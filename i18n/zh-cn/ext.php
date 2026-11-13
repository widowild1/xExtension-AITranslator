<?php

return [
    'aitranslator' => [
        'title' => 'AI Translator 扩展设置',
        'intro' => '以下 API 密钥与连接配置为<strong>通用共享</strong>：只需配置一次，即可同时用于<strong>全文翻译</strong>与<strong>即时摘要 (TL;DR)</strong>。',

        // 统计面板与维护
        'dashboard' => [
            'title'               => '📊 仪表盘：使用量与额度统计（最近 30 天）',
            'last_activity'       => '最近活动：%s',
            'no_activity'         => '暂无活动记录',
            'stat_success'        => '翻译成功次数',
            'stat_tldr'           => 'TL;DR 摘要生成次数',
            'stat_cache'          => 'SQLite 缓存条目',
            'stat_rate'           => '成功率（%d 次失败）',
            'cache_distribution'  => '各引擎缓存分布：',
            'btn_clear_cache'     => '🗑️ 清空 SQLite 缓存',
            'btn_clear_logs'      => '🧹 清空活动日志',
            'warning_title'       => '⚠️ 清空缓存风险提示：',
            'warning_text'        => '清空缓存将永久删除所有已存储的翻译与 TL;DR 摘要。外语文章将恢复为原文显示，再次阅读时将重新请求外部 API（Gemini、Mistral、OpenAI、DeepL），消耗额度与费用。',
            'confirm_clear_logs'  => '确定要清空全部活动日志历史记录吗？',
            'prompt_clear_cache'  => "⚠️ 警告：即将永久清空 SQLite 本地缓存\n\n此操作将清除所有本地保存的翻译与摘要数据。\n\n操作后果：\n• 已翻译文章将还原为原文。\n• 再次访问将重新调用外部 API 并消耗额度。\n\n如确认清除，请在下方输入 PURGER ：",
            'prompt_keyword'      => 'PURGER',
            'prompt_cancelled'    => '操作已取消：口令不匹配，缓存未变动。',
        ],

        // 1. 通用偏好设置
        'general' => [
            'title'              => '1. 通用偏好设置',
            'target_lang'        => '主要目标语言：',
            'target_lang_help'   => 'ISO 639-1 代码（例如：<code>zh</code>, <code>en</code>, <code>fr</code>, <code>de</code>）。',
            'pretranslate'       => '抓取时预翻译：',
            'pretranslate_desc'  => '在 FreshRSS 定时任务抓取新文章时自动预翻译',
        ],

        // 2. 引擎分配
        'engines' => [
            'title'              => '2. 引擎角色分配',
            'primary_label'      => '首选全文翻译引擎：',
            'primary_help'       => '用于翻译标题及整篇文章正文的引擎。',
            'fallback_label'     => '备用容灾引擎 (Fallback)：',
            'fallback_help'      => '当首选引擎报错或额度耗尽时自动切换接管。',
            'fallback2_label'     => '第二备用容灾引擎 (Fallback 2)：',
            'fallback2_help'      => '当首选引擎和第一备用引擎均失败时作为最后手段自动启用。',
            'opt_none'            => '无（已禁用）',
            'multiplex_label'     => '三引擎并行多路复用：',
            'multiplex_desc'      => '批量处理时同时使用全部 3 个已配置引擎',
            'multiplex_help'      => '仅当 3 个引擎互不相同且全部通过健康检查时才会启用。',
            'tldr_label'         => '即时摘要引擎 (TL;DR)：',
            'tldr_help'          => '点击「 ⚡ TL;DR 摘要 」按钮时调用的模型。',
            'opt_auto'           => '自动优选（自动调用首个可用的大模型）',
            'opt_statistical'    => '本地离线统计摘要（不调用 AI）',
            'opt_libretranslate' => 'LibreTranslate（本地 / 自建服务）',
            'opt_gemini'         => 'Google Gemini',
            'opt_mistral'        => 'Mistral AI',
            'opt_ollama'         => 'Ollama / llama.cpp（本地自托管模型）',
            'opt_openai'         => 'OpenAI',
            'opt_deepl'          => 'DeepL',
        ],

        // 3. 服务商凭据与连通性测试
        'providers' => [
            'title'               => '3. 服务商凭据与连接设置',
            'api_key'             => 'API 密钥：',
            'api_key_optional'    => 'API 密钥（选填）：',
            'model'               => '模型：',
            'model_local'         => '本地模型名称：',
            'endpoint'            => 'OpenAI / 兼容接口地址：',
            'url_local'           => '本地 API 服务地址：',
            'url_libretranslate'  => 'LibreTranslate 地址：',
            'account_type'        => '订阅类型：',
            'account_free'        => 'DeepL API Free',
            'account_pro'         => 'DeepL API Pro',
            'btn_test_gemini'     => '⚡ 测试 Gemini 连通性',
            'btn_test_mistral'    => '⚡ 测试 Mistral 连通性',
            'btn_test_ollama'     => '⚡ 测试 Ollama / llama.cpp 本地服务',
            'btn_test_openai'     => '⚡ 测试 OpenAI 连通性',
            'btn_test_lt'         => '⚡ 测试 LibreTranslate',
            'btn_test_deepl'      => '⚡ 测试 DeepL 连通性与额度',
            'help_gemini_model'   => '示例：<code>gemini-3.5-flash-lite</code>, <code>gemini-3.6-flash</code>。',
            'help_mistral_model'  => '示例：<code>mistral-small-latest</code>, <code>open-mistral-7b</code>。',
            'help_ollama_url'     => '示例：<code>http://localhost:11434</code> (Ollama) 或 <code>http://localhost:8080</code> (llama.cpp)。',
            'help_ollama_model'   => '示例：<code>llama3.2:latest</code>, <code>mistral:latest</code>, <code>qwen2.5:latest</code>。',
            'help_openai_model'   => '示例：<code>gpt-4o-mini</code>, <code>gpt-4o</code>。',
        ],

        // 操作动作
        'actions' => [
            'save' => '保存所有设置',
        ],

        // 阅读器工具栏与渲染
        'toolbar' => [
            'btn_translate'    => '⚡ 翻译文章 (T)',
            'btn_translated'   => '✓ 已翻译',
            'btn_same_lang'    => '✓ 母语无需翻译',
            'btn_tldr'         => '⚡ TL;DR 摘要',
            'btn_tldr_title'   => '快捷键 Shift+T：生成核心摘要',
            'btn_split'        => '📖 双语并排对比',
            'btn_split_title'  => '快捷键 B：切换左右分栏与单栏',
            'btn_stacked'      => '📖 恢复单栏排版',
            'tldr_title'       => '⚡ 核心速览 (TL;DR)',
            'original_title'   => '查看原文内容',
            'translated_via'   => '由 %s 翻译',
        ],

        // JavaScript 动态交互提示
        'messages' => [
            'testing'          => '⏳ 正在检测连接...',
            'pinging'          => '正在发起测试...',
            'translating'      => '⏳ 正在翻译中...',
            'synthesizing'     => '⚡ 正在生成摘要...',
            'tldr_displayed'   => '⚡ 摘要已生成',
            'error'            => '❌ 出错',
            'not_translated'   => '⚠️ 尚未翻译',
            'network_error'    => '❌ 网络异常：%s',
        ],
    ],
];