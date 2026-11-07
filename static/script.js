(function () {
    // Récupération dynamique du jeton de sécurité CSRF FreshRSS
    const getCsrfToken = function () {
        if (typeof window.context !== 'undefined' && window.context && window.context.csrf) {
            return window.context.csrf;
        }
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) {
            return meta.content;
        }
        const input = document.querySelector('input[name="_csrf"]');
        if (input && input.value) {
            return input.value;
        }
        const formInput = document.querySelector('form input[name="_csrf"]');
        if (formInput && formInput.value) {
            return formInput.value;
        }
        return '';
    };

    // Construction de l'URL cible avec contrôleur, action, identifiant et CSRF
    const getEndpointUrl = function (action, id) {
        let base = window.location.pathname || './';
        if (!base.endsWith('.php')) {
            base = base.replace(/\/?$/, '/index.php');
        }
        const csrf = getCsrfToken();
        let url = `${base}?c=aiTranslator&a=${action}`;
        if (id) {
            url += `&id=${encodeURIComponent(id)}`;
        }
        if (csrf) {
            url += `&_csrf=${encodeURIComponent(csrf)}`;
        }
        return url;
    };

    // === RACCOURCIS CLAVIER (T / Shift+T / B) ===
    document.addEventListener('keydown', function (e) {
        if (['INPUT', 'TEXTAREA'].includes(e.target.tagName) || e.target.isContentEditable) {
            return;
        }

        const activeArticle = document.querySelector('.flux_header.active, .flux.current, .flux:hover');
        if (!activeArticle) return;

        const entryId = activeArticle.getAttribute('data-entry-id') || activeArticle.closest('[data-entry-id]')?.getAttribute('data-entry-id');
        if (!entryId) return;

        if (e.key === 't' && !e.shiftKey) {
            e.preventDefault();
            const btn = document.querySelector(`.aitranslator-btn-translate[data-id="${entryId}"]`);
            if (btn) btn.click();
        } else if (e.key === 'T' && e.shiftKey) {
            e.preventDefault();
            const btn = document.querySelector(`.aitranslator-btn-tldr[data-id="${entryId}"]`);
            if (btn) btn.click();
        } else if (e.key === 'b' || e.key === 'B') {
            e.preventDefault();
            const btn = document.querySelector(`.aitranslator-btn-split[data-id="${entryId}"]`);
            if (btn) btn.click();
        }
    }, true);

    // === GESTION DES CLICS AVEC PHASE DE CAPTURE ===
    document.addEventListener('click', function (e) {

        // 1. ACTION : SÉCURITÉ RENFORCÉE PURGE DU CACHE SQLITE
        const clearCacheBtn = e.target.closest('.btn-clear-cache');
        if (clearCacheBtn) {
            const warningMsg = "⚠️ ATTENTION : SUPPRESSION DÉFINITIVE DU CACHE SQLITE\n\n" +
                "Cette opération va effacer l'intégralité des traductions et synthèses TL;DR mémorisées localement.\n\n" +
                "Conséquences opérationnelles :\n" +
                "• Les articles traduits réapparaîtront immédiatement dans leur langue d'origine.\n" +
                "• Les prochaines ouvertures et consultations solliciteront à nouveau vos API externes (Azure, Gemini, Mistral, OpenAI, DeepL).\n" +
                "• Cela consommera de NOUVEAUX tokens et quotas payants/limités.\n\n" +
                "Pour confirmer irrévocablement la suppression du cache, tapez PURGER ci-dessous :";

            const confirmationInput = prompt(warningMsg);
            if (confirmationInput !== 'PURGER') {
                e.preventDefault();
                e.stopPropagation();
                if (confirmationInput !== null) {
                    alert("Action annulée : mot-clé non reconnu. Le cache est intact.");
                }
                return;
            }
            return;
        }

        // 2. ACTION : SÉCURITÉ IMPORT DE BASE SQLITE
        const importDbBtn = e.target.closest('.btn-import-db');
        if (importDbBtn) {
            const warningDb = "⚠️ ATTENTION : ÉCRASEMENT COMPLET DE LA BASE DE DONNÉES\n\n" +
                "L'importation d'un fichier .db va écraser définitivement votre base translator.db actuelle.\n" +
                "Toutes les traductions en cache, règles de flux et termes du glossaire existants seront remplacés.\n\n" +
                "Pour confirmer le remplacement de la base, tapez IMPORTER ci-dessous :";

            const confirmVal = prompt(warningDb);
            if (confirmVal !== 'IMPORTER') {
                e.preventDefault();
                e.stopPropagation();
                if (confirmVal !== null) {
                    alert("Restauration annulée : mot-clé non reconnu.");
                }
                return;
            }
            return;
        }

        // 3. ACTION : SÉCURITÉ PURGE DES LOGS
        const clearLogsBtn = e.target.closest('.btn-clear-logs');
        if (clearLogsBtn) {
            if (!confirm("Réinitialiser l'historique complet des journaux d'activité ?")) {
                e.preventDefault();
                e.stopPropagation();
                return;
            }
            return;
        }

        // 4. ACTION : TRADUCTION À LA DEMANDE
        const transBtn = e.target.closest('.aitranslator-btn-translate');
        if (transBtn) {
            e.preventDefault();
            e.stopPropagation();

            const entryId = transBtn.getAttribute('data-id');
            if (!entryId || transBtn.disabled) return;

            const box = transBtn.closest('.flux_content, .content, article, .flux');
            const toolbar = transBtn.closest('.aitranslator-toolbar');
            const oldLabel = transBtn.innerHTML;
            const csrf = getCsrfToken();

            const lblTranslating = transBtn.getAttribute('data-label-translating') || '⏳ Traduction en cours...';
            const lblTranslated  = transBtn.getAttribute('data-label-translated') || '✓ Traduit en FR';
            const lblError       = toolbar?.getAttribute('data-msg-error') || '❌ Erreur';

            transBtn.innerHTML = lblTranslating;
            transBtn.disabled = true;

            fetch(getEndpointUrl('translate', entryId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrf
                },
                body: JSON.stringify({
                    id: entryId,
                    _csrf: csrf
                })
            })
            .then(r => {
                if (!r.ok) {
                    return r.json().catch(() => { throw new Error(`HTTP ${r.status}`); }).then(d => { throw new Error(d.error || `HTTP ${r.status}`); });
                }
                return r.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    transBtn.className = 'btn btn-sm aitranslator-btn-translate badge-translated';
                    transBtn.innerHTML = lblTranslated;
                    transBtn.disabled = false;
                    transBtn.title = `Traduit via ${data.provider}`;

                    if (box) {
                        const titleElem = box.querySelector('.title, h1, h2, .flux_header .title a');
                        if (titleElem && data.title) {
                            titleElem.innerHTML = data.title;
                        }

                        const target = box.querySelector('.aitranslator-split-wrapper, .aitranslator-article-body, .aitranslator-body');
                        if (target && data.contentHtml) {
                            target.outerHTML = data.contentHtml;
                        }
                    }
                } else {
                    throw new Error(data.error || 'Erreur serveur');
                }
            })
            .catch(err => {
                transBtn.innerHTML = lblError;
                transBtn.title = err.message;
                transBtn.disabled = false;
                setTimeout(() => { transBtn.innerHTML = oldLabel; }, 3500);
                console.error('Erreur traduction :', err);
            });
            return;
        }

        // 5. ACTION : RÉSUMÉ EXPRESS (TL;DR)
        const tldrBtn = e.target.closest('.aitranslator-btn-tldr');
        if (tldrBtn) {
            e.preventDefault();
            e.stopPropagation();

            const entryId = tldrBtn.getAttribute('data-id');
            if (!entryId || tldrBtn.disabled) return;

            const box = tldrBtn.closest('.flux_content, .content, article, .flux');
            const toolbar = tldrBtn.closest('.aitranslator-toolbar');
            const oldLabel = tldrBtn.innerHTML;
            const csrf = getCsrfToken();

            const lblSynthesizing = tldrBtn.getAttribute('data-label-synthesizing') || '⚡ Synthèse...';
            const lblDone         = tldrBtn.getAttribute('data-label-done') || '⚡ Résumé affiché';
            const lblError        = toolbar?.getAttribute('data-msg-error') || '❌ Erreur';

            tldrBtn.innerHTML = lblSynthesizing;
            tldrBtn.disabled = true;

            fetch(getEndpointUrl('tldr', entryId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrf
                },
                body: JSON.stringify({
                    id: entryId,
                    _csrf: csrf
                })
            })
            .then(r => {
                if (!r.ok) {
                    return r.json().catch(() => { throw new Error(`HTTP ${r.status}`); }).then(d => { throw new Error(d.error || `HTTP ${r.status}`); });
                }
                return r.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    tldrBtn.innerHTML = lblDone;
                    tldrBtn.disabled = false;

                    if (box) {
                        const existingTldr = box.querySelector('.aitranslator-tldr-box');
                        if (existingTldr) existingTldr.remove();

                        const bar = box.querySelector('.aitranslator-toolbar');
                        if (bar) {
                            bar.insertAdjacentHTML('afterend', data.tldrHtml);
                        }
                    }
                } else {
                    throw new Error(data.error || 'Erreur résumé');
                }
            })
            .catch(err => {
                tldrBtn.innerHTML = lblError;
                tldrBtn.title = err.message;
                tldrBtn.disabled = false;
                setTimeout(() => { tldrBtn.innerHTML = oldLabel; }, 3500);
                console.error('Erreur TL;DR :', err);
            });
            return;
        }

        // 6. ACTION : VUE BILINGUE CÔTE À CÔTE (SPLIT VIEW)
        const splitBtn = e.target.closest('.aitranslator-btn-split');
        if (splitBtn) {
            e.preventDefault();
            e.stopPropagation();

            const box = splitBtn.closest('.flux_content, .content, article, .flux');
            const toolbar = splitBtn.closest('.aitranslator-toolbar');
            if (!box) return;

            const origBox = box.querySelector('.aitranslator-original-box');
            const transBox = box.querySelector('.aitranslator-translated-content, .aitranslator-body');

            const lblSplit   = splitBtn.getAttribute('data-label-split') || '📖 Vue côte à côte';
            const lblStacked = splitBtn.getAttribute('data-label-stacked') || '📖 Vue empilée';
            const lblNotTrans= toolbar?.getAttribute('data-msg-not-translated') || '⚠️ Non traduit';

            if (!origBox || !transBox) {
                const originalText = splitBtn.innerHTML;
                splitBtn.innerHTML = lblNotTrans;
                splitBtn.style.opacity = '0.7';
                setTimeout(() => {
                    splitBtn.innerHTML = originalText;
                    splitBtn.style.opacity = '1';
                }, 1800);
                return;
            }

            let wrapper = box.querySelector('.aitranslator-split-wrapper');
            if (!wrapper) {
                wrapper = document.createElement('div');
                wrapper.className = 'aitranslator-split-wrapper';
                transBox.parentNode.insertBefore(wrapper, transBox);
                wrapper.appendChild(transBox);

                const hr = box.querySelector('hr, .aitranslator-divider');
                if (hr) {
                    hr.className = 'aitranslator-divider';
                    wrapper.appendChild(hr);
                }
                wrapper.appendChild(origBox);
            }

            const isNowActive = wrapper.classList.toggle('active');
            splitBtn.classList.toggle('active', isNowActive);

            if (isNowActive) {
                origBox.open = true;
                splitBtn.innerHTML = lblStacked;
            } else {
                splitBtn.innerHTML = lblSplit;
            }
            return;
        }

        // 7. ACTION : BANC D'ESSAI DES FOURNISSEURS (PAGE CONFIGURATION)
        const testBtn = e.target.closest('.btn-test-provider');
        if (testBtn) {
            e.preventDefault();
            e.stopPropagation();

            const provider = testBtn.getAttribute('data-provider');
            if (!provider || testBtn.disabled) return;

            const statusSpan = document.getElementById(`test-status-${provider}`);
            const oldText = testBtn.innerHTML;
            const csrf = getCsrfToken();

            testBtn.disabled = true;
            testBtn.innerHTML = '⏳ Test en cours...';
            if (statusSpan) {
                statusSpan.style.color = '#777';
                statusSpan.innerHTML = 'Ping en cours...';
            }

            const payload = {
                provider: provider,
                _csrf: csrf
            };

            if (provider === 'azure') {
                payload.api_key = document.getElementById('azure_api_key')?.value || '';
                payload.region = document.getElementById('azure_region')?.value || '';
            } else if (provider === 'nllb') {
                payload.api_url = document.getElementById('nllb_api_url')?.value || '';
            } else if (provider === 'gemini') {
                payload.api_key = document.getElementById('gemini_api_key')?.value || '';
                payload.model = document.getElementById('gemini_model')?.value || '';
            } else if (provider === 'mistral') {
                payload.api_key = document.getElementById('mistral_api_key')?.value || '';
                payload.model = document.getElementById('mistral_model')?.value || '';
            } else if (provider === 'ollama') {
                payload.api_url = document.getElementById('ollama_api_url')?.value || '';
                payload.model = document.getElementById('ollama_model')?.value || '';
            } else if (provider === 'openai') {
                payload.api_key = document.getElementById('openai_api_key')?.value || '';
                payload.model = document.getElementById('openai_model')?.value || '';
                payload.api_url = document.getElementById('openai_api_url')?.value || '';
            } else if (provider === 'libretranslate') {
                payload.api_url = document.getElementById('libretranslate_api_url')?.value || '';
                payload.api_key = document.getElementById('libretranslate_api_key')?.value || '';
            } else if (provider === 'deepl') {
                payload.api_key = document.getElementById('deepl_api_key')?.value || '';
                payload.api_type = document.getElementById('deepl_api_type')?.value || 'free';
            }

            fetch(getEndpointUrl('testProvider'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrf
                },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(data => {
                testBtn.disabled = false;
                testBtn.innerHTML = oldText;
                if (!statusSpan) return;

                if (data.status === 'success') {
                    statusSpan.style.color = '#2e7d32';
                    statusSpan.innerHTML = `✅ ${data.message}`;
                } else {
                    statusSpan.style.color = '#c62828';
                    statusSpan.innerHTML = `❌ ${data.error || 'Échec de connexion'}`;
                }
            })
            .catch(err => {
                testBtn.disabled = false;
                testBtn.innerHTML = oldText;
                if (statusSpan) {
                    statusSpan.style.color = '#c62828';
                    statusSpan.innerHTML = `❌ Erreur réseau : ${err.message}`;
                }
                console.error('Erreur banc d\'essai :', err);
            });
        }
    }, true);
})();