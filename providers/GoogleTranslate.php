<?php
class AITranslator_GoogleTranslate {
    public static function translate($title, $content, $opts) {
        if (empty($opts['api_key'])) return ['success' => false, 'error' => 'Clé Google Cloud manquante.'];

        $texts = [];
        if ($opts['trans_title']) $texts[] = $title;
        if ($opts['trans_content']) $texts[] = $content;
        if (empty($texts)) return ['success' => false, 'error' => 'Aucun texte.'];

        $targetLang = $opts['lang_meta']['gcloud'] ?? $opts['target_lang'];
        $url = "https://translation.googleapis.com/language/translate/v2?key=" . urlencode($opts['api_key']);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
            CURLOPT_POSTFIELDS     => json_encode(["q" => $texts, "target" => $targetLang, "format" => "html"])
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) return ['success' => false, 'error' => "cURL ($err)"];
        if ($httpCode < 200 or $httpCode >= 300) return ['success' => false, 'error' => "GTranslate Error {$httpCode}"];

        $json = json_decode((string)$response, true);
        $decoded = ['title' => '', 'content' => '', 'tldr' => []];

        if (isset($json['data']['translations'])) {
            if ($opts['trans_title']) $decoded['title'] = $json['data']['translations'][0]['translatedText'];
            if ($opts['trans_content']) $decoded['content'] = $json['data']['translations'][count($json['data']['translations']) - 1]['translatedText'];
            return ['success' => true, 'data' => $decoded, 'used_model' => 'Google Cloud Translate'];
        }
        return ['success' => false, 'error' => 'Format GTranslate invalide.'];
    }
}
