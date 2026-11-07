<?php

class AITranslator_Provider_LibreTranslate {

    public static function translate(string $url, string $apiKey, string $text, string $targetLang, string $sourceLang = 'auto', string $format = 'html'): array {
        $endpoint = rtrim($url, '/') . '/translate';

        $payload = [
            'q'      => $text,
            'source' => $sourceLang,
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
            return ['success' => false, 'error' => "cURL LibreTranslate: {$curlError}"];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "LibreTranslate HTTP {$httpCode}: {$response}"];
        }

        $json = json_decode($response, true);
        if (isset($json['translatedText'])) {
            return ['success' => true, 'text' => $json['translatedText']];
        }

        return ['success' => false, 'error' => $json['error'] ?? 'Réponse LibreTranslate invalide'];
    }
}