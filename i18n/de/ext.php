<?php

return [
    'aitranslator' => [
        'title' => 'Konfiguration der AI Translator Erweiterung',
        'intro' => 'Die unten aufgeführten API-Schlüssel und Verbindungsparameter werden <strong>gemeinsam genutzt</strong>: Einmal konfigurieren und sowohl für die <strong>vollständige Übersetzung</strong> als auch für die <strong>Schnellzusammenfassung (TL;DR)</strong> verwenden.',

        // Dashboard und Wartung
        'dashboard' => [
            'title'               => '📊 Dashboard: Nutzung & Kontingente (letzte 30 Tage)',
            'last_activity'       => 'Letzte Aktivität: %s',
            'no_activity'         => 'Keine Aktivität aufgezeichnet',
            'stat_success'        => 'Erfolgreiche Übersetzungen',
            'stat_tldr'           => 'TL;DR-Zusammenfassungen',
            'stat_cache'          => 'SQLite-Cache-Einträge',
            'stat_rate'           => 'Erfolgsquote (%d Fehler)',
            'cache_distribution'  => 'Cache-Verteilung nach Engine:',
            'btn_clear_cache'     => '🗑️ SQLite-Cache leeren',
            'btn_clear_logs'      => '🧹 Protokolle löschen',
            'warning_title'       => '⚠️ Warnung zum Leeren des Cache:',
            'warning_text'        => 'Das Löschen des Cache entfernt unwiderruflich alle gespeicherten Übersetzungen und TL;DR-Zusammenfassungen. Fremdsprachige Artikel erscheinen wieder im Originaltext. Jedes erneute Aufrufen fragt externe APIs (Gemini, Mistral, OpenAI, DeepL) erneut ab und verbraucht Tokens bzw. Kontingente.',
            'confirm_clear_logs'  => 'Möchten Sie den gesamten Verlauf der Aktivitätsprotokolle zurücksetzen?',
            'prompt_clear_cache'  => "⚠️ ACHTUNG: ENDGÜLTIGES LÖSCHEN DES SQLITE-CACHE\n\nDieser Vorgang löscht alle lokal gespeicherten Übersetzungen und Zusammenfassungen.\n\nFolgen:\n• Übersetzte Artikel werden wieder im Original angezeigt.\n• Künftige Aufrufe beanspruchen erneut API-Kontingente und Tokens.\n\nUm das Löschen zu bestätigen, geben Sie unten PURGER ein:",
            'prompt_keyword'      => 'PURGER',
            'prompt_cancelled'    => 'Vorgang abgebrochen: Falsches Schlüsselwort. Der Cache bleibt unverändert.',
        ],

        // 1. Allgemeine Einstellungen
        'general' => [
            'title'              => '1. Allgemeine Einstellungen',
            'target_lang'        => 'Hauptzielfarbe/-sprache:',
            'target_lang_help'   => 'ISO 639-1 Code (z.B. <code>de</code>, <code>en</code>, <code>fr</code>, <code>es</code>).',
            'pretranslate'       => 'Vorübersetzung beim Abruf:',
            'pretranslate_desc'  => 'Eingehende Artikel während des FreshRSS-Cronjobs automatisch übersetzen',
        ],

        // 2. Engine-Zuweisung
        'engines' => [
            'title'              => '2. Engine-Zuweisung',
            'primary_label'      => 'Hauptübersetzungs-Engine:',
            'primary_help'       => 'Wird verwendet, um Titel und den gesamten Artikeltext zu übersetzen.',
            'fallback_label'     => 'Ausweich-Engine (Fallback):',
            'fallback_help'      => 'Wird automatisch genutzt, falls die Haupt-Engine einen Fehler meldet oder das Kontingent erschöpft ist.',
            'tldr_label'         => 'Zusammenfassungs-Engine (TL;DR):',
            'tldr_help'          => 'Wird beim Klick auf « ⚡ TL;DR Zusammenfassung » aufgerufen.',
            'opt_auto'           => 'Automatisch (Wählt die erste verfügbare KI)',
            'opt_statistical'    => 'Lokaler statistischer Analysator (Ohne KI)',
            'opt_libretranslate' => 'LibreTranslate (Lokal / Selbst gehostet)',
            'opt_gemini'         => 'Google Gemini',
            'opt_mistral'        => 'Mistral AI',
            'opt_ollama'         => 'Ollama / llama.cpp (Lokal selbst gehostet)',
            'opt_openai'         => 'OpenAI',
            'opt_deepl'          => 'DeepL',
        ],

        // 3. Provider und Verbindungstest
        'providers' => [
            'title'               => '3. Provider-Zugangsdaten & Verbindungen',
            'api_key'             => 'API-Schlüssel:',
            'api_key_optional'    => 'API-Schlüssel (Optional):',
            'model'               => 'Modell:',
            'model_local'         => 'Lokales Modell:',
            'endpoint'            => 'OpenAI / Kompatibler Endpoint:',
            'url_local'           => 'Lokale API-URL:',
            'url_libretranslate'  => 'LibreTranslate-URL:',
            'account_type'        => 'Konto-Abonnement:',
            'account_free'        => 'DeepL API Free',
            'account_pro'         => 'DeepL API Pro',
            'btn_test_gemini'     => '⚡ Gemini-Verbindung testen',
            'btn_test_mistral'    => '⚡ Mistral-Verbindung testen',
            'btn_test_ollama'     => '⚡ Lokalen Server Ollama / llama.cpp testen',
            'btn_test_openai'     => '⚡ OpenAI-Verbindung testen',
            'btn_test_lt'         => '⚡ LibreTranslate testen',
            'btn_test_deepl'      => '⚡ DeepL-Verbindung & Kontingent testen',
            'help_gemini_model'   => 'Z.B.: <code>gemini-3.5-flash-lite</code>, <code>gemini-3.6-flash</code>.',
            'help_mistral_model'  => 'Z.B.: <code>mistral-small-latest</code>, <code>open-mistral-7b</code>.',
            'help_ollama_url'     => 'Z.B.: <code>http://localhost:11434</code> (Ollama) oder <code>http://localhost:8080</code> (llama.cpp).',
            'help_ollama_model'   => 'Z.B.: <code>llama3.2:latest</code>, <code>mistral:latest</code>, <code>qwen2.5:latest</code>.',
            'help_openai_model'   => 'Z.B.: <code>gpt-4o-mini</code>, <code>gpt-4o</code>.',
        ],

        // Aktionen
        'actions' => [
            'save' => 'Alle Einstellungen speichern',
        ],

        // Toolbar und Artikelansicht
        'toolbar' => [
            'btn_translate'    => '⚡ Übersetzen (T)',
            'btn_translated'   => '✓ Übersetzt',
            'btn_same_lang'    => '✓ Originalsprache',
            'btn_tldr'         => '⚡ TL;DR Zusammenfassung',
            'btn_tldr_title'   => 'Shift+T: Schnelle Zusammenfassung',
            'btn_split'        => '📖 Nebeneinander-Ansicht',
            'btn_split_title'  => 'Taste B: Zweisprachige Ansicht nebeneinander',
            'btn_stacked'      => '📖 Untereinander-Ansicht',
            'tldr_title'       => '⚡ Express-Zusammenfassung (TL;DR)',
            'original_title'   => 'Originalversion',
            'translated_via'   => 'Übersetzt mit %s',
        ],

        // Dynamische JavaScript-Meldungen
        'messages' => [
            'testing'          => '⏳ Teste Verbindung...',
            'pinging'          => 'Ping läuft...',
            'translating'      => '⏳ Übersetzung läuft...',
            'synthesizing'     => '⚡ Erstelle Zusammenfassung...',
            'tldr_displayed'   => '⚡ Zusammenfassung angezeigt',
            'error'            => '❌ Fehler',
            'not_translated'   => '⚠️ Nicht übersetzt',
            'network_error'    => '❌ Netzwerkfehler: %s',
        ],
    ],
];