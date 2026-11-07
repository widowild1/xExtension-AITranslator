<?php

return [
    'aitranslator' => [
        'title' => 'Configuración de la extensión AI Translator',
        'intro' => 'Las claves de API y parámetros de conexión siguientes son <strong>compartidos</strong>: configúrelos una sola vez y utilícelos tanto para la <strong>traducción completa</strong> como para el <strong>resumen exprés (TL;DR)</strong>.',

        // Panel de control y mantenimiento
        'dashboard' => [
            'title'               => '📊 Panel de control: Uso y cuotas (últimos 30 días)',
            'last_activity'       => 'Última actividad: %s',
            'no_activity'         => 'Ninguna actividad registrada',
            'stat_success'        => 'Traducciones completadas',
            'stat_tldr'           => 'Resúmenes TL;DR generados',
            'stat_cache'          => 'Entradas en caché SQLite',
            'stat_rate'           => 'Tasa de éxito (%d errores)',
            'cache_distribution'  => 'Distribución de caché por motor:',
            'btn_clear_cache'     => '🗑️ Vaciar caché SQLite',
            'btn_clear_logs'      => '🧹 Purgar registros',
            'warning_title'       => '⚠️ Advertencia sobre el vaciado de la caché:',
            'warning_text'        => 'Eliminar la caché borra permanentemente todas las traducciones y resúmenes TL;DR guardados. Los artículos extranjeros volverán a mostrarse en su idioma original y cualquier lectura posterior volverá a consultar sus API externas (Google Gemini, Mistral, OpenAI, DeepL), consumiendo nuevamente sus cuotas y créditos.',
            'confirm_clear_logs'  => '¿Desea restablecer el historial completo de registros de actividad?',
            'prompt_clear_cache'  => "⚠️ ATENCIÓN: ELIMINACIÓN DEFINITIVA DE LA CACHÉ SQLITE\n\nEsta operación borrará todas las traducciones y resúmenes almacenados localmente.\n\nConsecuencias:\n• Los artículos traducidos volverán a su versión original.\n• Las próximas lecturas consumirán nuevamente tokens y cuotas de API.\n\nPara confirmar la eliminación, escriba PURGER a continuación:",
            'prompt_keyword'      => 'PURGER',
            'prompt_cancelled'    => 'Acción cancelada: palabra clave no reconocida. La caché sigue intacta.',
        ],

        // 1. Preferencias generales
        'general' => [
            'title'              => '1. Preferencias Generales',
            'target_lang'        => 'Idioma de destino principal:',
            'target_lang_help'   => 'Código ISO 639-1 (ej: <code>es</code>, <code>fr</code>, <code>en</code>, <code>de</code>).',
            'pretranslate'       => 'Pretraducción en la ingesta:',
            'pretranslate_desc'  => 'Traducir automáticamente los artículos entrantes durante el cron de FreshRSS',
        ],

        // 2. Asignación de motores
        'engines' => [
            'title'              => '2. Asignación de Motores',
            'primary_label'      => 'Motor de Traducción Principal:',
            'primary_help'       => 'Motor utilizado para traducir el título y la totalidad del cuerpo del artículo.',
            'fallback_label'     => 'Motor de Reserva (Fallback):',
            'fallback_help'      => 'Se utiliza automáticamente si el motor principal devuelve un error o agota su cuota.',
            'tldr_label'         => 'Motor de Síntesis (TL;DR):',
            'tldr_help'          => 'Motor solicitado al hacer clic en el botón « ⚡ Resumen TL;DR ».',
            'opt_auto'           => 'Automático (Selecciona la primera IA disponible)',
            'opt_statistical'    => 'Analizador estadístico local (Sin IA)',
            'opt_libretranslate' => 'LibreTranslate (Local / Autohospedado)',
            'opt_gemini'         => 'Google Gemini',
            'opt_mistral'        => 'Mistral AI',
            'opt_ollama'         => 'Ollama / llama.cpp (Autohospedado local)',
            'opt_openai'         => 'OpenAI',
            'opt_deepl'          => 'DeepL',
        ],

        // 3. Proveedores y banco de pruebas
        'providers' => [
            'title'               => '3. Credenciales y Conexiones de Proveedores',
            'api_key'             => 'Clave API:',
            'api_key_optional'    => 'Clave API (Opcional):',
            'model'               => 'Modelo:',
            'model_local'         => 'Modelo local:',
            'endpoint'            => 'Endpoint OpenAI / Compatible:',
            'url_local'           => 'URL de la API local:',
            'url_libretranslate'  => 'URL LibreTranslate:',
            'account_type'        => 'Tipo de cuenta:',
            'account_free'        => 'DeepL API Free',
            'account_pro'         => 'DeepL API Pro',
            'btn_test_gemini'     => '⚡ Probar conexión Gemini',
            'btn_test_mistral'    => '⚡ Probar conexión Mistral',
            'btn_test_ollama'     => '⚡ Probar servidor local Ollama / llama.cpp',
            'btn_test_openai'     => '⚡ Probar conexión OpenAI',
            'btn_test_lt'         => '⚡ Probar LibreTranslate',
            'btn_test_deepl'      => '⚡ Probar conexión y cuota DeepL',
            'help_gemini_model'   => 'Ej: <code>gemini-3.5-flash-lite</code>, <code>gemini-3.6-flash</code>.',
            'help_mistral_model'  => 'Ej: <code>mistral-small-latest</code>, <code>open-mistral-7b</code>.',
            'help_ollama_url'     => 'Ej: <code>http://localhost:11434</code> (Ollama) o <code>http://localhost:8080</code> (llama.cpp).',
            'help_ollama_model'   => 'Ej: <code>llama3.2:latest</code>, <code>mistral:latest</code>, <code>qwen2.5:latest</code>.',
            'help_openai_model'   => 'Ej: <code>gpt-4o-mini</code>, <code>gpt-4o</code>.',
        ],

        // Acciones
        'actions' => [
            'save' => 'Guardar todos los ajustes',
        ],

        // Barra de herramientas y renderizado del artículo
        'toolbar' => [
            'btn_translate'    => '⚡ Traducir (T)',
            'btn_translated'   => '✓ Traducido',
            'btn_same_lang'    => '✓ Idioma original',
            'btn_tldr'         => '⚡ Resumen TL;DR',
            'btn_tldr_title'   => 'Shift+T: Resumen rápido',
            'btn_split'        => '📖 Vista lado a lado',
            'btn_split_title'  => 'Tecla B: Vista bilingüe lado a lado',
            'btn_stacked'      => '📖 Vista apilada',
            'tldr_title'       => '⚡ Resumen exprés (TL;DR)',
            'original_title'   => 'Versión original',
            'translated_via'   => 'Traducido mediante %s',
        ],

        // Mensajes dinámicos de JavaScript
        'messages' => [
            'testing'          => '⏳ Probando conexión...',
            'pinging'          => 'Haciendo ping...',
            'translating'      => '⏳ Traduciendo...',
            'synthesizing'     => '⚡ Sintetizando...',
            'tldr_displayed'   => '⚡ Resumen mostrado',
            'error'            => '❌ Error',
            'not_translated'   => '⚠️ No traducido',
            'network_error'    => '❌ Error de red: %s',
        ],
    ],
];