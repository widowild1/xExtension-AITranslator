<?php

return [
    'aitranslator' => [
        'title' => 'Configuração da extensão AI Translator',
        'intro' => 'As chaves de API e configurações de conexão abaixo são <strong>compartilhadas</strong>: defina-as uma única vez para utilizá-las tanto na <strong>tradução completa</strong> quanto no <strong>resumo expresso (TL;DR)</strong>.',

        // Painel de controle e manutenção
        'dashboard' => [
            'title'               => '📊 Painel: Uso e cotas (últimos 30 dias)',
            'last_activity'       => 'Última atividade: %s',
            'no_activity'         => 'Nenhuma atividade registrada',
            'stat_success'        => 'Traduções concluídas',
            'stat_tldr'           => 'Resumos TL;DR gerados',
            'stat_cache'          => 'Entradas em cache SQLite',
            'stat_rate'           => 'Taxa de sucesso (%d erros)',
            'cache_distribution'  => 'Distribuição do cache por motor:',
            'btn_clear_cache'     => '🗑️ Limpar cache SQLite',
            'btn_clear_logs'      => '🧹 Limpar logs de atividade',
            'warning_title'       => '⚠️ Aviso sobre a limpeza do cache:',
            'warning_text'        => 'Limpar o cache removerá permanentemente todas as traduções e resumos TL;DR gravados. Os artigos em idioma estrangeiro voltarão ao idioma original, e futuras leituras farão novas requisições às suas APIs externas (Google Gemini, Mistral, OpenAI, DeepL), consumindo novamente suas cotas e créditos.',
            'confirm_clear_logs'  => 'Deseja redefinir todo o histórico de logs de atividade?',
            'prompt_clear_cache'  => "⚠️ ATENÇÃO: EXCLUSÃO PERMANENTE DO CACHE SQLITE\n\nEsta operação apagará todas as traduções e resumos armazenados localmente.\n\nConsequências:\n• Artigos traduzidos voltarão ao idioma original.\n• Novas consultas consumirão novamente tokens e cotas de API.\n\nPara confirmar a exclusão, digite PURGER abaixo:",
            'prompt_keyword'      => 'PURGER',
            'prompt_cancelled'    => 'Operação cancelada: palavra-chave não reconhecida. O cache permanece intacto.',
        ],

        // 1. Preferências gerais
        'general' => [
            'title'              => '1. Preferências Gerais',
            'target_lang'        => 'Idioma de destino principal:',
            'target_lang_help'   => 'Código ISO 639-1 (ex: <code>pt</code>, <code>en</code>, <code>fr</code>, <code>es</code>).',
            'pretranslate'       => 'Pré-tradução na coleta:',
            'pretranslate_desc'  => 'Traduzir automaticamente novos artigos durante o cron do FreshRSS',
        ],

        // 2. Atribuição de motores
        'engines' => [
            'title'              => '2. Atribuição de Motores',
            'primary_label'      => 'Motor de Tradução Principal:',
            'primary_help'       => 'Motor utilizado para traduzir o título e o corpo completo do artigo.',
            'fallback_label'     => 'Motor de Contingência (Fallback):',
            'fallback_help'      => 'Utilizado automaticamente caso o motor principal apresente falha ou esgote a cota.',
            'fallback2_label'     => 'Motor de Contingência Secundário (Fallback 2):',
            'fallback2_help'      => 'Utilizado em último caso se tanto o motor principal quanto o primeiro motor de contingência falharem.',
            'opt_none'            => 'Nenhum (Desativado)',
            'multiplex_label'     => 'Multiplexação paralela (3 motores):',
            'multiplex_desc'      => 'Usar simultaneamente os 3 motores configurados durante o processamento em lote',
            'multiplex_help'      => 'Ativado apenas se os 3 motores forem distintos e responderem corretamente à verificação de integridade.',
            'tldr_label'         => 'Motor de Síntese (TL;DR):',
            'tldr_help'          => 'Motor acionado ao clicar no botão « ⚡ Resumo TL;DR ».',
            'opt_auto'           => 'Automático (Seleciona a 1ª IA disponível)',
            'opt_statistical'    => 'Analisador estatístico local (Sem IA)',
            'opt_libretranslate' => 'LibreTranslate (Local / Auto-hospedado)',
            'opt_gemini'         => 'Google Gemini',
            'opt_mistral'        => 'Mistral AI',
            'opt_ollama'         => 'Ollama / llama.cpp (Auto-hospedado local)',
            'opt_openai'         => 'OpenAI',
            'opt_deepl'          => 'DeepL',
        ],

        // 3. Provedores e testes de conexão
        'providers' => [
            'title'               => '3. Credenciais e Conexões de Provedores',
            'api_key'             => 'Chave de API:',
            'api_key_optional'    => 'Chave de API (Opcional):',
            'model'               => 'Modelo:',
            'model_local'         => 'Modelo local:',
            'endpoint'            => 'Endpoint OpenAI / Compatível:',
            'url_local'           => 'URL da API local:',
            'url_libretranslate'  => 'URL LibreTranslate:',
            'account_type'        => 'Tipo de conta:',
            'account_free'        => 'DeepL API Free',
            'account_pro'         => 'DeepL API Pro',
            'btn_test_gemini'     => '⚡ Testar conexão Gemini',
            'btn_test_mistral'    => '⚡ Testar conexão Mistral',
            'btn_test_ollama'     => '⚡ Testar servidor local Ollama / llama.cpp',
            'btn_test_openai'     => '⚡ Testar conexão OpenAI',
            'btn_test_lt'         => '⚡ Testar LibreTranslate',
            'btn_test_deepl'      => '⚡ Testar conexão e cota DeepL',
            'help_gemini_model'   => 'Ex: <code>gemini-3.5-flash-lite</code>, <code>gemini-3.6-flash</code>.',
            'help_mistral_model'  => 'Ex: <code>mistral-small-latest</code>, <code>open-mistral-7b</code>.',
            'help_ollama_url'     => 'Ex: <code>http://localhost:11434</code> (Ollama) ou <code>http://localhost:8080</code> (llama.cpp).',
            'help_ollama_model'   => 'Ex: <code>llama3.2:latest</code>, <code>mistral:latest</code>, <code>qwen2.5:latest</code>.',
            'help_openai_model'   => 'Ex: <code>gpt-4o-mini</code>, <code>gpt-4o</code>.',
        ],

        // Ações
        'actions' => [
            'save' => 'Salvar todas as configurações',
        ],

        // Barra de ferramentas e exibição de artigos
        'toolbar' => [
            'btn_translate'    => '⚡ Traduzir (T)',
            'btn_translated'   => '✓ Traduzido',
            'btn_same_lang'    => '✓ Idioma original',
            'btn_tldr'         => '⚡ Resumo TL;DR',
            'btn_tldr_title'   => 'Shift+T: Resumo rápido',
            'btn_split'        => '📖 Visão lado a lado',
            'btn_split_title'  => 'Tecla B: Visão bilíngue lado a lado',
            'btn_stacked'      => '📖 Visão empilhada',
            'tldr_title'       => '⚡ Resumo expresso (TL;DR)',
            'original_title'   => 'Versão original',
            'translated_via'   => 'Traduzido via %s',
        ],

        // Mensagens dinâmicas em JavaScript
        'messages' => [
            'testing'          => '⏳ Testando conexão...',
            'pinging'          => 'Enviando ping...',
            'translating'      => '⏳ Traduzindo...',
            'synthesizing'     => '⚡ Sintetizando...',
            'tldr_displayed'   => '⚡ Resumo exibido',
            'error'            => '❌ Erro',
            'not_translated'   => '⚠️ Não traduzido',
            'network_error'    => '❌ Erro de rede: %s',
        ],
    ],
];