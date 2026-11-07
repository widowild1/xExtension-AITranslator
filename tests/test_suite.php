<?php

if (php_sapi_name() !== 'cli') {
    die("Ce banc d'essai doit être exécuté en ligne de commande (CLI).\n");
}

$extDir = dirname(__DIR__);

function bootstrapFreshRss(): string {
    $candidates = [
        dirname(__DIR__, 3),
        dirname(__DIR__, 4),
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
        fwrite(STDERR, "❌ Erreur : Racine FreshRSS introuvable.\n");
        exit(1);
    }

    require_once $freshRssRoot . '/constants.php';
    require_once LIB_PATH . '/lib_rss.php';
    FreshRSS_Context::initSystem();

    return $freshRssRoot;
}

$freshRssRoot = bootstrapFreshRss();

require_once $extDir . '/extension.php';
require_once $extDir . '/TagClassifier.php';
require_once $extDir . '/ArticleUiRenderer.php';

function resolveUser(): string {
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

    return 'admin';
}

$user = resolveUser();
FreshRSS_Context::initUser($user);

if (class_exists('Minz_ExtensionManager')) {
    Minz_ExtensionManager::init();
}

$ext = Minz_ExtensionManager::findExtension('AITranslator')
    ?: Minz_ExtensionManager::findExtension('AI Translator')
    ?: Minz_ExtensionManager::findExtension('xExtension-AITranslator');

if (!$ext instanceof AITranslatorExtension) {
    $meta = @json_decode(@file_get_contents($extDir . '/metadata.json'), true) ?: [];
    $ext = new AITranslatorExtension($meta);
}

echo "====================================================\n";
echo "  Banc d'essai & Tests unitaires AITranslator\n";
echo "====================================================\n";
echo "Extension : " . $extDir . "\n";
echo "FreshRSS  : " . $freshRssRoot . "\n";
echo "Utilisateur : " . $user . "\n";
echo "----------------------------------------------------\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertTest(string $name, bool $condition, string $details = ''): void {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "  ✅ [PASS] {$name}" . ($details ? " ({$details})" : '') . "\n";
        $testsPassed++;
    } else {
        echo "  ❌ [FAIL] {$name}" . ($details ? " -> {$details}" : '') . "\n";
        $testsFailed++;
    }
}

// 1. Test Base SQLite
echo "1. Intégrité du stockage local (SQLite WAL) :\n";
$db = $ext->getDatabase();
assertTest("Connexion PDO SQLite", $db instanceof PDO);

$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
assertTest("Table 'cache' présente", in_array('cache', $tables, true));
assertTest("Table 'feed_rules' présente", in_array('feed_rules', $tables, true));
assertTest("Table 'glossary' présente", in_array('glossary', $tables, true));
assertTest("Table 'activity_logs' présente", in_array('activity_logs', $tables, true));

// 2. Test Protection du code HTML
echo "\n2. Moteur de protection des balises de code (<pre>/<code>) :\n";
$stash = [];
$htmlIn = "<p>Exemple :</p><pre><code>console.log('hello world');</code></pre><p>Fin</p>";
$protected = $ext->protectCodeBlocks($htmlIn, $stash);
assertTest("Masquage du code", strpos($protected, '___AITRANS_STASH_0___') !== false);
$restored = $ext->restoreCodeBlocks($protected, $stash);
assertTest("Restauration exacte du code", $restored === $htmlIn);

// 3. Test Glossaire
echo "\n3. Moteur de glossaire personnalisé :\n";
$db->exec("INSERT OR REPLACE INTO glossary (source_term, target_term, case_sensitive) VALUES ('Open Source', 'Logiciel Libre', 0)");
$textGlossary = $ext->applyGlossary("Vive le Open Source et le open source.");
assertTest("Remplacement terminologique", strpos($textGlossary, 'Logiciel Libre') !== false);

// 4. Test Rendu Bilingue
echo "\n4. Formatage du rendu bilingue :\n";
$titleSplit = $ext->formatBilingualTitle("Titre Traduit", "Original Title");
assertTest("Titre scindé", $titleSplit === "Titre Traduit | Original Title");

$contentSplit = $ext->formatBilingualContent("<p>Traduction</p>", "<p>Original</p>");
assertTest("Conteneur bilingue", strpos($contentSplit, 'aitranslator-split-wrapper') !== false && strpos($contentSplit, 'aitranslator-original-box') !== false);

// 5. Test Fournisseurs configurés
echo "\n5. Connectivité des moteurs de traduction configurés :\n";
$primary = $ext->getUserConfig('provider', 'gemini');
echo "  -> Test du fournisseur principal '{$primary}' :\n";
$resTest = $ext->testProviderConnection($primary);
assertTest("Fournisseur principal ({$primary})", !empty($resTest['success']), $resTest['message'] ?? ($resTest['error'] ?? ''));

echo "\n----------------------------------------------------\n";
echo "Bilan du banc d'essai : {$testsPassed} réussis, {$testsFailed} échecs.\n";
echo "====================================================\n";

exit($testsFailed > 0 ? 1 : 0);