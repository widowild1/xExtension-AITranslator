<?php

$dict = [
    'title' => 'Configuration de l’extension AI Translator',
    'intro' => 'Les clés d’API et paramètres de connexion ci-dessous sont <strong>mutualisés</strong> : vous les saisissez une seule fois et ils sont utilisables à la fois pour la <strong>traduction intégrale</strong> et pour la <strong>synthèse express (TL;DR)</strong>.',

    // Tableau de bord & maintenance
    'dashboard' => [
        'title'               => '📊 Tableau de bord : Utilisation & Quotas (30 derniers jours)',
        'last_activity'       => 'Dernière activité : %s',
        'no_activity'         => 'Aucune activité enregistrée',
        'stat_success'        => 'Traductions réussies',
        'stat_tldr'           => 'Résumés TL;DR générés',
        'stat_cache'          => 'Entrées en cache SQLite',
        'stat_rate'           => 'Taux de succès (%d erreurs)',
        'cache_distribution'  => 'Répartition du cache par moteur :',
        'btn_clear_cache'     => '🗑️ Vider le cache SQLite',
        'btn_clear_logs'      => '🧹 Purger les journaux',
        'warning_title'       => '⚠️ Avertissement sur la purge du cache :',
        'warning_text'        => 'La suppression du cache efface l’intégralité des traductions et synthèses TL;DR mémorisées. Vos articles étrangers réapparaîtront dans leur langue d’origine, et toute future lecture redéclenchera des appels vers vos API externes (Azure, Gemini, Mistral, OpenAI, DeepL), ce qui consommera à nouveau vos quotas et vos crédits payants.',
        'confirm_clear_logs'  => 'Réinitialiser l’historique complet des journaux d’activité ?',
        'prompt_clear_cache'  => "⚠️ ATTENTION : SUPPRESSION DÉFINITIVE DU CACHE SQLITE\n\nCette opération va effacer toutes les traductions et synthèses mémorisées localement.\n\nConséquences :\n• Les articles traduits réapparaîtront en version originale.\n• Les prochaines lectures consommeront à nouveau des tokens et des quotas API.\n\nPour confirmer la purge, tapez PURGER ci-dessous :",
        'prompt_keyword'      => 'PURGER',
        'prompt_cancelled'    => 'Action annulée : mot-clé non reconnu. Le cache est intact.',
    ],

    // 1. Préférences générales
    'general' => [
        'title'              => '1. Préférences Générales',
        'target_lang'        => 'Langue cible principale :',
        'target_lang_help'   => 'Code ISO 639-1 (ex: <code>fr</code>, <code>en</code>, <code>de</code>, <code>es</code>).',
        'pretranslate'       => 'Pré-traduction à l’ingestion :',
        'pretranslate_desc'  => 'Traduire automatiquement les articles entrants lors du cron FreshRSS',
    ],

    // 2. Attribution des moteurs
    'engines' => [
        'title'               => '2. Attribution des Moteurs',
        'group_self_hosted'   => '🏠 Moteurs Auto-hébergés (Local / LXC)',
        'group_cloud'         => '☁️ Solutions Cloud / SaaS',
        'primary_label'       => 'Moteur de Traduction Principal :',
        'primary_help'        => 'Moteur sollicité pour traduire le titre et l’intégralité du corps de texte.',
        'fallback_label'      => 'Moteur de Secours (Fallback) :',
        'fallback_help'       => 'Utilisé automatiquement si le moteur principal renvoie une erreur ou un dépassement de quota.',
        'tldr_label'          => 'Moteur de Synthèse (TL;DR) :',
        'tldr_help'           => 'Moteur sollicité lors du clic sur le bouton « ⚡ Résumé TL;DR ».',
        'opt_auto'            => 'Automatique (Sélectionne la 1ère IA configurée)',
        'opt_statistical'     => 'Analyseur statistique local (Sans IA)',
        'opt_azure'           => 'Microsoft Azure Translator (Cloud - 2M car./mois)',
        'opt_nllb'            => 'Meta NLLB-200 (Local / Auto-hébergé)',
        'opt_libretranslate'  => 'LibreTranslate (Local / Auto-hébergé)',
        'opt_gemini'          => 'Google Gemini',
        'opt_mistral'         => 'Mistral AI',
        'opt_ollama'          => 'Ollama / llama.cpp (Auto-hébergé local)',
        'opt_openai'          => 'OpenAI',
        'opt_deepl'           => 'DeepL',
    ],

    // 3. Fournisseurs & banc d'essai
    'providers' => [
        'title'                => '3. Identifiants & Connexions des Fournisseurs',
        'subtitle_self_hosted' => '🏠 Moteurs Auto-hébergés (Local / LXC)',
        'subtitle_cloud'       => '☁️ Solutions Cloud / SaaS',
        'api_key'              => 'Clé API :',
        'api_key_optional'     => 'Clé API (Optionnelle) :',
        'region'               => 'Région Azure :',
        'model'                => 'Modèle :',
        'model_local'          => 'Modèle local :',
        'endpoint'             => 'Endpoint OpenAI / Compatible :',
        'url_local'            => 'URL de l’API locale :',
        'url_libretranslate'   => 'URL LibreTranslate :',
        'url_nllb'             => 'URL de l’API NLLB-200 :',
        'account_type'         => 'Type d’abonnement :',
        'account_free'         => 'DeepL API Free',
        'account_pro'          => 'DeepL API Pro',
        'btn_test_azure'       => '⚡ Tester Microsoft Azure Translator',
        'btn_test_nllb'        => '⚡ Tester le serveur Meta NLLB-200',
        'btn_test_gemini'      => '⚡ Tester la connexion Gemini',
        'btn_test_mistral'     => '⚡ Tester la connexion Mistral',
        'btn_test_ollama'      => '⚡ Tester le serveur local Ollama / llama.cpp',
        'btn_test_openai'      => '⚡ Tester la connexion OpenAI',
        'btn_test_lt'          => '⚡ Tester LibreTranslate',
        'btn_test_deepl'       => '⚡ Tester la connexion & Quota DeepL',
        'help_azure_region'    => 'Ex: <code>francecentral</code>, <code>westeurope</code> ou <code>global</code>.',
        'help_nllb_url'        => 'Ex: <code>http://localhost:8000</code> ou <code>http://mon-serveur:8080</code> (wrapper compatible nllb-serve).',
        'help_gemini_model'    => 'Ex: <code>gemini-3.5-flash-lite</code>, <code>gemini-3.6-flash</code>.',
        'help_mistral_model'   => 'Ex: <code>mistral-small-latest</code>, <code>open-mistral-7b</code>.',
        'help_ollama_url'      => 'Ex: <code>http://localhost:11434</code> (Ollama) ou <code>http://localhost:8080</code> (llama.cpp).',
        'help_ollama_model'    => 'Ex: <code>llama3.2:latest</code>, <code>mistral:latest</code>, <code>qwen2.5:latest</code>.',
        'help_openai_model'    => 'Ex: <code>gpt-4o-mini</code>, <code>gpt-4o</code>.',
    ],

    // 4. Sauvegarde & Restauration
    'backup' => [
        'title'               => '4. Sauvegarde & Restauration (Import / Export)',
        'desc'                => 'Exportez ou restaurez la configuration complète de l’extension ainsi que la base de données SQLite locale.',
        'export_section'      => 'Exportation',
        'export_config_btn'   => '📥 Exporter la configuration (JSON)',
        'export_db_btn'       => '📥 Exporter la base SQLite (.db)',
        'import_section'      => 'Importation',
        'import_config_lbl'   => 'Importer une configuration (.json) :',
        'import_config_btn'   => '📤 Importer le fichier JSON',
        'import_db_lbl'       => 'Restaurer une base de données (.db) :',
        'import_db_btn'       => '📤 Restaurer la base SQLite',
        'import_db_warn'      => '⚠️ Attention : l’import d’un fichier .db écrasera la base SQLite actuelle (cache, règles et glossaire).',
        'prompt_import_db'    => "⚠️ ATTENTION : ÉCRASEMENT COMPLET DE LA BASE SQLITE\n\nL'importation va remplacer définitivement le fichier translator.db actuel.\n\nPour confirmer l'écrasement, tapez IMPORTER ci-dessous :",
        'prompt_keyword'      => 'IMPORTER',
        'prompt_cancelled'    => 'Restauration annulée : mot-clé non reconnu.',
        'status_import_ok'    => 'Configuration importée avec succès (%d paramètres, %d règles, %d termes)',
        'status_db_ok'        => 'Base SQLite restaurée et vérifiée avec succès',
        'status_err_file'     => 'Fichier téléversé invalide ou manquant',
        'status_err_sqlite'   => 'Le fichier téléversé n’est pas une base de données SQLite 3 valide ou intègre',
    ],

    // Actions
    'actions' => [
        'save' => 'Enregistrer tous les paramètres',
    ],

    // Barre d'outils du lecteur & Rendu d'article
    'toolbar' => [
        'btn_translate'    => '⚡ Traduire (T)',
        'btn_translated'   => '✓ Traduit en FR',
        'btn_same_lang'    => '✓ En français',
        'btn_tldr'         => '⚡ Résumé TL;DR',
        'btn_tldr_title'   => 'Shift+T : Résumé rapide',
        'btn_split'        => '📖 Vue côte à côte',
        'btn_split_title'  => 'Touche B : Vue bilingue côte à côte',
        'btn_stacked'      => '📖 Vue empilée',
        'tldr_title'       => '⚡ Résumé express (TL;DR)',
        'original_title'   => 'Version originale',
        'translated_via'   => 'Traduit via %s',
    ],

    // Statuts dynamiques JavaScript
    'messages' => [
        'testing'          => '⏳ Test en cours...',
        'pinging'          => 'Ping en cours...',
        'translating'      => '⏳ Traduction en cours...',
        'synthesizing'     => '⚡ Synthèse...',
        'tldr_displayed'   => '⚡ Résumé affiché',
        'error'            => '❌ Erreur',
        'not_translated'   => '⚠️ Non traduit',
        'network_error'    => '❌ Erreur réseau : %s',
    ],
];

// Double indexation pour compatibilité ascendante avec Minz_Translate
return [
    'ext'          => ['aitranslator' => $dict],
    'aitranslator' => $dict,
];