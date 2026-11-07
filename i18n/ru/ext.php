<?php

return [
    'aitranslator' => [
        'title' => 'Настройка расширения AI Translator',
        'intro' => 'Ключи API и параметры подключения ниже являются <strong>общими</strong>: настройте их один раз, и они будут использоваться как для <strong>полного перевода</strong>, так и для <strong>экспресс-сводки (TL;DR)</strong>.',

        // Панель управления и обслуживание
        'dashboard' => [
            'title'               => '📊 Панель управления: Использование и лимиты (последние 30 дней)',
            'last_activity'       => 'Последняя активность: %s',
            'no_activity'         => 'Активность не зафиксирована',
            'stat_success'        => 'Успешных переводов',
            'stat_tldr'           => 'Создано сводок TL;DR',
            'stat_cache'          => 'Записей в кэше SQLite',
            'stat_rate'           => 'Успешность (%d ошибок)',
            'cache_distribution'  => 'Распределение кэша по моделям:',
            'btn_clear_cache'     => '🗑️ Очистить кэш SQLite',
            'btn_clear_logs'      => '🧹 Очистить журналы',
            'warning_title'       => '⚠️ Предупреждение об очистке кэша:',
            'warning_text'        => 'Очистка кэша безвозвратно удаляет все сохраненные переводы и сводки TL;DR. Иностранные статьи снова будут отображаться на языке оригинала, а их повторное открытие потребует новых запросов к внешним API (Gemini, Mistral, OpenAI, DeepL) с расходом лимитов и токенов.',
            'confirm_clear_logs'  => 'Вы действительно хотите сбросить всю историю журналов активности?',
            'prompt_clear_cache'  => "⚠️ ВНИМАНИЕ: БЕЗВОЗВРАТНОЕ УДАЛЕНИЕ КЭША SQLITE\n\nЭто действие удалит все локально сохраненные переводы и краткие сводки.\n\nПоследствия:\n• Переведенные статьи вернутся к языку оригинала.\n• Новые запросы снова будут расходовать токены и квоты API.\n\nДля подтверждения очистки введите PURGER ниже:",
            'prompt_keyword'      => 'PURGER',
            'prompt_cancelled'    => 'Действие отменено: неверное кодовое слово. Кэш не изменен.',
        ],

        // 1. Основные настройки
        'general' => [
            'title'              => '1. Основные настройки',
            'target_lang'        => 'Основной целевой язык:',
            'target_lang_help'   => 'Код ISO 639-1 (например: <code>ru</code>, <code>en</code>, <code>fr</code>, <code>de</code>).',
            'pretranslate'       => 'Предварительный перевод при получении:',
            'pretranslate_desc'  => 'Автоматически переводить новые статьи во время выполнения cron FreshRSS',
        ],

        // 2. Назначение движков
        'engines' => [
            'title'              => '2. Назначение движков',
            'primary_label'      => 'Основной движок перевода:',
            'primary_help'       => 'Используется для перевода заголовка и полного текста статьи.',
            'fallback_label'     => 'Резервный движок (Fallback):',
            'fallback_help'      => 'Включается автоматически при ошибке или исчерпании лимита основного движка.',
            'tldr_label'         => 'Движок экспресс-сводки (TL;DR):',
            'tldr_help'          => 'Используется при нажатии на кнопку « ⚡ Сводка TL;DR ».',
            'opt_auto'           => 'Автоматически (Выбирает первый доступный ИИ)',
            'opt_statistical'    => 'Локальный статистический анализ (Без ИИ)',
            'opt_libretranslate' => 'LibreTranslate (Локальный / Self-hosted)',
            'opt_gemini'         => 'Google Gemini',
            'opt_mistral'        => 'Mistral AI',
            'opt_ollama'         => 'Ollama / llama.cpp (Локальный сервер)',
            'opt_openai'         => 'OpenAI',
            'opt_deepl'          => 'DeepL',
        ],

        // 3. Провайдеры и тестирование подключения
        'providers' => [
            'title'               => '3. Учетные данные и подключение провайдеров',
            'api_key'             => 'Ключ API:',
            'api_key_optional'    => 'Ключ API (Необязательно):',
            'model'               => 'Модель:',
            'model_local'         => 'Локальная модель:',
            'endpoint'            => 'Эндпоинт OpenAI / Совместимый:',
            'url_local'           => 'URL локального API:',
            'url_libretranslate'  => 'URL LibreTranslate:',
            'account_type'        => 'Тип подписки:',
            'account_free'        => 'DeepL API Free',
            'account_pro'         => 'DeepL API Pro',
            'btn_test_gemini'     => '⚡ Проверить подключение к Gemini',
            'btn_test_mistral'    => '⚡ Проверить подключение к Mistral',
            'btn_test_ollama'     => '⚡ Проверить локальный сервер Ollama / llama.cpp',
            'btn_test_openai'     => '⚡ Проверить подключение к OpenAI',
            'btn_test_lt'         => '⚡ Проверить LibreTranslate',
            'btn_test_deepl'      => '⚡ Проверить подключение и квоту DeepL',
            'help_gemini_model'   => 'Пример: <code>gemini-3.5-flash-lite</code>, <code>gemini-3.6-flash</code>.',
            'help_mistral_model'  => 'Пример: <code>mistral-small-latest</code>, <code>open-mistral-7b</code>.',
            'help_ollama_url'     => 'Пример: <code>http://localhost:11434</code> (Ollama) или <code>http://localhost:8080</code> (llama.cpp).',
            'help_ollama_model'   => 'Пример: <code>llama3.2:latest</code>, <code>mistral:latest</code>, <code>qwen2.5:latest</code>.',
            'help_openai_model'   => 'Пример: <code>gpt-4o-mini</code>, <code>gpt-4o</code>.',
        ],

        // Действия
        'actions' => [
            'save' => 'Сохранить все настройки',
        ],

        // Панель инструментов и отображение статей
        'toolbar' => [
            'btn_translate'    => '⚡ Перевести (T)',
            'btn_translated'   => '✓ Переведено',
            'btn_same_lang'    => '✓ Язык оригинала',
            'btn_tldr'         => '⚡ Сводка TL;DR',
            'btn_tldr_title'   => 'Shift+T: Быстрая выжимка',
            'btn_split'        => '📖 Режим параллельного чтения',
            'btn_split_title'  => 'Клавиша B: Двуязычный вид бок о бок',
            'btn_stacked'      => '📖 Обычный вид',
            'tldr_title'       => '⚡ Экспресс-сводка (TL;DR)',
            'original_title'   => 'Оригинальная версия',
            'translated_via'   => 'Переведено через %s',
        ],

        // Динамические сообщения JavaScript
        'messages' => [
            'testing'          => '⏳ Проверка связи...',
            'pinging'          => 'Отправка запроса...',
            'translating'      => '⏳ Выполняется перевод...',
            'synthesizing'     => '⚡ Генерация сводки...',
            'tldr_displayed'   => '⚡ Сводка сформирована',
            'error'            => '❌ Ошибка',
            'not_translated'   => '⚠️ Не переведено',
            'network_error'    => '❌ Ошибка сети: %s',
        ],
    ],
];