<?php

ob_implicit_flush(true);
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
        fwrite(STDERR, "❌ Erreur : Impossible de localiser la racine de FreshRSS.\n");
        exit(1);
    }

    require_once $freshRssRoot . '/constants.php';
    require_once LIB_PATH . '/lib_rss.php';
    FreshRSS_Context::initSystem();

    return $freshRssRoot;
}

$freshRssRoot = bootstrapFreshRss();

$options = getopt('u:l:f:h', [
    'user:',
    'limit:',
    'feed:',
    'lang:',
    'all',
    'force',
    'multiplex',
    'no-multiplex',
    'probe-engine:',
    'worker-entry:',
    'worker-engine:',
    'help',
]);

if (isset($options['h']) || isset($options['help'])) {
    echo <<<HELP
Utilisation: php batch_translate_history.php [options]

Options:
  -u, --user <username>   Nom de l'utilisateur FreshRSS (défaut : utilisateur principal)
  -l, --limit <nombre>    Nombre d'articles à traiter (défaut : 50, max : 100000)
  -f, --feed <id>         Filtrer sur un ID de flux spécifique
      --lang <iso>        Code langue cible (ex: fr, en, de)
      --all               Traiter tous les articles (y compris ceux déjà lus)
      --force             Retraduire même si déjà présent dans le cache
      --multiplex         Activer le multiplexage de débit adaptatif (3x / 2x)
      --no-multiplex      Désactiver le multiplexage et forcer le mode séquentiel
  -h, --help              Afficher cette aide

HELP;
    exit(0);
}

