<?php

class FreshExtension_aiTranslator_Controller extends Minz_ActionController {

    private function sendJson(array $data, int $httpCode = 200): void {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    private function getExtensionInstance(): ?AITranslatorExtension {
        foreach (['AITranslator', 'AI Translator', 'xExtension-AITranslator'] as $name) {
            $ext = Minz_ExtensionManager::findExtension($name);
            if ($ext instanceof AITranslatorExtension) {
                return $ext;
            }
        }
        if (class_exists('AITranslatorExtension')) {
            $meta = @json_decode(@file_get_contents(dirname(__DIR__) . '/metadata.json'), true) ?: [];
            return new AITranslatorExtension($meta);
        }
        return null;
    }

    private function getRequestedEntry(): ?FreshRSS_Entry {
        $id = Minz_Request::paramString('id');
        if (!$id) {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
        }

        if (!$id) {
            $this->sendJson(['error' => 'Identifiant d’article manquant'], 400);
        }

        $entryDAO = FreshRSS_Factory::createEntryDao();
        $entry = $entryDAO->searchById($id);

        if (!$entry) {
            $this->sendJson(['error' => "Article #{$id} introuvable dans FreshRSS"], 404);
        }

        return $entry;
    }

    public function translateAction(): void {
        try {
            $ext = $this->getExtensionInstance();
            if (!$ext) {
                $this->sendJson(['error' => 'Extension AITranslator introuvable'], 500);
            }

            $entry = $this->getRequestedEntry();
            $targetLang = $ext->getUserConfig('target_lang', 'fr');

            $title = $entry->title();
            $content = $entry->content();

            $hash = AITranslator_TagClassifier::getCanonicalHash($title, $content, $targetLang, 'translate');
            $cached = $ext->getCachedTranslation($hash);

            if ($cached) {
                $transTitle = $cached['title'];
                $transContent = $cached['content'];
                $provider = $cached['provider'];
            } else {
                $res = $ext->executeTranslation($title, $content, $targetLang);
                if (empty($res['success'])) {
                    $this->sendJson(['error' => $res['error'] ?? 'Échec de la traduction'], 502);
                }
                $transTitle = $res['title'];
                $transContent = $res['content'];
                $provider = $res['provider'] ?? 'AI';

                $ext->setCachedTranslation($hash, $transTitle, $transContent, $provider, $targetLang); if (!empty($res['summary']) && method_exists($ext, 'cacheOnePassTldr')) { $ext->cacheOnePassTldr($title, $content, $targetLang, $res['summary'], $provider); }
            }

            $effectiveOrigTitle = trim(strip_tags((string)$title)) !== '' ? $title : AITranslator_ArticleUiRenderer::extractFallbackTitle((string)$content);
            $effectiveTransTitle = trim(strip_tags((string)$transTitle)) !== '' ? $transTitle : AITranslator_ArticleUiRenderer::extractFallbackTitle((string)$transContent);
            $formattedTitle = $ext->formatBilingualTitle($effectiveTransTitle, $effectiveOrigTitle);
            $formattedContent = AITranslator_ArticleUiRenderer::formatTranslatedContent(
                $transContent,
                $content,
                $provider,
                (string)$entry->id()
            );

            $this->sendJson([
                'status'      => 'success',
                'entryId'     => (string)$entry->id(),
                'title'       => $formattedTitle,
                'contentHtml' => $ext->formatBilingualContent($formattedContent, $content),
                'provider'    => $provider,
                'tldrHtml'    => ($ext->getCachedTranslation(AITranslator_TagClassifier::getCanonicalHash($title, $content, $targetLang, 'tldr')))['content'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $this->sendJson(['error' => 'Erreur serveur translate : ' . $e->getMessage()], 500);
        }
    }

    public function tldrAction(): void {
        try {
            $ext = $this->getExtensionInstance();
            if (!$ext) {
                $this->sendJson(['error' => 'Extension non chargée'], 500);
            }

            $entry = $this->getRequestedEntry();
            $targetLang = $ext->getUserConfig('target_lang', 'fr');
            $title = $entry->title();
            $content = $entry->content();

            $hash = AITranslator_TagClassifier::getCanonicalHash($title, $content, $targetLang, 'tldr');
            $cached = $ext->getCachedTranslation($hash);

            if ($cached) {
                $this->sendJson([
                    'status'   => 'success',
                    'entryId'  => (string)$entry->id(),
                    'tldrHtml' => $cached['content'],
                    'provider' => $cached['provider'],
                ]);
            }

            $transHash = AITranslator_TagClassifier::getCanonicalHash($title, $content, $targetLang, 'translate');
            $transCached = $ext->getCachedTranslation($transHash);
            $sourceText = $transCached ? $transCached['content'] : $content;

            $cleanText = strip_tags($sourceText);
            $cleanText = html_entity_decode($cleanText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $cleanText = preg_replace('/\s+/u', ' ', $cleanText);
            $cleanText = trim($cleanText);

            if (mb_strlen($cleanText) > 4500) {
                $cleanText = mb_substr($cleanText, 0, 4500);
            }

            $res = $ext->executeSummary($cleanText, $targetLang);
            $points = $res['points'] ?? [];
            $provider = $res['provider'] ?? 'IA';

            $html  = '<div class="aitranslator-tldr-box">';
            $html .= '<div class="aitranslator-tldr-title">⚡ Résumé express (TL;DR) <span class="aitranslator-tldr-provider">· ' . htmlspecialchars($provider) . '</span></div>';
            $html .= '<ul>';
            foreach ($points as $pt) {
                $html .= '<li>' . htmlspecialchars($pt) . '</li>';
            }
            $html .= '</ul></div>';

            $ext->setCachedTranslation($hash, $title, $html, $provider, $targetLang);

            $this->sendJson([
                'status'   => 'success',
                'entryId'  => (string)$entry->id(),
                'tldrHtml' => $html,
                'provider' => $provider,
            ]);
        } catch (\Throwable $e) {
            $this->sendJson(['error' => 'Erreur serveur TLDR : ' . $e->getMessage()], 500);
        }
    }

    public function testProviderAction(): void {
        try {
            $ext = $this->getExtensionInstance();
            if (!$ext) {
                $this->sendJson(['status' => 'error', 'error' => 'Extension AITranslator introuvable'], 200);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];

            $provider = Minz_Request::paramString('provider') ?: ($input['provider'] ?? '');
            if (!$provider) {
                $this->sendJson(['status' => 'error', 'error' => 'Fournisseur non spécifié'], 200);
            }

            $params = [
                'api_key'  => Minz_Request::paramString('api_key') ?: ($input['api_key'] ?? null),
                'region'   => Minz_Request::paramString('region') ?: ($input['region'] ?? null),
                'api_url'  => Minz_Request::paramString('api_url') ?: ($input['api_url'] ?? null),
                'model'    => Minz_Request::paramString('model') ?: ($input['model'] ?? null),
                'api_type' => Minz_Request::paramString('api_type') ?: ($input['api_type'] ?? null),
            ];

            $res = $ext->testProviderConnection($provider, $params);

            if (!empty($res['success'])) {
                $this->sendJson([
                    'status'   => 'success',
                    'latency'  => $res['latency'] ?? 0,
                    'message'  => $res['message'] ?? 'Connexion réussie',
                ]);
            } else {
                $this->sendJson([
                    'status' => 'error',
                    'error'  => $res['error'] ?? 'Échec du test de connexion',
                ]);
            }
        } catch (\Throwable $e) {
            $this->sendJson(['status' => 'error', 'error' => 'Erreur test : ' . $e->getMessage()], 200);
        }
    }

    public function exportConfigAction(): void {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(403);
        }
        $ext = $this->getExtensionInstance();
        if (!$ext) {
            Minz_Error::error(404);
        }

        $data = $ext->exportConfigurationData();
        $filename = 'aitranslator-config-' . date('Y-m-d-His') . '.json';
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    public function exportDbAction(): void {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(403);
        }
        $ext = $this->getExtensionInstance();
        if (!$ext) {
            Minz_Error::error(404);
        }

        try {
            $ext->getDatabase()->exec('PRAGMA wal_checkpoint(TRUNCATE);');
        } catch (\Throwable $e) {}

        $dbPath = $ext->getDatabasePath();
        if (!file_exists($dbPath)) {
            Minz_Error::error(404);
        }

        $filename = 'aitranslator-database-' . date('Y-m-d-His') . '.db';
        header('Content-Type: application/x-sqlite3');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($dbPath));
        readfile($dbPath);
        exit();
    }
}

// Déclaration des alias pour assurer le dispatching Minz
if (!class_exists('FreshRSS_aiTranslator_Controller', false)) {
    class_alias('FreshExtension_aiTranslator_Controller', 'FreshRSS_aiTranslator_Controller');
}
if (!class_exists('aiTranslatorController', false)) {
    class_alias('FreshExtension_aiTranslator_Controller', 'aiTranslatorController');
}