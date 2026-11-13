<?php

class AITranslator_ArticleUiRenderer {

    public static function extractFallbackTitle(string $rawContent, int $maxLen = 90): string {
        $clean = preg_replace('/<(button|script|style)\b[^>]*>.*?<\/\1>/is', ' ', $rawContent);
        $clean = preg_replace('/<div[^>]*class=["\'][^"\']*aitranslator-(?:toolbar|tldr-box)[^"\']*["\'][^>]*>.*?<\/div>/is', ' ', (string)$clean);
        $text = strip_tags((string)$clean);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/^(?:📄\s*Récupérer le texte complet|✓\s*En français|⚡\s*Résumé TL;DR|📖\s*Vue côte à côte)+\s*/u', '', trim($text));
        $text = trim((string)preg_replace('/\s+/u', ' ', (string)$text));

        if ($text === '') {
            return '';
        }

        if (mb_strlen($text, 'UTF-8') > $maxLen) {
            $cut = mb_substr($text, 0, $maxLen, 'UTF-8');
            $cut = preg_replace('/\s+\S*$/u', '', $cut) ?: $cut;
            return rtrim($cut, " \t\n\r\0\x0B.,;:") . '…';
        }

        return $text;
    }

    public static function attachToolbar($entry, bool $isTranslated, bool $isSameLang, ?string $cachedTldrHtml = null, bool $autoTldr = false): void {
        $entryId = (string)$entry->id();

        $badgeClass = $isTranslated ? 'badge-translated' : ($isSameLang ? 'badge-samelang' : 'badge-pending');
        $badgeText  = $isTranslated ? _t('ext.aitranslator.toolbar.btn_translated') : ($isSameLang ? _t('ext.aitranslator.toolbar.btn_same_lang') : _t('ext.aitranslator.toolbar.btn_translate'));

        $labelTranslate   = htmlspecialchars(_t('ext.aitranslator.toolbar.btn_translate'));
        $labelTranslated  = htmlspecialchars(_t('ext.aitranslator.toolbar.btn_translated'));
        $labelTranslating = htmlspecialchars(_t('ext.aitranslator.messages.translating'));
        $labelTldr        = htmlspecialchars(_t('ext.aitranslator.toolbar.btn_tldr'));
        $labelSynthesizing= htmlspecialchars(_t('ext.aitranslator.messages.synthesizing'));
        $labelTldrDone    = htmlspecialchars(_t('ext.aitranslator.messages.tldr_displayed'));
        $labelSplit       = htmlspecialchars(_t('ext.aitranslator.toolbar.btn_split'));
        $labelStacked     = htmlspecialchars(_t('ext.aitranslator.toolbar.btn_stacked'));
        $labelNotTrans    = htmlspecialchars(_t('ext.aitranslator.messages.not_translated'));
        $labelError       = htmlspecialchars(_t('ext.aitranslator.messages.error'));

        $hasCachedTldr = ($cachedTldrHtml !== null && $cachedTldrHtml !== '');
        $autoAttr = (!$hasCachedTldr && $autoTldr) ? ' data-auto-tldr="1"' : '';

        $toolbarHtml  = '<div class="aitranslator-toolbar" data-entry-id="' . htmlspecialchars($entryId) . '"'
            . $autoAttr
            . ' data-msg-error="' . $labelError . '"'
            . ' data-msg-not-translated="' . $labelNotTrans . '">';

        $toolbarHtml .= '<button type="button" class="btn btn-sm aitranslator-btn-translate ' . $badgeClass . '"'
            . ' data-id="' . htmlspecialchars($entryId) . '"'
            . ' data-label-translating="' . $labelTranslating . '"'
            . ' data-label-translated="' . $labelTranslated . '"'
            . ' data-label-default="' . $labelTranslate . '">'
            . htmlspecialchars($badgeText) . '</button>';

        $tldrClass = $hasCachedTldr ? ' aitranslator-tldr-ready' : '';
        $tldrStyle = $hasCachedTldr ? ' style="border-color: #22c55e; color: #15803d; font-weight: 600;"' : '';
        $tldrTitle = $hasCachedTldr ? 'Résumé express 1-Passe déjà prêt en cache (affichage instantané)' : _t('ext.aitranslator.toolbar.btn_tldr_title');
        $tldrText  = $hasCachedTldr ? $labelTldrDone : $labelTldr;

        $toolbarHtml .= '<button type="button" class="btn btn-sm aitranslator-btn-tldr' . $tldrClass . '"' . $tldrStyle
            . ' data-id="' . htmlspecialchars($entryId) . '"'
            . ' data-label-synthesizing="' . $labelSynthesizing . '"'
            . ' data-label-done="' . $labelTldrDone . '"'
            . ' title="' . htmlspecialchars($tldrTitle) . '">'
            . $tldrText . '</button>';

        $toolbarHtml .= '<button type="button" class="btn btn-sm aitranslator-btn-split"'
            . ' data-id="' . htmlspecialchars($entryId) . '"'
            . ' data-label-split="' . $labelSplit . '"'
            . ' data-label-stacked="' . $labelStacked . '"'
            . ' title="' . htmlspecialchars(_t('ext.aitranslator.toolbar.btn_split_title')) . '">'
            . $labelSplit . '</button>';

        $toolbarHtml .= '</div>';

        if ($hasCachedTldr) {
            $toolbarHtml .= $cachedTldrHtml;
        }

        $currentContent = $entry->content();
        if (!$isTranslated && strpos($currentContent, 'aitranslator-split-wrapper') === false) {
            $currentContent = '<div class="aitranslator-article-body">' . $currentContent . '</div>';
        }

        $entry->_content($toolbarHtml . $currentContent);
    }

    public static function formatTranslatedContent(string $translatedContent, string $originalContent, string $provider, string $entryId): string {
        $metaText = sprintf(_t('ext.aitranslator.toolbar.translated_via'), '<strong>' . htmlspecialchars(ucfirst($provider)) . '</strong>');

        $banner  = '<div class="aitranslator-meta-badge">' . $metaText . '</div>';
        return $banner . '<div class="aitranslator-body" data-id="' . htmlspecialchars($entryId) . '">' . $translatedContent . '</div>';
    }
}
