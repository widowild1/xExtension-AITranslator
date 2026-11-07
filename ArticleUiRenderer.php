<?php

class AITranslator_ArticleUiRenderer {

    public static function attachToolbar($entry, bool $isTranslated, bool $isSameLang): void {
        $entryId = (string)$entry->id();

        $badgeClass = $isTranslated ? 'badge-translated' : ($isSameLang ? 'badge-samelang' : 'badge-pending');
        $badgeText  = $isTranslated ? _t('ext.aitranslator.toolbar.btn_translated') : ($isSameLang ? _t('ext.aitranslator.toolbar.btn_same_lang') : _t('ext.aitranslator.toolbar.btn_translate'));

        $labelTranslate  = htmlspecialchars(_t('ext.aitranslator.toolbar.btn_translate'));
        $labelTranslated = htmlspecialchars(_t('ext.aitranslator.toolbar.btn_translated'));
        $labelTranslating= htmlspecialchars(_t('ext.aitranslator.messages.translating'));
        $labelTldr       = htmlspecialchars(_t('ext.aitranslator.toolbar.btn_tldr'));
        $labelSynthesizing= htmlspecialchars(_t('ext.aitranslator.messages.synthesizing'));
        $labelTldrDone   = htmlspecialchars(_t('ext.aitranslator.messages.tldr_displayed'));
        $labelSplit      = htmlspecialchars(_t('ext.aitranslator.toolbar.btn_split'));
        $labelStacked    = htmlspecialchars(_t('ext.aitranslator.toolbar.btn_stacked'));
        $labelNotTrans   = htmlspecialchars(_t('ext.aitranslator.messages.not_translated'));
        $labelError      = htmlspecialchars(_t('ext.aitranslator.messages.error'));

        $toolbarHtml  = '<div class="aitranslator-toolbar" data-entry-id="' . htmlspecialchars($entryId) . '"'
            . ' data-msg-error="' . $labelError . '"'
            . ' data-msg-not-translated="' . $labelNotTrans . '">';

        $toolbarHtml .= '<button type="button" class="btn btn-sm aitranslator-btn-translate ' . $badgeClass . '"'
            . ' data-id="' . htmlspecialchars($entryId) . '"'
            . ' data-label-translating="' . $labelTranslating . '"'
            . ' data-label-translated="' . $labelTranslated . '"'
            . ' data-label-default="' . $labelTranslate . '">'
            . htmlspecialchars($badgeText) . '</button>';

        $toolbarHtml .= '<button type="button" class="btn btn-sm aitranslator-btn-tldr"'
            . ' data-id="' . htmlspecialchars($entryId) . '"'
            . ' data-label-synthesizing="' . $labelSynthesizing . '"'
            . ' data-label-done="' . $labelTldrDone . '"'
            . ' title="' . htmlspecialchars(_t('ext.aitranslator.toolbar.btn_tldr_title')) . '">'
            . $labelTldr . '</button>';

        $toolbarHtml .= '<button type="button" class="btn btn-sm aitranslator-btn-split"'
            . ' data-id="' . htmlspecialchars($entryId) . '"'
            . ' data-label-split="' . $labelSplit . '"'
            . ' data-label-stacked="' . $labelStacked . '"'
            . ' title="' . htmlspecialchars(_t('ext.aitranslator.toolbar.btn_split_title')) . '">'
            . $labelSplit . '</button>';

        $toolbarHtml .= '</div>';

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