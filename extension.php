<?php

require_once __DIR__ . '/TagClassifier.php';
require_once __DIR__ . '/ArticleUiRenderer.php';

class AITranslatorExtension extends Minz_Extension {

    private static ?PDO $sharedDb = null;

    // === ACCÈS CONFIGURATION UTILISATEUR ===

    public function getUserConfig(string $key, $default = null) {
        $val = $this->getUserConfigurationValue($key);
        return ($val !== null && $val !== '') ? $val : $default;
    }

    public function setUserConfig(string $key, $value): void {
        $this->setUserConfigurationValue($key, $value);
    }

    // === CHARGEMENT PUBLIC DES TRADUCTIONS ===

    public function loadTranslates(): void {
        $this->registerTranslates();
    }

    // === INITIALISATION DE L'EXTENSION ===

    public function init(): void {
        $this->loadTranslates();

        if (php_sapi_name() !== 'cli') {
            try {
                $styleUrl = $this->getFileUrl('style.css', 'css');
                $scriptUrl = $this->getFileUrl('script.js', 'js');
                $vStyle = @filemtime($this->getPath() . '/static/style.css');
                $vScript = @filemtime($this->getPath() . '/static/script.js');

                $sepStyle = (strpos($styleUrl, '?') !== false) ? '&' : '?';
                $sepScript = (strpos($scriptUrl, '?') !== false) ? '&' : '?';

                Minz_View::appendStyle($styleUrl . $sepStyle . 'v=' . $vStyle);
                Minz_View::appendScript($scriptUrl . $sepScript . 'v=' . $vScript);
            } catch (\Throwable $e) {
                // Ignore l'absence de vue lors des scripts d'arrière-plan
            }
        }

        $this->registerHook('entry_before_insert', [$this, 'hookEntryBeforeInsert']);
        $this->registerHook('entry_before_display', [$this, 'hookEntryBeforeDisplay']);
        $this->registerController('aiTranslator');

        try {
            $this->initDatabase();
        } catch (\Throwable $e) {
            error_log('xExtension-AITranslator DB Init error: ' . $e->getMessage());
        }
    }

    // === BASE DE DONNÉES SQLITE LOCALE ===

    public function getDatabasePath(): string {
        $base = $this->getPath();
        if (empty($base)) {
            $base = __DIR__;
        }
        return $base . '/data/translator.db';
    }

    public function closeDatabase(): void {
        self::$sharedDb = null;
    }

    public function getDatabase(): PDO {
        if (self::$sharedDb === null) {
            $dbPath = $this->getDatabasePath();
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            self::$sharedDb = new PDO('sqlite:' . $dbPath);
            self::$sharedDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$sharedDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$sharedDb->exec('PRAGMA journal_mode = WAL;');

            $this->initDatabase(self::$sharedDb);
        }
        return self::$sharedDb;
    }

