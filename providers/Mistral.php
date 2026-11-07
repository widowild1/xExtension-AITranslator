<?php

class AITranslator_Provider_Mistral {

    public static function translate(string $apiKey, string $model, string $title, string $content, string $targetLang): array {
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Clé API Mistral absente.'];
        }

        $endpoint = '[https://api.mistral.ai/v1/chat/completions](https://api.mistral.ai/v1/chat/completions)';

        $systemPrompt = "Tu es un traducteur professionnel expert. Traduis le titre et le corps HTML de l'article vers le '{$targetLang}'. "
            . "Conserve scrupuleusement l'ensemble du balisage HTML (liens, images, styles, préformatage). "
            . "Réponds STRICTEMENT avec un objet JSON valide contenant les champs 'title' et 'content'. "
            . "N'inclus aucune prose additionnelle en dehors du JSON.";

        $userPrompt = json_encode([
            'title'   => $title,
            'content' => $content
        ], JSON_UNESCAPED_UNICODE);

        $payload = [
            'model'           => $model ?: 'mistral-small-latest',
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
            return ['success' => false, 'error' => "cURL Mistral : {$curlError}"];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "Mistral HTTP {$httpCode} : {$response}"];
        }

        $json = json_decode($response, true);
        $rawText = $json['choices'][0]['message']['content'] ?? '';
        $data = json_decode($rawText, true);

        if (isset($data['title'], $data['content'])) {
            return [
                'success'  => true,
                'title'    => trim($data['title']),
                'content'  => trim($data['content']),
                'provider' => 'Mistral'
            ];
        }

        return ['success' => false, 'error' => 'Format de réponse Mistral inattendu.'];
    }

    public static function summarize(string $apiKey, string $model, string $text, string $targetLang = 'fr'): array {
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Clé API Mistral absente pour le résumé.'];
        }

        $endpoint = '[https://api.mistral.ai/v1/chat/completions](https://api.mistral.ai/v1/chat/completions)';

        $payload = [
            'model'           => $model ?: 'mistral-small-latest',
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => 'Génère 3 points clés résumant le texte au format JSON : {"points": ["point 1", "point 2", "point 3"]}.'],
                ['role' => 'user', 'content' => mb_substr($text, 0, 4000)]
            ],
            'temperature'     => 0.3
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
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $json = json_decode($response, true);
            $raw = json_decode($json['choices'][0]['message']['content'] ?? '', true);
            if (isset($raw['points']) && is_array($raw['points'])) {
                return ['success' => true, 'points' => $raw['points']];
            }
        }

        return ['success' => false, 'error' => 'Échec du résumé Mistral'];
    }
}