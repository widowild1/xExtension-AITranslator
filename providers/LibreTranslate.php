<?php

class AITranslator_Provider_LibreTranslate {

    /**
     * Cache mémoire par URL d'instance pour les langues sources supportées (/languages).
     * @var array<string, array<string, array<int, string>>>
     */
    private static array $languagesCache = [];

    public static function translate(string $url, string $apiKey, string $text, string $targetLang, string $sourceLang = 'auto', string $format = 'html'): array {
        if (trim(strip_tags($text)) === '') {
            return ['success' => true, 'text' => $text];
        }

        $baseUrl = rtrim($url, '/');
        $endpoint = $baseUrl . '/translate';
        $targetBase = self::normalizeLangCode($targetLang);

        $scriptInfo = self::detectNonLatinScript($text);
        $effectiveSource = $sourceLang;

        if ($scriptInfo !== null && !in_array($targetBase, $scriptInfo['langs'], true)) {
            $supportedMap = self::getSupportedLanguages($baseUrl, $apiKey);

            if (!empty($supportedMap)) {
                $installedForScript = [];
                foreach ($scriptInfo['langs'] as $candidateLang) {
                    $targets = $supportedMap[$candidateLang] ?? [];
                    if (!empty($targets) && in_array($targetBase, $targets, true)) {
                        $installedForScript[] = $candidateLang;
                    }
                }

                if (empty($installedForScript)) {
                    $primaryCode = $scriptInfo['langs'][0];
                    return [
                        'success' => false,
                        'error'   => "Modèle LibreTranslate '{$primaryCode} -> {$targetBase}' non installé (bascule vers fournisseur de secours)",
                    ];
                }

                // Force la langue source uniquement si l'écriture est univoque (ex. grec, coréen, thaï...)
                // afin d'éviter qu'un nom propre latin ne perturbe la détection 'auto'.
                if ($sourceLang === 'auto' && count($scriptInfo['langs']) === 1) {
                    $effectiveSource = $installedForScript[0];
                }
            } elseif ($sourceLang === 'auto' && count($scriptInfo['langs']) === 1) {
                $effectiveSource = $scriptInfo['langs'][0];
            }
        }

        $payload = [
            'q'      => $text,
            'source' => $effectiveSource,
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
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => "cURL LibreTranslate: {$curlError}"];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "LibreTranslate HTTP {$httpCode}: {$response}"];
        }

        $json = json_decode($response, true);
        if (!isset($json['translatedText'])) {
            return ['success' => false, 'error' => $json['error'] ?? 'Réponse LibreTranslate invalide'];
        }

        $translatedText = (string)$json['translatedText'];

        // Garde-fou universel : rejette la sortie si l'écriture source non latine subsiste vers une langue latine
        if ($scriptInfo !== null && self::isLatinTarget($targetBase)) {
            if (preg_match($scriptInfo['pattern'], strip_tags($translatedText))) {
                $primaryCode = $scriptInfo['langs'][0];
                return [
                    'success' => false,
                    'error'   => "Sortie LibreTranslate non traduite ({$primaryCode} résiduel)",
                ];
            }
        }

        return ['success' => true, 'text' => $translatedText];
    }

    /**
     * Récupère et met en cache la matrice des langues sources/cibles de LibreTrranslate.
     *
     * @return array<string, array<int, string>>
     */
    private static function getSupportedLanguages(string $baseUrl, string $apiKey): array {
        if (isset(self::$languagesCache[$baseUrl])) {
            return self::$languagesCache[$baseUrl];
        }

        $endpoint = $baseUrl . '/languages';
        if (!empty($apiKey)) {
            $endpoint .= '?api_key=' . urlencode($apiKey);
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            self::$languagesCache[$baseUrl] = [];
            return [];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            self::$languagesCache[$baseUrl] = [];
            return [];
        }

        $map = [];
        foreach ($decoded as $item) {
            if (!empty($item['code']) && isset($item['targets']) && is_array($item['targets'])) {
                $map[strtolower((string)$item['code'])] = array_map('strtolower', $item['targets']);
            }
        }

        self::$languagesCache[$baseUrl] = $map;
        return $map;
    }

    /**
     * Détecte toutes les écritures non latines Unicode et retourne le motif ainsi que les codes langues associés.
     *
     * @return array{pattern: string, langs: array<int, string>}|null
     */
    private static function detectNonLatinScript(string $text): ?array {
        $plain = strip_tags($text);
        $scripts = [
            '/[\p{Greek}]/u'                => ['el'],
            '/[\p{Hiragana}\p{Katakana}]/u' => ['ja'],
            '/[\p{Hangul}]/u'               => ['ko'],
            '/[\p{Han}]/u'                  => ['zh', 'ja'],
            '/[\p{Cyrillic}]/u'             => ['ru', 'uk', 'bg', 'sr', 'mk', 'be', 'kk', 'ky', 'tg', 'mn'],
            '/[\p{Arabic}]/u'               => ['ar', 'fa', 'ur', 'ps', 'ku'],
            '/[\p{Hebrew}]/u'               => ['he', 'yi'],
            '/[\p{Thai}]/u'                 => ['th'],
            '/[\p{Devanagari}]/u'           => ['hi', 'mr', 'ne', 'sa'],
            '/[\p{Bengali}]/u'              => ['bn', 'as'],
            '/[\p{Georgian}]/u'             => ['ka'],
            '/[\p{Armenian}]/u'             => ['hy'],
            '/[\p{Tamil}]/u'                => ['ta'],
            '/[\p{Telugu}]/u'               => ['te'],
            '/[\p{Gujarati}]/u'             => ['gu'],
            '/[\p{Gurmukhi}]/u'             => ['pa'],
            '/[\p{Kannada}]/u'              => ['kn'],
            '/[\p{Malayalam}]/u'            => ['ml'],
            '/[\p{Sinhala}]/u'              => ['si'],
            '/[\p{Khmer}]/u'                => ['km'],
            '/[\p{Lao}]/u'                  => ['lo'],
            '/[\p{Myanmar}]/u'              => ['my'],
            '/[\p{Tibetan}]/u'              => ['bo'],
            '/[\p{Ethiopic}]/u'             => ['am', 'ti'],
        ];

        foreach ($scripts as $pattern => $langCodes) {
            if (preg_match($pattern, $plain)) {
                return ['pattern' => $pattern, 'langs' => $langCodes];
            }
        }

        return null;
    }

    private static function isLatinTarget(string $targetBase): bool {
        $nonLatinTargets = [
            'el', 'ru', 'uk', 'bg', 'sr', 'mk', 'be', 'kk', 'ky', 'tg', 'mn',
            'ja', 'ko', 'zh', 'ar', 'fa', 'ur', 'ps', 'ku', 'he', 'yi', 'th',
            'hi', 'mr', 'ne', 'sa', 'bn', 'as', 'ka', 'hy', 'ta', 'te', 'gu',
            'pa', 'kn', 'ml', 'si', 'km', 'lo', 'my', 'bo', 'am', 'ti'
        ];
        return !in_array($targetBase, $nonLatinTargets, true);
    }

    private static function normalizeLangCode(string $lang): string {
        $clean = strtolower(trim($lang));
        if (strpos($clean, '-') !== false) {
            $clean = explode('-', $clean)[0];
        } elseif (strpos($clean, '_') !== false) {
            $clean = explode('_', $clean)[0];
        }
        return $clean ?: 'fr';
    }
}