function resolveUser(?string $requestedUser): string {
    if (!empty($requestedUser)) {
        if (is_dir(DATA_PATH . '/users/' . $requestedUser)) {
            return $requestedUser;
        }
        fwrite(STDERR, "❌ Erreur : L'utilisateur '{$requestedUser}' n'existe pas dans FreshRSS.\n");
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

    fwrite(STDERR, "❌ Erreur : Aucun utilisateur FreshRSS détecté.\n");
    exit(1);
}

$user = resolveUser($options['u'] ?? $options['user'] ?? null);
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

// === WORKER INTERNE 1 : SONDE ISOLÉE D'UN MOTEUR ===
if (isset($options['probe-engine'])) {
    $engine = trim((string)$options['probe-engine']);
    $ref = new ReflectionMethod($ext, 'dispatchProvider');
    $ref->setAccessible(true);

    $res = $ref->invoke($ext, $engine, 'Hello world', '', $targetLang);
    $ok = !empty($res['success']) && trim((string)($res['title'] ?? '')) !== '';

    echo json_encode([
        'engine'  => $engine,
        'healthy' => $ok,
        'error'   => $ok ? null : ($res['error'] ?? 'Réponse vide ou moteur non configuré'),
    ], JSON_UNESCAPED_UNICODE) . "\n";
    exit($ok ? 0 : 1);
}

// === WORKER INTERNE 2 : TRADUCTION D'UN ARTICLE SUR SON MOTEUR ASSIGNÉ ===
if (isset($options['worker-entry'])) {
    $entryId = (string)$options['worker-entry'];
    $assignedEngine = trim((string)($options['worker-engine'] ?? ''));
    $entryDAO = FreshRSS_Factory::createEntryDao();
    $entry = $entryDAO->searchById($entryId);

    if (!$entry instanceof FreshRSS_Entry) {
        echo json_encode(['id' => $entryId, 'success' => false, 'engine' => $assignedEngine, 'error' => 'Article introuvable'], JSON_UNESCAPED_UNICODE) . "\n";
        exit(1);
    }

    $title = $entry->title();
    $content = $entry->content();
    $hash = AITranslator_TagClassifier::getCanonicalHash($title, $content, $targetLang, 'translate');

    $ref = new ReflectionMethod($ext, 'dispatchProvider');
    $ref->setAccessible(true);

    $stash = [];
    $protectedContent = $ext->protectCodeBlocks($content, $stash);
    $res = $ref->invoke($ext, $assignedEngine, $title, $protectedContent, $targetLang);

    if (!empty($res['success'])) {
        $finalContent = $ext->applyGlossary($ext->restoreCodeBlocks($res['content'], $stash));
        $finalTitle   = $ext->applyGlossary($res['title']);
        $actualProvider = $res['provider'] ?? $assignedEngine;
        $ext->setCachedTranslation($hash, $finalTitle, $finalContent, $actualProvider, $targetLang); if (!empty($res['summary'])) { $ext->cacheOnePassTldr($title, $content, $targetLang, $res['summary'], $actualProvider); }

        echo json_encode([
            'id'       => $entryId,
            'success'  => true,
            'engine'   => $assignedEngine,
            'provider' => $actualProvider,
        ], JSON_UNESCAPED_UNICODE) . "\n";
        exit(0);
    }

    echo json_encode([
        'id'      => $entryId,
        'success' => false,
        'engine'  => $assignedEngine,
        'error'   => $res['error'] ?? 'Échec moteur assigné',
    ], JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}

// === ORCHESTRATEUR PRINCIPAL ===
$limit = max(1, min(100000, (int)($options['l'] ?? $options['limit'] ?? 50)));
$feedFilter = isset($options['f']) ? (int)$options['f'] : (isset($options['feed']) ? (int)$options['feed'] : 0);
$force = isset($options['force']);
$includeAll = isset($options['all']);

$primary   = trim((string)$ext->getUserConfig('provider', 'gemini'));
$fallback1 = trim((string)$ext->getUserConfig('fallback_provider', 'mistral', 'openai'));
$fallback2 = trim((string)$ext->getUserConfig('fallback_provider_2', ''));

$wantMultiplex = isset($options['no-multiplex'])
    ? false
    : (isset($options['multiplex']) || (bool)$ext->getUserConfig('multiplex_enabled', false));

$configuredPool = [];
foreach ([$primary, $fallback1, $fallback2] as $cand) {
    if ($cand !== '' && !in_array($cand, $configuredPool, true)) {
        $configuredPool[] = $cand;
    }
}

echo "====================================================\n";
echo "  xExtension-AITranslator - Traitement par lot CLI\n";
echo "====================================================\n";
echo "Racine FreshRSS   : {$freshRssRoot}\n";
echo "Utilisateur actif : {$user}\n";
echo "Limite d'articles : {$limit}\n";
if ($feedFilter > 0) {
    echo "Filtre flux ID    : {$feedFilter}\n";
}
echo "Moteurs configurés: " . implode(' -> ', $configuredPool) . "\n";
echo "Multiplexage 3x   : " . ($wantMultiplex ? 'Demandé (vérification des 3 moteurs...)' : 'Désactivé') . "\n";
echo "Langue cible      : {$targetLang}\n";
echo "Mode de sélection : " . ($includeAll ? 'Tous les articles (lus et non lus)' : 'Articles non lus uniquement') . "\n";
echo "Mode forcé        : " . ($force ? 'Oui' : 'Non') . "\n";
echo "----------------------------------------------------\n\n";

function runParallelProcesses(array $tasks): array {
    $procs = [];
    $pipesMap = [];
    $outputs = [];

    foreach ($tasks as $key => $cmd) {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (is_resource($proc)) {
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $procs[$key] = $proc;
            $pipesMap[$key] = $pipes;
            $outputs[$key] = '';
        }
    }

    while (!empty($procs)) {
        $read = [];
        foreach ($pipesMap as $key => $pipes) {
            if (is_resource($pipes[1])) {
                $read[] = $pipes[1];
            }
        }
        if (empty($read)) {
            break;
        }
        $write = null;
        $except = null;
        @stream_select($read, $write, $except, 1, 0);

        foreach ($procs as $key => $proc) {
            $chunk = stream_get_contents($pipesMap[$key][1]);
            if ($chunk !== false && $chunk !== '') {
                $outputs[$key] .= $chunk;
            }
            $status = proc_get_status($proc);
            if (!$status['running']) {
                $rem = stream_get_contents($pipesMap[$key][1]);
                if ($rem !== false) {
                    $outputs[$key] .= $rem;
                }
                fclose($pipesMap[$key][1]);
                fclose($pipesMap[$key][2]);
                proc_close($proc);
                unset($procs[$key], $pipesMap[$key]);
            }
        }
    }

    return $outputs;
}

$multiplexActive = false;
$activeEngines = $configuredPool;
$cooldownUntil = [];

if ($wantMultiplex) {
    if (count($configuredPool) < 3) {
        echo "ℹ️ Multiplexage ignoré : moins de 3 moteurs distincts configurés (" . count($configuredPool) . "/3).\n\n";
    } else {
        $probeTasks = [];
        foreach ($configuredPool as $eng) {
            $probeTasks[$eng] = sprintf(
                '%s %s --user %s --lang %s --probe-engine %s',
                escapeshellarg(PHP_BINARY),
                escapeshellarg(__FILE__),
                escapeshellarg($user),
                escapeshellarg($targetLang),
                escapeshellarg($eng)
            );
        }
        $probeResults = runParallelProcesses($probeTasks);
        $healthy = [];
        $failedProbes = [];

        $rateLimitedAtBoot = [];
        foreach ($configuredPool as $eng) {
            $decoded = json_decode(trim((string)($probeResults[$eng] ?? '')), true);
            $errMsg = (string)($decoded['error'] ?? '');
            if (!empty($decoded['healthy'])) {
                $healthy[] = $eng;
                $cooldownUntil[$eng] = 0;
            } elseif (preg_match('/HTTP\s*429|rate_limited|Rate limit/i', $errMsg) === 1) {
                $healthy[] = $eng;
                $cooldownUntil[$eng] = time() + 60;
                $rateLimitedAtBoot[] = $eng;
            } else {
                $failedProbes[] = "{$eng} (" . ($errMsg ?: 'injoignable') . ")";
            }
        }

        if (count($healthy) === 3) {
            $multiplexActive = true;
            $activeEngines = $healthy;
            if (empty($rateLimitedAtBoot)) {
                echo "⚡ Multiplexage 3x ACTIF : les 3 moteurs (" . implode(', ', $activeEngines) . ") répondent correctement (cooldown adaptatif 60s).\n\n";
            } else {
                echo "⚡ Multiplexage ACTIF (démarrage 2x, cooldown initial 60s sur " . implode(', ', $rateLimitedAtBoot) . " pour HTTP 429).\n\n";
            }
        } else {
            echo "⚠️ Multiplexage 3x DÉSACTIVÉ (" . count($healthy) . "/3 moteurs valides). Échec sur : " . implode(' ; ', $failedProbes) . "\n";
            echo "↪ Bascule automatique en mode cascade séquentielle.\n\n";
        }
    }
}

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
$toTranslateQueue = [];

foreach ($entryList as $idx => $entry) {
    $currentNum = $idx + 1;
    $id = $entry->id();
    $processed++;
    $title = $entry->title();
    $content = $entry->content();

    $eval = AITranslator_TagClassifier::evaluateArticle($entry, $targetLang, $ext);
    if ($eval === 'same_lang') {
        $skipped++;
        continue;
    }

    $hash = AITranslator_TagClassifier::getCanonicalHash($title, $content, $targetLang, 'translate');
    $existing = $ext->getCachedTranslation($hash);

    if ($existing && !$force) {
        $cached++;
        continue;
    }

    $toTranslateQueue[] = [
        'num'     => $currentNum,
        'id'      => (string)$id,
        'title'   => $title,
        'content' => $content,
        'hash'    => $hash,
    ];
}

$queueCount = count($toTranslateQueue);
echo "📊 Pré-filtrage terminé : {$skipped} déjà en {$targetLang} | {$cached} déjà en cache | {$queueCount} à traduire.\n\n";

$queueIdx = 0;

while ($queueIdx < $queueCount && $multiplexActive) {
    $now = time();
    $readyEngines = [];
    foreach ($activeEngines as $eng) {
        if (($cooldownUntil[$eng] ?? 0) <= $now) {
            $readyEngines[] = $eng;
        }
    }

    if (empty($readyEngines)) {
        sleep(5);
        continue;
    }

    $concurrency = count($readyEngines);
    $wave = array_slice($toTranslateQueue, $queueIdx, $concurrency);
    $tasks = [];
    $waveMeta = [];

    foreach ($wave as $slot => $item) {
        $assignedEngine = $readyEngines[$slot];
        $waveMeta[$item['id']] = ['item' => $item, 'engine' => $assignedEngine];
        $tasks[$item['id']] = sprintf(
            '%s %s --user %s --lang %s --worker-entry %s --worker-engine %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__FILE__),
            escapeshellarg($user),
            escapeshellarg($targetLang),
            escapeshellarg($item['id']),
            escapeshellarg($assignedEngine)
        );
    }

    $waveWidth = count($wave);
    echo "⚡ Vague parallèle ({$waveWidth}x : " . implode(', ', array_slice($readyEngines, 0, $waveWidth)) . ")...\n";
    $waveOutputs = runParallelProcesses($tasks);

    foreach ($waveMeta as $entryId => $meta) {
        $item = $meta['item'];
        $assignedEngine = $meta['engine'];
        $decoded = json_decode(trim((string)($waveOutputs[$entryId] ?? '')), true);

        if (!empty($decoded['success'])) {
            $prov = $decoded['provider'] ?? $assignedEngine;
            echo "   [{$item['num']}/{$total}] #{$entryId} [{$assignedEngine}] -> ✅ Succès ({$prov})\n";
            $translated++;
        } else {
            $err = $decoded['error'] ?? 'Erreur worker';
            $isArticleLangUnsupported = preg_match('/non traduite|résiduel/i', $err) === 1;
            if ($isArticleLangUnsupported) {
                echo "   [{$item['num']}/{$total}] #{$entryId} [{$assignedEngine}] -> ↪ Langue non supportée par ce moteur, bascule cascade...\n";
            } else {
                $isAuthError = preg_match('/HTTP\s*(401|403)|API\s*key|Clé\s*API/i', $err) === 1;
                $pauseSec = $isAuthError ? 86400 : 60;
                $cooldownUntil[$assignedEngine] = time() + $pauseSec;
                echo "   [{$item['num']}/{$total}] #{$entryId} [{$assignedEngine}] -> ⚠️ Cooldown {$pauseSec}s ({$err})\n";
            }

            $fallbackRes = $ext->executeTranslation($item['title'], $item['content'], $targetLang, $primary);
            if (!empty($fallbackRes['success'])) {
                $actualProvider = $fallbackRes['provider'] ?? $primary;
                $ext->setCachedTranslation($item['hash'], $fallbackRes['title'], $fallbackRes['content'], $actualProvider, $targetLang); if (!empty($fallbackRes['summary'])) { $ext->cacheOnePassTldr($item['title'], $item['content'], $targetLang, $fallbackRes['summary'], $actualProvider); }
                echo "   [{$item['num']}/{$total}] #{$entryId} -> ✅ Récupéré via {$actualProvider}\n";
                $translated++;
            } else {
                echo "   [{$item['num']}/{$total}] #{$entryId} -> ❌ Échec : " . ($fallbackRes['error'] ?? 'Inconnu') . "\n";
                $errors++;
            }
        }
    }

    $queueIdx += count($wave);
    usleep(400000);
}

while ($queueIdx < $queueCount) {
    $item = $toTranslateQueue[$queueIdx];
    $queueIdx++;

    echo "[{$item['num']}/{$total}] #{$item['id']} - Traduction via {$primary}... ";
    $res = $ext->executeTranslation($item['title'], $item['content'], $targetLang);

    if (!empty($res['success'])) {
        $actualProvider = $res['provider'] ?? $primary;
        $ext->setCachedTranslation($item['hash'], $res['title'], $res['content'], $actualProvider, $targetLang); if (!empty($res['summary'])) { $ext->cacheOnePassTldr($item['title'], $item['content'], $targetLang, $res['summary'], $actualProvider); }
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
