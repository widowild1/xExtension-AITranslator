<?php

if (php_sapi_name() !== 'cli') {
    die("Ce script doit être exécuté en ligne de commande (CLI).\n");
}

function bootstrapFreshRss(): string {
    $candidates = [
        dirname(__DIR__, 2),
        dirname(__DIR__, 3),
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

$options = getopt('u:l:f:h', ['user:', 'limit:', 'feed:', 'lang:', 'all', 'force', 'help']);

if (isset($options['h']) || isset($options['help'])) {
    echo <<<HELP
Utilisation: php batch_translate_history.php [options]

Options:
  -u, --user <username>   Nom de l'utilisateur FreshRSS (défaut : utilisateur principal FreshRSS)
  -l, --limit <nombre>    Nombre d'articles à traiter (défaut : 50)
  -f, --feed <id>         Filtrer sur un ID de flux spécifique
      --lang <iso>        Code langue cible (ex: fr, en, de)
      --all               Traiter tous les articles (y compris ceux déjà lus)
      --force             Retraduire même si déjà présent dans le cache
  -h, --help              Afficher cette aide

Exemples:
  php batch_translate_history.php --limit 100
  php batch_translate_history.php --all --limit 10

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
$limit = max(1, min(1000, (int)($options['l'] ?? $options['limit'] ?? 50)));
$feedFilter = isset($options['f']) ? (int)$options['f'] : (isset($options['feed']) ? (int)$options['feed'] : 0);
$force = isset($options['force']);
$includeAll = isset($options['all']);

FreshRSS_Context::initUser($user);

if (class_exists('Minz_ExtensionManager')) {
    Minz_ExtensionManager::init();
}

$extDir = __DIR__;
require_once $extDir . '/extension.php';
require_once $extDir . '/TagClassifier.php';
require_once $extDir . '/ArticleUiRenderer.php';

$ext = Minz_ExtensionManager::findExtension('AITranslator')
    ?: Minz_ExtensionManager::findExtension('AI Translator')
    ?: Minz_ExtensionManager::findExtension('xExtension-AITranslator');

if (!$ext instanceof AITranslatorExtension) {
    $meta = @json_decode(@file_get_contents($extDir . '/metadata.json'), true) ?: [];
    $ext = new AITranslatorExtension($meta);
}

$targetLang = $options['lang'] ?? $ext->getUserConfig('target_lang', 'fr');
$provider   = $ext->getUserConfig('provider', 'gemini');

echo "====================================================\n";
echo "  xExtension-AITranslator - Traitement par lot CLI\n";
echo "====================================================\n";
echo "Racine FreshRSS   : {$freshRssRoot}\n";
echo "Utilisateur actif : {$user}\n";
echo "Limite d'articles : {$limit}\n";
if ($feedFilter > 0) {
    echo "Filtre flux ID    : {$feedFilter}\n";
}
echo "Moteur principal  : {$provider}\n";
echo "Langue cible      : {$targetLang}\n";
echo "Mode de sélection : " . ($includeAll ? 'Tous les articles (lus et non lus)' : 'Articles non lus uniquement') . "\n";
echo "Mode forcé        : " . ($force ? 'Oui' : 'Non') . "\n";
echo "----------------------------------------------------\n\n";

$entryDAO = FreshRSS_Factory::createEntryDao();

$feedId = $feedFilter > 0 ? $feedFilter : 0;
$type = $feedId > 0 ? 'f' : 'a';

$stateFilter = $includeAll
    ? (class_exists('FreshRSS_Entry') ? FreshRSS_Entry::STATE_ALL : 3)
    : (class_exists('FreshRSS_Entry') ? FreshRSS_Entry::STATE_NOT_READ : 2);

if (PHP_VERSION_ID >= 80000) {
    $rawEntries = $entryDAO->listWhere(type: $type, id: $feedId, state: $stateFilter, limit: $limit);
} else {
    $rawEntries = $entryDAO->listWhere($type, $feedId, $stateFilter, null, '', '', 'id', 'DESC', '', null, $limit);
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
    echo "ℹ️ Aucun article éligible trouvé pour le compte {$user}.\n";
    exit(0);
}

$total = count($entryList);
$processed = 0;
$translated = 0;
$cached = 0;
$skipped = 0;
$errors = 0;

foreach ($entryList as $idx => $entry) {
    $currentNum = $idx + 1;
    $id = $entry->id();
    $processed++;
    $title = $entry->title();
    $content = $entry->content();

    $eval = AITranslator_TagClassifier::evaluateArticle($entry, $targetLang, $ext);
    if ($eval === 'same_lang') {
        echo "[{$currentNum}/{$total}] #{$id} - Déjà en langue cible ({$targetLang}) -> Ignoré\n";
        $skipped++;
        continue;
    }

    $hash = AITranslator_TagClassifier::getCanonicalHash($title, $content, $targetLang, 'translate');
    $existing = $ext->getCachedTranslation($hash);

    if ($existing && !$force) {
        echo "[{$currentNum}/{$total}] #{$id} - Déjà en cache ({$existing['provider']}) -> OK\n";
        $cached++;
        continue;
    }

    echo "[{$currentNum}/{$total}] #{$id} - Traduction via {$provider}... ";
    $res = $ext->executeTranslation($title, $content, $targetLang);

    if (!empty($res['success'])) {
        $actualProvider = $res['provider'] ?? $provider;
        $ext->setCachedTranslation($hash, $res['title'], $res['content'], $actualProvider, $targetLang);
        echo "✅ Succès ({$actualProvider})\n";
        $translated++;
    } else {
        echo "❌ Erreur : " . ($res['error'] ?? 'Échec inconnu') . "\n";
        $errors++;
    }

    usleep(250000);
}

echo "\n====================================================\n";
echo "  Bilan du traitement :\n";
echo "  • Total analysés    : {$processed}\n";
echo "  • Nouveaux traduits : {$translated}\n";
echo "  • Déjà en cache     : {$cached}\n";
echo "  • Langue d'origine  : {$skipped}\n";
echo "  • Erreurs           : {$errors}\n";
echo "====================================================\n";