    public function initDatabase(?PDO $db = null): void {
        if ($db === null) {
            $db = $this->getDatabase();
            return;
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS cache (
                hash TEXT PRIMARY KEY,
                provider TEXT NOT NULL,
                target_lang TEXT NOT NULL DEFAULT 'fr',
                title TEXT,
                content TEXT NOT NULL,
                created_at INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_cache_created ON cache(created_at);

            CREATE TABLE IF NOT EXISTS feed_rules (
                feed_id INTEGER PRIMARY KEY,
                rule TEXT NOT NULL,
                target_lang TEXT DEFAULT 'fr',
                updated_at INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS glossary (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                source_term TEXT NOT NULL UNIQUE,
                target_term TEXT NOT NULL,
                case_sensitive INTEGER DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS activity_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                level TEXT NOT NULL,
                message TEXT NOT NULL,
                context TEXT,
                created_at INTEGER NOT NULL
            );
        ");

        $this->ensureColumnExists($db, 'cache', 'target_lang', "TEXT NOT NULL DEFAULT 'fr'");
        $this->ensureColumnExists($db, 'feed_rules', 'target_lang', "TEXT DEFAULT 'fr'");
    }

    private function ensureColumnExists(PDO $db, string $table, string $column, string $definition): void {
        try {
            $stmt = $db->query("PRAGMA table_info({$table})");
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array($column, $cols, true)) {
                $db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        } catch (\Throwable $e) {}
    }

    public function logActivity(string $level, string $message, ?string $context = null): void {
        try {
            $stmt = $this->getDatabase()->prepare('INSERT INTO activity_logs (level, message, context, created_at) VALUES (:lvl, :msg, :ctx, :ts)');
            $stmt->execute([
                ':lvl' => strtoupper($level),
                ':msg' => $message,
                ':ctx' => $context,
                ':ts'  => time(),
            ]);
        } catch (\Throwable $e) {
            error_log('xExtension-AITranslator Log Error: ' . $e->getMessage());
        }
    }

    public function getCachedTranslation(string $hash): ?array {
        try {
            $stmt = $this->getDatabase()->prepare('SELECT title, content, provider, target_lang FROM cache WHERE hash = :hash LIMIT 1');
            $stmt->execute([':hash' => $hash]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function setCachedTranslation(string $hash, string $title, string $content, string $provider, string $targetLang = 'fr'): bool {
        try {
            $stmt = $this->getDatabase()->prepare('INSERT OR REPLACE INTO cache (hash, provider, target_lang, title, content, created_at) VALUES (:h, :p, :l, :t, :c, :ts)');
            return $stmt->execute([
                ':h'  => $hash,
                ':p'  => $provider,
                ':l'  => $targetLang,
                ':t'  => $title,
                ':c'  => $content,
                ':ts' => time(),
            ]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function clearCache(): bool {
        try {
            $this->getDatabase()->exec('DELETE FROM cache');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function clearLogs(): bool {
        try {
            $this->getDatabase()->exec('DELETE FROM activity_logs');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // === TABLEAU DE BORD : STATISTIQUES D'ACTIVITÉ & QUOTAS ===

    public function getUsageStats(): array {
        $db = $this->getDatabase();
        $stats = [
            'cache_count'       => 0,
            'cache_by_provider' => [],
            'recent_logs_count' => 0,
            'success_count'     => 0,
            'error_count'       => 0,
            'tldr_count'        => 0,
            'last_activity'     => null,
        ];

        try {
            $stmt = $db->query('SELECT provider, COUNT(*) as count FROM cache GROUP BY provider');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stats['cache_by_provider'][$row['provider']] = (int)$row['count'];
                $stats['cache_count'] += (int)$row['count'];
            }

            $thirtyDaysAgo = time() - (30 * 86400);
            $stmt = $db->prepare('SELECT level, COUNT(*) as count FROM activity_logs WHERE created_at >= :since GROUP BY level');
            $stmt->execute([':since' => $thirtyDaysAgo]);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lvl = strtoupper($row['level']);
                $cnt = (int)$row['count'];
                $stats['recent_logs_count'] += $cnt;

                if ($lvl === 'SUCCESS' || $lvl === 'AUTO_INGEST') {
                    $stats['success_count'] += $cnt;
                } elseif ($lvl === 'ERROR') {
                    $stats['error_count'] += $cnt;
                } elseif ($lvl === 'TLDR') {
                    $stats['tldr_count'] += $cnt;
                }
            }

            $stmt = $db->query('SELECT created_at FROM activity_logs ORDER BY id DESC LIMIT 1');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $stats['last_activity'] = (int)$row['created_at'];
            }
        } catch (\Throwable $e) {}

        return $stats;
    }

    // === MODULE IMPORT / EXPORT : CONFIGURATION ET BASE SQLITE ===

    public function exportConfigurationData(): array {
        $configKeys = [
            'target_lang',
            'pretranslate_enabled',
            'provider',
            'fallback_provider',
            'tldr_provider',
            'azure_api_key',
            'azure_region',
            'nllb_api_url',
            'gemini_api_key',
            'gemini_model',
            'mistral_api_key',
            'mistral_model',
            'ollama_api_url',
            'ollama_model',
            'openai_api_key',
            'openai_model',
            'openai_api_url',
            'libretranslate_api_url',
            'libretranslate_api_key',
            'deepl_api_key',
            'deepl_api_type',
        ];

        $config = [];
        foreach ($configKeys as $k) {
            $config[$k] = $this->getUserConfig($k, '');
        }

        $feedRules = [];
        $glossary = [];

        try {
            $stmt = $this->getDatabase()->query('SELECT feed_id, rule, target_lang FROM feed_rules');
            $feedRules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->getDatabase()->query('SELECT source_term, target_term, case_sensitive FROM glossary');
            $glossary = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        return [
            'format'      => 'xExtension-AITranslator',
            'version'     => '1.2',
            'exported_at' => time(),
            'config'      => $config,
            'feed_rules'  => $feedRules,
            'glossary'    => $glossary,
        ];
    }

    public function importConfigurationData(array $data): array {
        $countConfig = 0;
        $countRules = 0;
        $countGlossary = 0;

        $config = $data['config'] ?? $data;
        if (is_array($config)) {
            foreach ($config as $k => $v) {
                if (is_scalar($v) && !in_array($k, ['format', 'version', 'exported_at', '_csrf'], true)) {
                    $this->setUserConfigurationValue($k, $v);
                    $countConfig++;
                }
            }
        }

        $db = $this->getDatabase();

        if (!empty($data['feed_rules']) && is_array($data['feed_rules'])) {
            $stmt = $db->prepare('INSERT OR REPLACE INTO feed_rules (feed_id, rule, target_lang, updated_at) VALUES (:id, :r, :l, :ts)');
            foreach ($data['feed_rules'] as $rule) {
                if (!empty($rule['feed_id']) && !empty($rule['rule'])) {
                    $stmt->execute([
                        ':id' => (int)$rule['feed_id'],
                        ':r'  => (string)$rule['rule'],
                        ':l'  => (string)($rule['target_lang'] ?? 'fr'),
                        ':ts' => time(),
                    ]);
                    $countRules++;
                }
            }
        }

        if (!empty($data['glossary']) && is_array($data['glossary'])) {
            $stmt = $db->prepare('INSERT OR REPLACE INTO glossary (source_term, target_term, case_sensitive) VALUES (:s, :t, :c)');
            foreach ($data['glossary'] as $g) {
                if (!empty($g['source_term']) && !empty($g['target_term'])) {
                    $stmt->execute([
                        ':s' => trim((string)$g['source_term']),
                        ':t' => trim((string)$g['target_term']),
                        ':c' => (int)($g['case_sensitive'] ?? 0),
                    ]);
                    $countGlossary++;
                }
            }
        }

        $this->logActivity('CONFIG', "Importation réussie : {$countConfig} clés, {$countRules} règles, {$countGlossary} termes");
        return [
            'config_count'   => $countConfig,
            'rules_count'    => $countRules,
            'glossary_count' => $countGlossary,
        ];
    }

    public function importDatabaseFile(string $tmpFilePath): bool {
        if (!is_uploaded_file($tmpFilePath)) {
            return false;
        }

        $fp = @fopen($tmpFilePath, 'rb');
        if (!$fp) {
            return false;
        }
        $header = fread($fp, 16);
        fclose($fp);

        if ($header !== "SQLite format 3\000") {
            $this->logActivity('ERROR', 'Tentative d’import de base SQLite invalide (signature binaire incorrecte)');
            return false;
        }

        $dbPath = $this->getDatabasePath();
        $backupPath = $dbPath . '.bak';

        try {
            $this->getDatabase()->exec('PRAGMA wal_checkpoint(TRUNCATE);');
        } catch (\Throwable $e) {}
        $this->closeDatabase();

        if (file_exists($dbPath)) {
            @copy($dbPath, $backupPath);
        }

        @unlink($dbPath . '-wal');
        @unlink($dbPath . '-shm');

        if (!@move_uploaded_file($tmpFilePath, $dbPath)) {
            if (file_exists($backupPath)) {
                @copy($backupPath, $dbPath);
            }
            return false;
        }

        @chmod($dbPath, 0664);

        try {
            $testDb = new PDO('sqlite:' . $dbPath);
            $testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $res = $testDb->query('PRAGMA integrity_check;')->fetchColumn();
            if ($res !== 'ok') {
                throw new Exception("Échec de l'intégrité SQLite: {$res}");
            }
            $this->initDatabase($testDb);
            $testDb = null;
        } catch (\Throwable $e) {
            $this->logActivity('ERROR', 'Base restaurée corrompue, rollback : ' . $e->getMessage());
            if (file_exists($backupPath)) {
                @copy($backupPath, $dbPath);
            }
            return false;
        }

        $this->logActivity('DATABASE', 'Restauration complète de la base SQLite effectuée avec succès');
        return true;
    }

    // === BANC D'ESSAI : TEST DE CONNEXION DES FOURNISSEURS ===

    public function testProviderConnection(string $provider, array $params = []): array {
        $start = microtime(true);

        try {
            switch ($provider) {
                case 'azure':
                    $key = !empty($params['api_key']) ? (string)$params['api_key'] : (string)$this->getUserConfig('azure_api_key', '');
                    $region = !empty($params['region']) ? (string)$params['region'] : (string)$this->getUserConfig('azure_region', 'global');
                    if (empty($key)) {
                        return ['success' => false, 'error' => 'Clé API Azure non renseignée'];
                    }

                    $endpoint = 'https://api.cognitive.microsofttranslator.com/translate?api-version=3.0&to=fr&textType=plain';
                    $payload = [['Text' => 'Hello']];

                    $headers = [
                        'Content-Type: application/json; charset=UTF-8',
                        'Ocp-Apim-Subscription-Key: ' . $key,
                    ];
                    if (!empty($region)) {
                        $headers[] = 'Ocp-Apim-Subscription-Region: ' . $region;
                    }

                    $ch = curl_init($endpoint);
                    curl_setopt_array($ch, [
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => json_encode($payload),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER     => $headers,
                        CURLOPT_TIMEOUT        => 15,
                        CURLOPT_CONNECTTIMEOUT => 6,
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $err = curl_error($ch);
                    curl_close($ch);
                    $latency = round((microtime(true) - $start) * 1000);

                    if ($err || $httpCode !== 200) {
                        return ['success' => false, 'error' => "Azure HTTP {$httpCode} : " . ($err ?: $response)];
                    }

                    $json = json_decode($response, true);
                    $resText = $json[0]['translations'][0]['text'] ?? 'OK';
                    return [
                        'success' => true,
                        'latency' => $latency,
                        'message' => "Connexion Azure validée ({$latency} ms) · Traduction : \"{$resText}\""
                    ];

                case 'nllb':
                    $rawUrl = !empty($params['api_url']) ? (string)$params['api_url'] : (string)$this->getUserConfig('nllb_api_url', 'http://localhost:8000');
                    $baseUrl = rtrim($rawUrl, '/');
                    $endpoint = (strpos($baseUrl, '/translate') === false) ? $baseUrl . '/translate' : $baseUrl;

                    $payload = [
                        'text'        => 'Hello',
                        'source'      => 'auto',
                        'target'      => 'fra_Latn',
                        'source_lang' => 'auto',
                        'target_lang' => 'fra_Latn',
                    ];

                    $ch = curl_init($endpoint);
                    curl_setopt_array($ch, [
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => json_encode($payload),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                        CURLOPT_TIMEOUT        => 15,
                        CURLOPT_CONNECTTIMEOUT => 5,
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $err = curl_error($ch);
                    curl_close($ch);
                    $latency = round((microtime(true) - $start) * 1000);

                    if ($err || $httpCode !== 200) {
                        return ['success' => false, 'error' => "NLLB HTTP {$httpCode} : " . ($err ?: $response)];
                    }

                    $json = json_decode($response, true);
                    $resText = $json['translatedText'] ?? $json['result'] ?? $json['translation'] ?? 'OK';
                    return [
                        'success' => true,
                        'latency' => $latency,
                        'message' => "Serveur Meta NLLB-200 validé ({$latency} ms) · Réponse : \"{$resText}\""
                    ];

                case 'libretranslate':
                    $url = !empty($params['api_url']) ? rtrim($params['api_url'], '/') : rtrim((string)($this->getUserConfig('libretranslate_api_url') ?: 'http://localhost:5000'), '/');
                    $key = !empty($params['api_key']) ? (string)$params['api_key'] : (string)$this->getUserConfig('libretranslate_api_key', '');
                    $res = $this->callLibreTranslateApi($url . '/translate', 'Hello', 'fr', 'text', $key);
                    $latency = round((microtime(true) - $start) * 1000);

                    if (!empty($res['success'])) {
                        return [
                            'success' => true,
                            'latency' => $latency,
                            'message' => "Connexion établie ({$latency} ms) · Réponse : \"{$res['text']}\""
                        ];
                    }
                    return ['success' => false, 'error' => $res['error'] ?? 'Échec LibreTranslate'];

                case 'gemini':
                    $key = !empty($params['api_key']) ? (string)$params['api_key'] : (string)$this->getUserConfig('gemini_api_key', '');
                    $model = !empty($params['model']) ? (string)$params['model'] : (string)$this->getUserConfig('gemini_model', 'gemini-3.5-flash-lite');
                    if (empty($key)) {
                        return ['success' => false, 'error' => 'Clé API Gemini non renseignée'];
                    }

                    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
                    $payload = [
                        'contents' => [['role' => 'user', 'parts' => [['text' => 'ping']]]],
                        'generationConfig' => ['maxOutputTokens' => 5]
                    ];

                    $ch = curl_init($endpoint);
                    curl_setopt_array($ch, [
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => json_encode($payload),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                        CURLOPT_TIMEOUT        => 15,
                        CURLOPT_CONNECTTIMEOUT => 6,
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $err = curl_error($ch);
                    curl_close($ch);
                    $latency = round((microtime(true) - $start) * 1000);

                    if ($err || $httpCode !== 200) {
                        return ['success' => false, 'error' => "Gemini HTTP {$httpCode} : " . ($err ?: $response)];
                    }
                    return [
                        'success' => true,
                        'latency' => $latency,
                        'message' => "Connexion Gemini validée ({$latency} ms) · Modèle : {$model}"
                    ];

                case 'mistral':
                    $key = !empty($params['api_key']) ? (string)$params['api_key'] : (string)$this->getUserConfig('mistral_api_key', '');
                    $model = !empty($params['model']) ? (string)$params['model'] : (string)$this->getUserConfig('mistral_model', 'mistral-small-latest');
                    if (empty($key)) {
                        return ['success' => false, 'error' => 'Clé API Mistral non renseignée'];
                    }

                    $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
                    curl_setopt_array($ch, [
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => json_encode([
                            'model'      => $model,
                            'messages'   => [['role' => 'user', 'content' => 'ping']],
                            'max_tokens' => 5
                        ]),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER     => [
                            'Content-Type: application/json',
                            'Authorization: Bearer ' . $key
                        ],
                        CURLOPT_TIMEOUT        => 15,
                        CURLOPT_CONNECTTIMEOUT => 6,
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $err = curl_error($ch);
                    curl_close($ch);
                    $latency = round((microtime(true) - $start) * 1000);

                    if ($err || $httpCode !== 200) {
                        return ['success' => false, 'error' => "Mistral HTTP {$httpCode} : " . ($err ?: $response)];
                    }
                    return [
                        'success' => true,
                        'latency' => $latency,
                        'message' => "Connexion Mistral validée ({$latency} ms) · Modèle : {$model}"
                    ];

                case 'ollama':
                    $rawUrl = !empty($params['api_url']) ? (string)$params['api_url'] : (string)$this->getUserConfig('ollama_api_url', 'http://localhost:11434');
                    $model = !empty($params['model']) ? (string)$params['model'] : (string)$this->getUserConfig('ollama_model', 'llama3.2:latest');

                    $baseUrl = rtrim($rawUrl, '/');
                    if (strpos($baseUrl, '/chat/completions') === false) {
                        $endpoint = (substr($baseUrl, -3) === '/v1') ? $baseUrl . '/chat/completions' : $baseUrl . '/v1/chat/completions';
                    } else {
                        $endpoint = $baseUrl;
                    }

                    $ch = curl_init($endpoint);
                    curl_setopt_array($ch, [
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => json_encode([
                            'model'      => $model,
                            'messages'   => [['role' => 'user', 'content' => 'ping']],
                            'max_tokens' => 5
                        ]),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                        CURLOPT_TIMEOUT        => 15,
                        CURLOPT_CONNECTTIMEOUT => 5,
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $err = curl_error($ch);
                    curl_close($ch);
                    $latency = round((microtime(true) - $start) * 1000);

                    if ($err || $httpCode !== 200) {
                        return ['success' => false, 'error' => "Ollama/llama.cpp HTTP {$httpCode} : " . ($err ?: $response)];
                    }
                    return [
                        'success' => true,
                        'latency' => $latency,
                        'message' => "Serveur local validé ({$latency} ms) · Modèle : {$model}"
                    ];

                case 'openai':
                    $key = !empty($params['api_key']) ? (string)$params['api_key'] : (string)$this->getUserConfig('openai_api_key', '');
                    $model = !empty($params['model']) ? (string)$params['model'] : (string)$this->getUserConfig('openai_model', 'gpt-4o-mini');
                    $endpoint = !empty($params['api_url']) ? (string)$params['api_url'] : (string)$this->getUserConfig('openai_api_url', 'https://api.openai.com/v1/chat/completions');

                    if (empty($key)) {
                        return ['success' => false, 'error' => 'Clé API OpenAI non renseignée'];
                    }

                    $ch = curl_init($endpoint);
                    curl_setopt_array($ch, [
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => json_encode([
                            'model'      => $model,
                            'messages'   => [['role' => 'user', 'content' => 'ping']],
                            'max_tokens' => 5
                        ]),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER     => [
                            'Content-Type: application/json',
                            'Authorization: Bearer ' . $key
                        ],
                        CURLOPT_TIMEOUT        => 15,
                        CURLOPT_CONNECTTIMEOUT => 6,
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $err = curl_error($ch);
                    curl_close($ch);
                    $latency = round((microtime(true) - $start) * 1000);

                    if ($err || $httpCode !== 200) {
                        return ['success' => false, 'error' => "OpenAI HTTP {$httpCode} : " . ($err ?: $response)];
                    }
                    return [
                        'success' => true,
                        'latency' => $latency,
                        'message' => "Connexion OpenAI validée ({$latency} ms) · Modèle : {$model}"
                    ];

                case 'deepl':
                    $key = !empty($params['api_key']) ? (string)$params['api_key'] : (string)$this->getUserConfig('deepl_api_key', '');
                    $type = !empty($params['api_type']) ? (string)$params['api_type'] : (string)$this->getUserConfig('deepl_api_type', 'free');

                    if (empty($key)) {
                        return ['success' => false, 'error' => 'Clé API DeepL non renseignée'];
                    }

                    $domain = ($type === 'free') ? 'api-free.deepl.com' : 'api.deepl.com';
                    $ch = curl_init("https://{$domain}/v2/usage");
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER     => ['Authorization: DeepL-Auth-Key ' . $key],
                        CURLOPT_TIMEOUT        => 15,
                        CURLOPT_CONNECTTIMEOUT => 6,
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $err = curl_error($ch);
                    curl_close($ch);
                    $latency = round((microtime(true) - $start) * 1000);

                    if ($err || $httpCode !== 200) {
                        return ['success' => false, 'error' => "DeepL HTTP {$httpCode} : " . ($err ?: $response)];
                    }

                    $json = json_decode($response, true);
                    $msg = "Connexion DeepL ({$type}) validée ({$latency} ms)";
                    if (isset($json['character_count'], $json['character_limit'])) {
                        $used = number_format($json['character_count'], 0, ',', ' ');
                        $limit = number_format($json['character_limit'], 0, ',', ' ');
                        $msg .= " · Quota consommé : {$used} / {$limit} caractères";
                    }

                    return ['success' => true, 'latency' => $latency, 'message' => $msg];

                default:
                    return ['success' => false, 'error' => "Fournisseur '{$provider}' non supporté pour le test"];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Exception test : ' . $e->getMessage()];
        }
    }

    // === GESTION DE L'AFFICHAGE BILINGUE ET SPLIT-VIEW ===

    public function formatBilingualTitle(string $transTitle, string $origTitle): string {
        $targetLang = $this->getUserConfig('target_lang', 'fr');
        if (strtolower($targetLang) !== 'fr' || trim($transTitle) === trim($origTitle)) {
            return $transTitle;
        }
        return $transTitle . ' | ' . $origTitle;
    }

    public function formatBilingualContent(string $transContent, string $origContent): string {
        $targetLang = $this->getUserConfig('target_lang', 'fr');
        if (strtolower($targetLang) !== 'fr') {
            return $transContent;
        }

        $bilingualHtml  = '<div class="aitranslator-split-wrapper">';
        $bilingualHtml .= '<div class="aitranslator-translated-content">' . $transContent . '</div>';
        $bilingualHtml .= '<hr class="aitranslator-divider" style="margin: 25px 0 15px 0; border: none; border-top: 1px dashed #888; opacity: 0.5;" />';
        $bilingualHtml .= '<details class="aitranslator-original-box" style="margin-top: 10px; padding: 12px; background: rgba(127,127,127,0.08); border-radius: 6px;">';
        $bilingualHtml .= '<summary style="cursor: pointer; font-weight: bold; margin-bottom: 8px;">📖 ' . htmlspecialchars(_t('ext.aitranslator.toolbar.original_title')) . '</summary>';
        $bilingualHtml .= '<div class="aitranslator-original-content" style="opacity: 0.9;">' . $origContent . '</div>';
        $bilingualHtml .= '</details>';
        $bilingualHtml .= '</div>';

        return $bilingualHtml;
    }

    // === GESTIONNAIRE D'ACTIONS DU PANNEAU DE CONFIGURATION ===

    public function handleConfigureAction(): void {
        $this->loadTranslates();

        if (Minz_Request::isPost()) {

            if (isset($_POST['action_export_config'])) {
                $exportData = $this->exportConfigurationData();
                $filename = 'aitranslator-config-' . date('Y-m-d-His') . '.json';
                header('Content-Type: application/json; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit();
            }

            if (isset($_POST['action_export_db'])) {
                try {
                    $this->getDatabase()->exec('PRAGMA wal_checkpoint(TRUNCATE);');
                } catch (\Throwable $e) {}
                $dbPath = $this->getDatabasePath();
                if (file_exists($dbPath)) {
                    $filename = 'aitranslator-database-' . date('Y-m-d-His') . '.db';
                    header('Content-Type: application/x-sqlite3');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    header('Content-Length: ' . filesize($dbPath));
                    readfile($dbPath);
                    exit();
                }
            }

            if (isset($_POST['action_import_config']) && isset($_FILES['config_json_file'])) {
                $file = $_FILES['config_json_file']['tmp_name'] ?? '';
                if ($file && is_uploaded_file($file)) {
                    $jsonContent = file_get_contents($file);
                    $imported = json_decode($jsonContent, true);
                    if (is_array($imported)) {
                        $this->importConfigurationData($imported);
                    }
                }
                return;
            }

            if (isset($_POST['action_import_db']) && isset($_FILES['db_file'])) {
                $file = $_FILES['db_file']['tmp_name'] ?? '';
                if ($file) {
                    $this->importDatabaseFile($file);
                }
                return;
            }

            if (isset($_POST['action_start_preload_bg'])) {
                $limit = max(1, min(500, (int)Minz_Request::paramString('preload_limit', '50')));
                $scriptPath = __DIR__ . '/batch_translate_history.php';
                $user = FreshRSS_Context::$user;
                if (file_exists($scriptPath)) {
                    $cmd = sprintf('php %s --user %s --limit %d > /dev/null 2>&1 &', escapeshellarg($scriptPath), escapeshellarg($user), $limit);
                    @exec($cmd);
                    $this->logActivity('INFO', "Lancement préchargement arrière-plan ({$limit} articles)");
                }
                return;
            }

            if (isset($_POST['action_set_feed_rule'])) {
                $feedId = (int)Minz_Request::paramString('rule_feed_id', '0');
                $rule = Minz_Request::paramString('rule_type', 'default');
                $lang = Minz_Request::paramString('rule_target_lang', 'fr');
                if ($feedId > 0) {
                    if ($rule === 'default') {
                        $stmt = $this->getDatabase()->prepare('DELETE FROM feed_rules WHERE feed_id = :id');
                        $stmt->execute([':id' => $feedId]);
                    } else {
                        AITranslator_TagClassifier::setFeedRule($feedId, $rule, $lang);
                    }
                    $this->logActivity('CONFIG', "Règle flux #{$feedId} mise à jour : {$rule} ({$lang})");
                }
                return;
            }

            if (isset($_POST['action_glossary_manage'])) {
                $subAction = Minz_Request::paramString('glossary_subaction', 'add');
                if ($subAction === 'add') {
                    $src = trim(Minz_Request::paramString('glossary_source', ''));
                    $tgt = trim(Minz_Request::paramString('glossary_target', ''));
                    $case = Minz_Request::paramBoolean('glossary_case') ? 1 : 0;
                    if ($src !== '' && $tgt !== '') {
                        $stmt = $this->getDatabase()->prepare('INSERT OR REPLACE INTO glossary (source_term, target_term, case_sensitive) VALUES (:s, :t, :c)');
                        $stmt->execute([':s' => $src, ':t' => $tgt, ':c' => $case]);
                        $this->logActivity('GLOSSARY', "Ajout terme glossaire : '{$src}' -> '{$tgt}'");
                    }
                } elseif ($subAction === 'delete') {
                    $id = (int)Minz_Request::paramString('glossary_id', '0');
                    if ($id > 0) {
                        $stmt = $this->getDatabase()->prepare('DELETE FROM glossary WHERE id = :id');
                        $stmt->execute([':id' => $id]);
                    }
                }
                return;
            }

            if (isset($_POST['action_clear_cache'])) {
                $this->clearCache();
                $this->logActivity('CONFIG', 'Cache des traductions purgé par l’utilisateur');
                return;
            }

            if (isset($_POST['action_clear_logs'])) {
                $this->clearLogs();
                return;
            }

            $this->setUserConfigurationValue('target_lang', Minz_Request::paramString('target_lang', 'fr'));
            $this->setUserConfigurationValue('pretranslate_enabled', Minz_Request::paramBoolean('pretranslate_enabled'));
            $this->setUserConfigurationValue('provider', Minz_Request::paramString('provider', 'gemini'));
            $this->setUserConfigurationValue('fallback_provider', Minz_Request::paramString('fallback_provider', 'mistral'));
            $this->setUserConfigurationValue('tldr_provider', Minz_Request::paramString('tldr_provider', 'auto'));

            // Microsoft Azure Translator
            $this->setUserConfigurationValue('azure_api_key', Minz_Request::paramString('azure_api_key', ''));
            $this->setUserConfigurationValue('azure_region', Minz_Request::paramString('azure_region', 'global'));

            // Meta NLLB-200
            $this->setUserConfigurationValue('nllb_api_url', Minz_Request::paramString('nllb_api_url', 'http://localhost:8000'));

            // Gemini
            $this->setUserConfigurationValue('gemini_api_key', Minz_Request::paramString('gemini_api_key', ''));
            $this->setUserConfigurationValue('gemini_model', Minz_Request::paramString('gemini_model', 'gemini-3.5-flash-lite'));

            // Mistral AI
            $this->setUserConfigurationValue('mistral_api_key', Minz_Request::paramString('mistral_api_key', ''));
            $this->setUserConfigurationValue('mistral_model', Minz_Request::paramString('mistral_model', 'mistral-small-latest'));

            // Ollama / llama.cpp
            $this->setUserConfigurationValue('ollama_api_url', Minz_Request::paramString('ollama_api_url', 'http://localhost:11434'));
            $this->setUserConfigurationValue('ollama_model', Minz_Request::paramString('ollama_model', 'llama3.2:latest'));

            // OpenAI
            $this->setUserConfigurationValue('openai_api_key', Minz_Request::paramString('openai_api_key', ''));
            $this->setUserConfigurationValue('openai_model', Minz_Request::paramString('openai_model', 'gpt-4o-mini'));
            $this->setUserConfigurationValue('openai_api_url', Minz_Request::paramString('openai_api_url', 'https://api.openai.com/v1/chat/completions'));

            // LibreTranslate
            $this->setUserConfigurationValue('libretranslate_api_url', Minz_Request::paramString('libretranslate_api_url', 'http://localhost:5000'));
            $this->setUserConfigurationValue('libretranslate_api_key', Minz_Request::paramString('libretranslate_api_key', ''));

            // DeepL
            $this->setUserConfigurationValue('deepl_api_key', Minz_Request::paramString('deepl_api_key', ''));
            $this->setUserConfigurationValue('deepl_api_type', Minz_Request::paramString('deepl_api_type', 'free'));

            $this->logActivity('CONFIG', 'Identifiants et rôles enregistrés avec succès');
        }
    }

    // === HOOKS ET PIPELINE ===

    public function hookEntryBeforeInsert($entry) {
        if (!$entry) return $entry;

        $pretranslate = (bool)$this->getUserConfig('pretranslate_enabled', false);
        if (!$pretranslate) return $entry;

        try {
            $feedId = $entry->feedId();
            $rule = AITranslator_TagClassifier::getFeedRule($feedId);
            if ($rule === 'ignore') return $entry;

            $targetLang = $this->getUserConfig('target_lang', 'fr');
            $evalResult = AITranslator_TagClassifier::evaluateArticle($entry, $targetLang, $this);

            if ($evalResult === 'same_lang') return $entry;

            if ($rule === 'translate' || $evalResult === 'foreign_confirmed') {
                $title = $entry->title();
                $content = $entry->content();
                $hash = AITranslator_TagClassifier::getCanonicalHash($title, $content, $targetLang, 'translate');

                if (!$this->getCachedTranslation($hash)) {
                    $res = $this->executeTranslation($title, $content, $targetLang);
                    if (!empty($res['success'])) {
                        $this->setCachedTranslation($hash, $res['title'], $res['content'], $res['provider'] ?? 'AI', $targetLang);
                        $this->logActivity('AUTO_INGEST', "Article #{$entry->id()} traduit avec succès via " . ($res['provider'] ?? 'AI'));
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logActivity('ERROR', 'Erreur hook ingestion : ' . $e->getMessage());
        }

        return $entry;
    }

    public function hookEntryBeforeDisplay($entry) {
        if (!$entry) return $entry;

        try {
            $targetLang = $this->getUserConfig('target_lang', 'fr');
            $evalResult = AITranslator_TagClassifier::evaluateArticle($entry, $targetLang, $this);

            if ($evalResult === 'same_lang') {
                AITranslator_ArticleUiRenderer::attachToolbar($entry, false, true);
                return $entry;
            }

            $title = $entry->title();
            $content = $entry->content();
            $hash = AITranslator_TagClassifier::getCanonicalHash($title, $content, $targetLang, 'translate');
            $cached = $this->getCachedTranslation($hash);

            if ($cached) {
                $entry->_title($this->formatBilingualTitle($cached['title'], $entry->title()));

                $formattedContent = AITranslator_ArticleUiRenderer::formatTranslatedContent(
                    $cached['content'],
                    $content,
                    $cached['provider'],
                    (string)$entry->id()
                );

                $entry->_content($this->formatBilingualContent($formattedContent, $content));
                AITranslator_ArticleUiRenderer::attachToolbar($entry, true, false);
                return $entry;
            }

            AITranslator_ArticleUiRenderer::attachToolbar($entry, false, false);
            return $entry;

        } catch (\Throwable $e) {
            $this->logActivity('ERROR', 'Erreur hook affichage : ' . $e->getMessage());
            return $entry;
        }
    }

    public function executeSummary(string $text, string $targetLang = 'fr', ?string $preferredProvider = null): array {
        $startTime = microtime(true);
        $choice = $preferredProvider ?: $this->getUserConfig('tldr_provider', 'auto');

        if ($choice === 'auto') {
            if (!empty($this->getUserConfig('gemini_api_key'))) {
                $choice = 'gemini';
            } elseif (!empty($this->getUserConfig('mistral_api_key'))) {
                $choice = 'mistral';
            } elseif (!empty($this->getUserConfig('ollama_api_url'))) {
                $choice = 'ollama';
            } elseif (!empty($this->getUserConfig('openai_api_key'))) {
                $choice = 'openai';
            } else {
                $choice = 'statistical';
            }
        }

        $res = ['success' => false];

        switch ($choice) {
            case 'gemini':
                $res = $this->summarizeGemini($text, $targetLang);
                break;
            case 'mistral':
                $res = $this->summarizeMistral($text, $targetLang);
                break;
            case 'ollama':
                $res = $this->summarizeOllama($text, $targetLang);
                break;
            case 'openai':
                $res = $this->summarizeOpenAI($text, $targetLang);
                break;
            case 'statistical':
                $res = $this->summarizeStatistical($text);
                break;
        }

        if (empty($res['success'])) {
            $this->logActivity('WARN', "Échec résumé IA ({$choice}), repli statistique : " . ($res['error'] ?? ''));
            $res = $this->summarizeStatistical($text);
        }

        $duration = round(microtime(true) - $startTime, 2);
        $this->logActivity('TLDR', "Synthèse générée via {$res['provider']} en {$duration}s");

        return $res;
    }

    private function parseAiSummaryResponse(string $rawText): ?array {
        $rawText = trim($rawText);

        $data = json_decode($rawText, true);
        if (!empty($data['points']) && is_array($data['points'])) {
            return array_slice(array_map('trim', $data['points']), 0, 3);
        }

        $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $rawText);
        $data = json_decode(trim($cleaned), true);
        if (!empty($data['points']) && is_array($data['points'])) {
            return array_slice(array_map('trim', $data['points']), 0, 3);
        }

        if (preg_match_all('/^[*\-•\d.)]+\s*(.+)$/m', $rawText, $matches)) {
            $points = array_values(array_filter(array_map('trim', $matches[1]), fn($p) => mb_strlen($p) > 15));
            if (count($points) >= 2) {
                return array_slice($points, 0, 3);
            }
        }

        return null;
    }

    private function summarizeGemini(string $text, string $targetLang): array {
        $apiKey = (string)$this->getUserConfig('gemini_api_key', '');
        $model = (string)$this->getUserConfig('gemini_model', 'gemini-3.5-flash-lite');

        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Clé API Gemini non configurée'];
        }

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $systemPrompt = "Tu es un assistant de synthèse journalistique. Analyse le texte fourni et rédige un résumé express (TL;DR) en 3 points clés concis, factuels et autonomes en {$targetLang}. "
            . "Renvoie STRICTEMENT un objet JSON valide avec la clé 'points' contenant un tableau de 3 phrases, sans démarquage markdown.";

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $systemPrompt . "\n\nTexte à résumer :\n" . $text]]]
            ],
            'generationConfig' => [
                'temperature'      => 0.2,
                'responseMimeType' => 'application/json'
            ]
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            return ['success' => false, 'error' => "Gemini HTTP {$httpCode} : " . ($curlError ?: $response)];
        }

        $json = json_decode($response, true);
        $raw = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $points = $this->parseAiSummaryResponse($raw);

        if ($points) {
            return ['success' => true, 'points' => $points, 'provider' => "Gemini ({$model})"];
        }

        return ['success' => false, 'error' => 'Réponse Gemini non décodable'];
    }

    private function summarizeMistral(string $text, string $targetLang): array {
        $apiKey = (string)$this->getUserConfig('mistral_api_key', '');
        $model = (string)$this->getUserConfig('mistral_model', 'mistral-small-latest');

        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Clé API Mistral non configurée'];
        }

        $endpoint = 'https://api.mistral.ai/v1/chat/completions';

        $systemPrompt = "Tu es un assistant de synthèse journalistique. Analyse le texte et rédige un résumé express en 3 points clés rédigés en {$targetLang}. "
            . "Réponds STRICTEMENT avec un JSON ayant pour clé 'points' contenant un tableau de 3 phrases synthétiques.";

        $payload = [
            'model'           => $model,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $text]
            ],
            'temperature'     => 0.2
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            return ['success' => false, 'error' => "Mistral HTTP {$httpCode} : " . ($curlError ?: $response)];
        }

        $json = json_decode($response, true);
        $rawText = $json['choices'][0]['message']['content'] ?? '';
        $points = $this->parseAiSummaryResponse($rawText);

        if ($points) {
            return ['success' => true, 'points' => $points, 'provider' => "Mistral ({$model})"];
        }

        return ['success' => false, 'error' => 'Réponse Mistral non décodable'];
    }

    private function summarizeOllama(string $text, string $targetLang): array {
        $rawUrl = (string)$this->getUserConfig('ollama_api_url', 'http://localhost:11434');
        $model = (string)$this->getUserConfig('ollama_model', 'llama3.2:latest');

        $baseUrl = rtrim($rawUrl, '/');
        if (strpos($baseUrl, '/chat/completions') === false) {
            $endpoint = (substr($baseUrl, -3) === '/v1') ? $baseUrl . '/chat/completions' : $baseUrl . '/v1/chat/completions';
        } else {
            $endpoint = $baseUrl;
        }

        $systemPrompt = "Tu es un assistant de veille. Analyse le texte et produis une synthèse en 3 points clés clairs et concis en {$targetLang}. "
            . "Renvoie EXCLUSIVEMENT un JSON valide au format: {\"points\": [\"point 1\", \"point 2\", \"point 3\"]}.";

        $payload = [
            'model'       => $model,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $text]
            ],
            'temperature' => 0.2
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_CONNECTTIMEOUT => 6,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            return ['success' => false, 'error' => "Ollama/llama.cpp HTTP {$httpCode} : " . ($curlError ?: $response)];
        }

        $json = json_decode($response, true);
        $rawText = $json['choices'][0]['message']['content'] ?? '';
        $points = $this->parseAiSummaryResponse($rawText);

        if ($points) {
            return ['success' => true, 'points' => $points, 'provider' => "Local LLM ({$model})"];
        }

        return ['success' => false, 'error' => 'Format de réponse Local LLM invalide'];
    }

    private function summarizeOpenAI(string $text, string $targetLang): array {
        $apiKey = (string)$this->getUserConfig('openai_api_key', '');
        $model = (string)$this->getUserConfig('openai_model', 'gpt-4o-mini');
        $endpoint = (string)$this->getUserConfig('openai_api_url', 'https://api.openai.com/v1/chat/completions');

        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Clé API OpenAI non configurée'];
        }

        $systemPrompt = "Tu es un assistant de synthèse journalistique. Analyse le texte et rédige un résumé en 3 points en {$targetLang}. "
            . "Réponds strictement avec un JSON ayant pour clé 'points'.";

        $payload = [
            'model'           => $model,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $text]
            ],
            'temperature'     => 0.2
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            return ['success' => false, 'error' => "OpenAI HTTP {$httpCode} : " . ($curlError ?: $response)];
        }

        $json = json_decode($response, true);
        $rawText = $json['choices'][0]['message']['content'] ?? '';
        $points = $this->parseAiSummaryResponse($rawText);

        if ($points) {
            return ['success' => true, 'points' => $points, 'provider' => "OpenAI ({$model})"];
        }

        return ['success' => false, 'error' => 'Réponse OpenAI non conforme'];
    }

    private function summarizeStatistical(string $text): array {
        $sentences = preg_split('/(?<=[.?!])\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $summaryPoints = [];

        foreach ($sentences as $s) {
            $s = trim($s);
            if (mb_strlen($s) > 40 && !preg_match('/^(cliquez|abonnez|source|lire aussi|partager|retrouvez)/i', $s)) {
                $summaryPoints[] = $s;
                if (count($summaryPoints) >= 3) break;
            }
        }

        if (empty($summaryPoints)) {
            $summaryPoints[] = mb_substr($text, 0, 250) . '...';
        }

        return [
            'success'  => true,
            'points'   => $summaryPoints,
            'provider' => 'Analyseur local hors-ligne'
        ];
    }

    // === PIPELINE DE TRADUCTION ===

    public function executeTranslation(string $title, string $content, string $targetLang = 'fr', ?string $preferredProvider = null): array {
        $startTime = microtime(true);
        $primary = $preferredProvider ?: $this->getUserConfig('provider', 'gemini');
        $fallback = $this->getUserConfig('fallback_provider', 'mistral');

        $stash = [];
        $protectedContent = $this->protectCodeBlocks($content, $stash);

        $res = $this->dispatchProvider($primary, $title, $protectedContent, $targetLang);

        if (empty($res['success']) && !empty($fallback) && $fallback !== $primary) {
            $this->logActivity('WARN', "Échec du moteur principal ({$primary}), bascule sur fallback ({$fallback}) : " . ($res['error'] ?? ''));
            $res = $this->dispatchProvider($fallback, $title, $protectedContent, $targetLang);
        }

        if (!empty($res['success'])) {
            $res['content'] = $this->restoreCodeBlocks($res['content'], $stash);
            $res['content'] = $this->applyGlossary($res['content']);
            $res['title']   = $this->applyGlossary($res['title']);
            $duration = round(microtime(true) - $startTime, 2);
            $this->logActivity('SUCCESS', "Traduction réussie via {$res['provider']} en {$duration}s");
        } else {
            $this->logActivity('ERROR', "Échec global de la traduction : " . ($res['error'] ?? 'Inconnu'));
        }

        return $res;
    }

    public function applyGlossary(string $text): string {
        try {
            $stmt = $this->getDatabase()->query('SELECT source_term, target_term, case_sensitive FROM glossary');
            $terms = $stmt->fetchAll();
            foreach ($terms as $t) {
                $src = preg_quote($t['source_term'], '/');
                $tgt = $t['target_term'];
                $modifier = $t['case_sensitive'] ? 'u' : 'iu';
                $text = preg_replace('/\b' . $src . '\b/' . $modifier, $tgt, $text);
            }
        } catch (\Throwable $e) {}
        return $text;
    }

    public function protectCodeBlocks(string $content, array &$stash): string {
        $idx = 0;
        return preg_replace_callback('/<(pre|code)\b[^>]*>.*?<\/\1>/is', function ($matches) use (&$stash, &$idx) {
            $placeholder = "___AITRANS_STASH_{$idx}___";
            $stash[$placeholder] = $matches[0];
            $idx++;
            return $placeholder;
        }, $content);
    }

    public function restoreCodeBlocks(string $content, array $stash): string {
        foreach ($stash as $ph => $originalTag) {
            $content = str_replace($ph, $originalTag, $content);
        }
        return $content;
    }

    private function dispatchProvider(string $provider, string $title, string $content, string $targetLang): array {
        switch ($provider) {
            case 'azure':
                return $this->translateAzure($title, $content, $targetLang);
            case 'nllb':
                return $this->translateNllb($title, $content, $targetLang);
            case 'libretranslate':
                return $this->translateLibreTranslate($title, $content, $targetLang);
            case 'gemini':
                return $this->translateGemini($title, $content, $targetLang);
            case 'mistral':
                return $this->translateMistral($title, $content, $targetLang);
            case 'ollama':
                return $this->translateOllama($title, $content, $targetLang);
            case 'openai':
                return $this->translateOpenAI($title, $content, $targetLang);
            case 'deepl':
                return $this->translateDeepL($title, $content, $targetLang);
            default:
                return ['success' => false, 'error' => "Fournisseur inconnu : {$provider}"];
        }
    }

    // === FOURNISSEUR : MICROSOFT AZURE TRANSLATOR ===

    private function translateAzure(string $title, string $content, string $targetLang): array {
        $key = (string)$this->getUserConfig('azure_api_key', '');
        $region = (string)$this->getUserConfig('azure_region', 'global');

        if (empty($key)) {
            return ['success' => false, 'error' => 'Clé API Azure non renseignée'];
        }

        $endpoint = "https://api.cognitive.microsofttranslator.com/translate?api-version=3.0&to=" . urlencode($targetLang) . "&textType=html";
        $payload = [
            ['Text' => $title],
            ['Text' => $content],
        ];

        $headers = [
            'Content-Type: application/json; charset=UTF-8',
            'Ocp-Apim-Subscription-Key: ' . $key,
        ];
        if (!empty($region)) {
            $headers[] = 'Ocp-Apim-Subscription-Region: ' . $region;
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            return ['success' => false, 'error' => "Azure HTTP {$httpCode} : " . ($curlError ?: $response)];
        }

        $json = json_decode($response, true);
        if (!empty($json[0]['translations'][0]['text']) && isset($json[1]['translations'][0]['text'])) {
            return [
                'success'  => true,
                'title'    => $json[0]['translations'][0]['text'],
                'content'  => $json[1]['translations'][0]['text'],
                'provider' => 'Azure Translator',
            ];
        }

        return ['success' => false, 'error' => 'Format de réponse Azure invalide'];
    }

    // === FOURNISSEUR : META NLLB-200 (AUTO-HÉBERGÉ) ===

    public function isoToFlores(string $iso): string {
        $map = [
            'fr' => 'fra_Latn',
            'en' => 'eng_Latn',
            'es' => 'spa_Latn',
            'de' => 'deu_Latn',
            'it' => 'ita_Latn',
            'pt' => 'por_Latn',
            'ru' => 'rus_Cyrl',
            'zh' => 'zho_Hans',
            'zh-cn' => 'zho_Hans',
            'ja' => 'jpn_Jpan',
            'ar' => 'ara_Arab',
            'nl' => 'nld_Latn',
            'pl' => 'pol_Latn',
            'uk' => 'ukr_Cyrl',
            'tr' => 'tur_Latn',
            'ko' => 'kor_Hang',
            'sv' => 'swe_Latn',
            'da' => 'dan_Latn',
            'fi' => 'fin_Latn',
            'no' => 'nob_Latn',
            'el' => 'ell_Grek',
            'cs' => 'ces_Latn',
            'ro' => 'ron_Latn',
            'hu' => 'hun_Latn',
        ];
        $code = strtolower(trim($iso));
        return $map[$code] ?? (strpos($code, '_') !== false ? $iso : 'fra_Latn');
    }

    private function translateNllb(string $title, string $content, string $targetLang): array {
        $rawUrl = (string)$this->getUserConfig('nllb_api_url', 'http://localhost:8000');
        $baseUrl = rtrim($rawUrl, '/');
        $endpoint = (strpos($baseUrl, '/translate') === false) ? $baseUrl . '/translate' : $baseUrl;

        $targetFlores = $this->isoToFlores($targetLang);

        $callSingle = function (string $text) use ($endpoint, $targetFlores): array {
            $payload = [
                'text'        => $text,
                'source'      => 'auto',
                'target'      => $targetFlores,
                'source_lang' => 'auto',
                'target_lang' => $targetFlores,
            ];

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError || $httpCode !== 200) {
                return ['success' => false, 'error' => "NLLB HTTP {$httpCode} : " . ($curlError ?: $response)];
            }

            $json = json_decode($response, true);
            $resText = $json['translatedText'] ?? $json['result'] ?? $json['translation'] ?? null;
            if ($resText !== null) {
                return ['success' => true, 'text' => $resText];
            }
            return ['success' => false, 'error' => 'Réponse NLLB non reconnue'];
        };

        $transTitle = $callSingle($title);
        if (!$transTitle['success']) {
            return $transTitle;
        }

        $transContent = $callSingle($content);
        if (!$transContent['success']) {
            return $transContent;
        }

        return [
            'success'  => true,
            'title'    => $transTitle['text'],
            'content'  => $transContent['text'],
            'provider' => 'Meta NLLB-200',
        ];
    }

    // === FOURNISSEUR : LIBRETRANSLATE ===

    private function translateLibreTranslate(string $title, string $content, string $targetLang): array {
        $url = rtrim((string)($this->getUserConfig('libretranslate_api_url') ?: $this->getUserConfig('libretranslate_url', 'http://localhost:5000')), '/');
        $key = (string)$this->getUserConfig('libretranslate_api_key', '');

        $transTitle = $this->callLibreTranslateApi($url . '/translate', $title, $targetLang, 'text', $key);
        if (!$transTitle['success']) {
            return $transTitle;
        }

        $transContent = $this->callLibreTranslateApi($url . '/translate', $content, $targetLang, 'html', $key);
        if (!$transContent['success']) {
            return $transContent;
        }

        return [
            'success'  => true,
            'title'    => $transTitle['text'],
            'content'  => $transContent['text'],
            'provider' => 'LibreTranslate',
        ];
    }

    private function callLibreTranslateApi(string $endpoint, string $text, string $targetLang, string $format, string $apiKey): array {
        $payload = [
            'q'      => $text,
            'source' => 'auto',
            'target' => $targetLang,
            'format' => $format,
        ];
        if (!empty($apiKey)) {
            $payload['api_key'] = $apiKey;
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => "cURL LibreTranslate : {$curlError}"];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "LibreTranslate HTTP {$httpCode} : {$response}"];
        }

        $json = json_decode($response, true);
        if (isset($json['translatedText'])) {
            return ['success' => true, 'text' => $json['translatedText']];
        }

        return ['success' => false, 'error' => $json['error'] ?? 'Réponse LibreTranslate inattendue'];
    }

    // === FOURNISSEUR : GOOGLE GEMINI ===

    private function translateGemini(string $title, string $content, string $targetLang): array {
        $apiKey = (string)$this->getUserConfig('gemini_api_key', '');
        $model = (string)$this->getUserConfig('gemini_model', 'gemini-3.5-flash-lite');

        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Clé API Gemini manquante'];
        }

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $systemPrompt = "Tu es un traducteur professionnel. Traduis le titre et le contenu HTML fournis vers la langue : {$targetLang}. "
            . "Conserve strictement toutes les balises HTML, les liens, les attributs et la mise en forme. "
            . "Renvoie UNIQUEMENT un objet JSON valide avec les clés 'title' et 'content', sans démarquage markdown ```json.";

        $userPrompt = json_encode(['title' => $title, 'content' => $content], JSON_UNESCAPED_UNICODE);

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $systemPrompt . "\n\n" . $userPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature'      => 0.2,
                'responseMimeType' => 'application/json'
            ]
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            return ['success' => false, 'error' => "Gemini HTTP {$httpCode} : " . ($curlError ?: $response)];
        }

        $json = json_decode($response, true);
        $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $data = json_decode($rawText, true);

        if (isset($data['title'], $data['content'])) {
            return [
                'success'  => true,
                'title'    => $data['title'],
                'content'  => $data['content'],
                'provider' => "Gemini ({$model})",
            ];
        }

        return ['success' => false, 'error' => 'Format de réponse Gemini invalide'];
    }

    // === FOURNISSEUR : MISTRAL AI ===

    private function translateMistral(string $title, string $content, string $targetLang): array {
        $apiKey = (string)$this->getUserConfig('mistral_api_key', '');
        $model = (string)$this->getUserConfig('mistral_model', 'mistral-small-latest');

        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Clé API Mistral manquante'];
        }

        $endpoint = '[https://api.mistral.ai/v1/chat/completions](https://api.mistral.ai/v1/chat/completions)';

        $systemPrompt = "Tu es un traducteur expert. Traduis le titre et le contenu HTML en langue cible : {$targetLang}. "
            . "Préserve fidèlement l'intégralité du balisage HTML. Réponds exclusivement avec un JSON ayant pour clés 'title' et 'content'.";

        $userPrompt = json_encode(['title' => $title, 'content' => $content], JSON_UNESCAPED_UNICODE);

        $payload = [
            'model'           => $model,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature'     => 0.2
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            return ['success' => false, 'error' => "Mistral HTTP {$httpCode} : " . ($curlError ?: $response)];
        }

        $json = json_decode($response, true);
        $rawText = $json['choices'][0]['message']['content'] ?? '';
        $data = json_decode($rawText, true);

        if (isset($data['title'], $data['content'])) {
            return [
                'success'  => true,
                'title'    => $data['title'],
                'content'  => $data['content'],
                'provider' => "Mistral ({$model})",
            ];
        }

        return ['success' => false, 'error' => 'Réponse Mistral invalide'];
    }

    // === FOURNISSEUR : OLLAMA / LLM LOCAL ===

    private function translateOllama(string $title, string $content, string $targetLang): array {
        $rawUrl = (string)$this->getUserConfig('ollama_api_url', 'http://localhost:11434');
        $model = (string)$this->getUserConfig('ollama_model', 'llama3.2:latest');

        $baseUrl = rtrim($rawUrl, '/');
        if (strpos($baseUrl, '/chat/completions') === false) {
            $endpoint = (substr($baseUrl, -3) === '/v1') ? $baseUrl . '/chat/completions' : $baseUrl . '/v1/chat/completions';
        } else {
            $endpoint = $baseUrl;
        }

        $systemPrompt = "Tu es un traducteur bilingue rigoureux. Traduis le titre et le contenu HTML fournis vers la langue : {$targetLang}. "
            . "Conserve strictement l'ensemble des balises HTML, attributs, images et liens sans altération. "
            . "Renvoie STRICTEMENT un JSON valide au format: {\"title\": \"...\", \"content\": \"...\"}.";

        $userPrompt = json_encode(['title' => $title, 'content' => $content], JSON_UNESCAPED_UNICODE);

        $payload = [
            'model'           => $model,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature'     => 0.2
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            return ['success' => false, 'error' => "Ollama HTTP {$httpCode} : " . ($curlError ?: $response)];
        }

        $json = json_decode($response, true);
        $rawText = $json['choices'][0]['message']['content'] ?? '';
        $data = json_decode($rawText, true);

        if (!$data) {
            $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
            $data = json_decode(trim($cleaned), true);
        }

        if (isset($data['title'], $data['content'])) {
            return [
                'success'  => true,
                'title'    => $data['title'],
                'content'  => $data['content'],
                'provider' => "Local LLM ({$model})",
            ];
        }

        return ['success' => false, 'error' => 'Format de réponse Local LLM invalide pour la traduction'];
    }

    // === FOURNISSEUR : OPENAI ===

    private function translateOpenAI(string $title, string $content, string $targetLang): array {
        $apiKey = (string)$this->getUserConfig('openai_api_key', '');
        $model = (string)$this->getUserConfig('openai_model', 'gpt-4o-mini');
        $endpoint = (string)$this->getUserConfig('openai_api_url', '[https://api.openai.com/v1/chat/completions](https://api.openai.com/v1/chat/completions)');

        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Clé API OpenAI manquante'];
        }

        $systemPrompt = "Tu es un traducteur professionnel. Traduis le titre et le contenu HTML vers {$targetLang}. "
            . "Conserve scrupuleusement la structure HTML. Réponds au format JSON strict avec les champs 'title' et 'content'.";

        $userPayload = json_encode(['title' => $title, 'content' => $content], JSON_UNESCAPED_UNICODE);

        $payload = [
            'model'           => $model,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPayload]
            ],
            'temperature'     => 0.2
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            return ['success' => false, 'error' => "OpenAI HTTP {$httpCode} : " . ($curlError ?: $response)];
        }

        $json = json_decode($response, true);
        $rawText = $json['choices'][0]['message']['content'] ?? '';
        $data = json_decode($rawText, true);

        if (isset($data['title'], $data['content'])) {
            return [
                'success'  => true,
                'title'    => $data['title'],
                'content'  => $data['content'],
                'provider' => "OpenAI ({$model})",
            ];
        }

        return ['success' => false, 'error' => 'Réponse OpenAI non conforme'];
    }

    // === FOURNISSEUR : DEEPL ===

    private function translateDeepL(string $title, string $content, string $targetLang): array {
        $apiKey = (string)$this->getUserConfig('deepl_api_key', '');
        $type = (string)$this->getUserConfig('deepl_api_type', 'free');

        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Clé API DeepL manquante'];
        }

        $domain = ($type === 'free') ? 'api-free.deepl.com' : 'api.deepl.com';
        $endpoint = "https://{$domain}/v2/translate";

        $postData = [
            'text'         => [$title, $content],
            'target_lang'  => strtoupper($targetLang),
            'tag_handling' => 'html',
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: DeepL-Auth-Key ' . $apiKey
            ],
            CURLOPT_TIMEOUT        => 45,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            return ['success' => false, 'error' => "DeepL HTTP {$httpCode} : " . ($curlError ?: $response)];
        }

        $json = json_decode($response, true);
        if (!empty($json['translations'][0]['text']) && !empty($json['translations'][1]['text'])) {
            return [
                'success'  => true,
                'title'    => $json['translations'][0]['text'],
                'content'  => $json['translations'][1]['text'],
                'provider' => 'DeepL',
            ];
        }

        return ['success' => false, 'error' => 'Réponse DeepL incomplète'];
    }
}