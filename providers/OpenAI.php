<?php

class AITranslator_Provider_OpenAI {

    public static function translate(string $apiKey, string $model, string $apiUrl, string $title, string $content, string $targetLang): array {
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Clé API OpenAI absente.'];
        }

        $endpoint = $apiUrl ?: '[https://api.openai.com/v1/chat/completions](https://api.openai.com/v1/chat/completions)';

        $systemPrompt = "Tu es un moteur de traduction professionnelle. Traduis le titre et le contenu HTML en langue '{$targetLang}'. "
            . "Conserve l'intégrité intégrale des éléments HTML (balises, attributs, liens). "
            . "Renvoie exclusivement un objet JSON valide avec deux clés 'title' et 'content'.";

        $userPrompt = json_encode([
            'title'   => $title,
            'content' => $content
        ], JSON_UNESCAPED_UNICODE);

        $payload = [
            'model'           => $model ?: 'gpt-4o-mini',
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
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => "cURL OpenAI : {$curlError}"];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "OpenAI HTTP {$httpCode} : {$response}"];
        }

        $json = json_decode($response, true);
        $rawText = $json['choices'][0]['message']['content'] ?? '';
        $data = json_decode($rawText, true);

        if (isset($data['title'], $data['content'])) {
            return [
                'success'  => true,
                'title'    => trim($data['title']),
                'content'  => trim($data['content']),
                'provider' => 'OpenAI'
            ];
        }

        return ['success' => false, 'error' => 'Format de réponse OpenAI inattendu.'];
    }
}