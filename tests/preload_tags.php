<?php

if (php_sapi_name() !== 'cli') {
    die("Ce script doit être exécuté en ligne de commande (CLI).\n");
}

function bootstrapFreshRss(): string {
    $candidates = [
        dirname(__DIR__, 3), // /opt/freshrss
        dirname(__DIR__, 4),
        dirname(__DIR__, 2),
        '/opt/freshrss',
        '/var/www/FreshRSS',
        '/usr/share/freshrss',
    ];

    $freshRssRoot = null;
    foreach ($candidates as $dir) {
        if (!empty($dir) && is_dir($dir)) {
            if (file_exists($dir . '/constants.php') && file_exists($dir . '/lib/lib_rss.php')) {
                $freshRssRoot = realpath($dir);
                break;
            }
        }
    }

    if (!$freshRssRoot) {
        fwrite(STDERR, "❌ Erreur : Impossible de localiser la racine de FreshRSS (constants.php ou lib/lib_rss.php manquant).\n");
        exit(1);
    }

    require_once $freshRssRoot . '/constants.php';
    require_once LIB_PATH . '/lib_rss.php';
    FreshRSS_Context::initSystem();

    return $freshRssRoot;
}

$freshRssRoot = bootstrapFreshRss();

$options = getopt('u:l:f:h', ['user:', 'limit:', 'feed:', 'help']);

if (isset($options['h']) || isset($options['help'])) {
    echo <<<HELP
Utilisation: php tests/preload_tags.php [options]

Analyse et audite la classification des flux et articles sans appel API externe.

Options:
  -u, --user <username>   Nom de l'utilisateur FreshRSS (défaut : utilisateur principal FreshRSS)
  -l, --limit <nombre>    Nombre d'articles à classifier (défaut : 30)
  -f, --feed <id>         Filtrer par ID de flux
  -h, --help              Afficher cette aide

HELP;
    exit(0);
}

function resolveUser(?string $requestedUser): string {
    if (!empty($requestedUser)) {
        if (is_dir(DATA_PATH . '/users/' . $requestedUser)) {
            return $requestedUser;
        }
        fwrite(STDERR, "❌ Erreur : L'utilisateur spécifié '{$requestedUser}' n'existe pas dans FreshRSS.\n");
        exit(1);
    }

    if (isset(FreshRSS_Context::$system_conf) && !empty(FreshRSS_Context::$system_conf->default_user)) {
        $default = FreshRSS_Context::$system_conf->default_user;
        if (is_dir(DATA_PATH . '/users/' . $default)) {
            return $default;
        }
    }

    $usersDir = DATA_PATH . '/users';
    if (is_dir($usersDir)) {
        $entries = scandir($usersDir);
        if (is_array($entries)) {
            foreach ($entries as $e) {
                if ($e !== '.' && $e !== '..' && $e !== '_' && is_dir($usersDir . '/' . $e)) {
                    return $e;
                }
            }
        }
    }

    fwrite(STDERR, "❌ Erreur : Aucun utilisateur FreshRSS détecté dans " . DATA_PATH . "/users.\n");
    exit(1);
}

$user = resolveUser($options['u'] ?? $options['user'] ?? null);
$limit = max(1, min(500, (int)($options['l'] ?? $options['limit'] ?? 30)));
$feedFilter = isset($options['f']) ? (int)$options['f'] : (isset($options['feed']) ? (int)$options['feed'] : 0);

FreshRSS_Context::initUser($user);

if (class_exists('Minz_ExtensionManager')) {
    Minz_ExtensionManager::init();
}

$extDir = dirname(__DIR__);
require_once $extDir . '/extension.php';
require_once $extDir . '/TagClassifier.php';

$ext = Minz_ExtensionManager::findExtension('AITranslator')
    ?: Minz_ExtensionManager::findExtension('AI Translator')
    ?: Minz_ExtensionManager::findExtension('xExtension-AITranslator');

if (!$ext instanceof AITranslatorExtension) {
    $meta = @json_decode(@file_get_contents($extDir . '/metadata.json'), true) ?: [];
    $ext = new AITranslatorExtension($meta);
}

$targetLang = $ext->getUserConfig('target_lang', 'fr');

echo "====================================================\n";
echo "  Audit de classification linguistique des flux\n";
echo "====================================================\n";
echo "Racine FreshRSS : {$freshRssRoot}\n";
echo "Utilisateur     : {$user}\n";
echo "Langue cible    : {$targetLang}\n";
echo "Limite demandée : {$limit}\n";
echo "----------------------------------------------------\n\n";

$entryDAO = FreshRSS_Factory::createEntryDao();

$feedId = $feedFilter > 0 ? $feedFilter : 0;
$type = $feedId > 0 ? 'f' : 'a';
$stateAll = class_exists('FreshRSS_Entry') ? FreshRSS_Entry::STATE_ALL : 3;

if (PHP_VERSION_ID >= 80000) {
    $rawEntries = $entryDAO->listWhere(type: $type, id: $feedId, state: $stateAll, limit: $limit);
} else {
    $rawEntries = $entryDAO->listWhere($type, $feedId, $stateAll, null, '', '', 'id', 'DESC', '', null, $limit);
}

$entryList = [];
foreach ($rawEntries as $item) {
    if ($item instanceof FreshRSS_Entry) {
        $entryList[] = $item;
    } elseif (is_array($item) && isset($item['id'])) {
        $entryObj = $entryDAO->searchById($item['id']);
        if ($entryObj instanceof FreshRSS_Entry) {
            $entryList[] = $entryObj;
        }
    }
}

if (empty($entryList)) {
    echo "ℹ️ Aucun article trouvé pour l'échantillonnage.\n";
    exit(0);
}

printf("%-18s | %-8s | %-16s | %s\n", "ID Article", "Flux ID", "Décision", "Titre");
echo str_repeat('-', 85) . "\n";

foreach ($entryList as $entry) {
    $id = $entry->id();
    $currentFeedId = $entry->feedId();
    $feedRule = AITranslator_TagClassifier::getFeedRule($currentFeedId);
    $decision = AITranslator_TagClassifier::evaluateArticle($entry, $targetLang, $ext);

    $label = match($decision) {
        'same_lang'         => 'ORIGINAL (' . strtoupper($targetLang) . ')',
        'foreign_confirmed' => 'À TRADUIRE',
        default             => strtoupper($decision),
    };

    if ($feedRule === 'ignore') {
        $label .= ' [Ignorer]';
    } elseif ($feedRule === 'translate') {
        $label .= ' [Forcer]';
    }

    $shortTitle = mb_strimwidth($entry->title(), 0, 40, '...');
    printf("%-18s | %-8s | %-16s | %s\n", $id, $currentFeedId, $label, $shortTitle);
}

echo str_repeat('-', 85) . "\n";
echo "✅ Audit terminé avec succès (" . count($entryList) . " articles analysés).\n";