<?php

class AITranslator_Provider_Gemini {

    public static function translate(string $apiKey, string $model, string$title, string $content, string$targetLang): array {
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Clé API Gemini absente.'];
        }

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $systemInstruction = "Tu es un traducteur bilingue professionnel de haute précision. "
            . "Traduis le titre et le contenu HTML fournis vers la langue cible : '{$targetLang}'. "
            . "Conserve scrupuleusement la totalité de la structure HTML d'origine, y compris les balises <a>, <img>, <code>, <pre>, les attributs et classes CSS. "
            . "Ne modifie pas les blocs de code ni les URLs. "
            . "Retourne STRICTEMENT un objet JSON valide contenant exactement deux clés : 'title' et 'content'. "
            . "N'ajoute aucun texte explicatif ni délimiteur markdown de code (pas de ```json).";

        $inputData = json_encode([
            'title'   => $title,
            'content' => $content
        ], JSON_UNESCAPED_UNICODE);

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $systemInstruction . "\n\n" . $inputData]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature'     => 0.2,
                'responseMimeType'=> 'application/json'
            ]
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => "cURL Gemini : {$curlError}"];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "Gemini HTTP {$httpCode} : {$response}"];
        }

        $json = json_decode($response, true);
        $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $data = json_decode($rawText, true);

        if (isset($data['title'], $data['content'])) {
            return [
                'success'  => true,
                'title'    => trim($data['title']),
                'content'  => trim($data['content']),
                'provider' => 'Gemini'
            ];
        }

        return ['success' => false, 'error' => 'Structure JSON retournée par Gemini non reconnue.'];
    }

    public static function summarize(string $apiKey, string $model, string $text, string $targetLang = 'fr'): array {
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Clé API Gemini absente pour le résumé.'];
        }

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $systemInstruction = "Tu es un assistant éditorial de synthèse. Rédige un résumé TL;DR concis en 3 puces en français pour le texte fourni. "
            . "Retourne UNIQUEMENT un tableau JSON de 3 chaînes de caractères (ex: [\"point 1\", \"point 2\", \"point 3\"]).";

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $systemInstruction . "\n\nTexte à résumer :\n" . mb_substr($text, 0, 4000)]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature'     => 0.3,
                'responseMimeType'=> 'application/json'
            ]
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $json = json_decode($response, true);
            $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $points = json_decode($rawText, true);
            if (is_array($points)) {
                return ['success' => true, 'points' => $points];
            }
        }

        return ['success' => false, 'error' => 'Échec de la génération du résumé Gemini'];
    }
}