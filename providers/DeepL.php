<?php
class AITranslator_DeepL {
    public static function translate($title, $content, $opts) {
        if (empty($opts['api_key'])) return ['success' => false, 'error' => 'Clé API DeepL manquante.'];

        $texts = [];
        if ($opts['trans_title']) $texts[] = $title;
        if ($opts['trans_content']) $texts[] = $content;
        if (empty($texts)) return ['success' => false, 'error' => 'Aucun texte.'];

        $apiUrl = ($opts['type'] === 'pro') ? 'https://api.deepl.com/v2/translate' : 'https://api-free.deepl.com/v2/translate';
        $targetLang = $opts['lang_meta']['deepl'] ?? strtoupper($opts['target_lang']);

        $data = ["text" => $texts, "target_lang" => $targetLang, "tag_handling" => "html"];
        if ($opts['protect_code']) $data["ignore_tags"] = ["pre", "code"];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: DeepL-Auth-Key {$opts['api_key']}",
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS     => json_encode($data)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) return ['success' => false, 'error' => "cURL ($err)"];
        if ($httpCode < 200 or $httpCode >= 300) return ['success' => false, 'error' => "DeepL Error {$httpCode}"];

        $json = json_decode((string)$response, true);
        $decoded = ['title' => '', 'content' => '', 'tldr' => []];

        if (isset($json['translations'])) {
            if ($opts['trans_title']) $decoded['title'] = $json['translations'][0]['text'];
            if ($opts['trans_content']) $decoded['content'] = $json['translations'][count($json['translations']) - 1]['text'];
            return ['success' => true, 'data' => $decoded, 'used_model' => 'DeepL API'];
        }
        return ['success' => false, 'error' => 'Format DeepL inattendu.'];
    }
}
