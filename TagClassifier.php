<?php

class AITranslator_TagClassifier {

    public static function evaluateArticle($entry, string $targetLang, $ext): string {
        if (!$entry) return 'same_lang';

        $title = $entry->title();
        $content = $entry->content();
        $text = strip_tags($title . ' ' . mb_substr($content, 0, 1500));

        // 1. Détection des alphabets non latins et spécificités germaniques
        if (preg_match('/[\p{Cyrillic}\p{Han}\p{Hiragana}\p{Katakana}\p{Arabic}\p{Greek}]/u', $text)) {
            return 'foreign_confirmed';
        }
        if (preg_match('/[ß]/u', $text)) {
            return 'foreign_confirmed';
        }

        // 2. Exclusion des releases multimédia françaises
        if (preg_match('/\b(truefrench|french|vostfr|vf)\b/i', $title) ||
           (preg_match('/\b(multi|webrip|hdlight|x265|bluray)\b/i', $title) && preg_match('/\b(drame|thriller|horreur|action|comédie|film)\b/i', implode(' ', (array)$entry->tags())))) {
            return 'same_lang';
        }

        // 3. Marqueurs syntaxiques stricts dans le titre
        $cleanTitle = ' ' . mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title)), 'UTF-8') . ' ';
        $hasFrAccents = (bool)preg_match('/[éèêëàâîïôùûçœæ]/u', $title);

        $enStrictWords = [
            ' i ', ' my ', ' you ', ' your ', ' it ', ' its ', ' we ', ' our ', ' they ', ' their ',
            ' onto ', ' into ', ' with ', ' without ', ' from ', ' about ', ' between ', ' through ',
            ' how ', ' why ', ' what ', ' where ', ' when ', ' who ',
            ' still ', ' charging ', ' costs ', ' board ', ' flashed ', ' released ', ' releases ', ' announced ',
            ' update ', ' review ', ' guide ', ' should ', ' could ', ' would ', ' built ', ' makes ',
            ' preview ', ' technology ', ' sued ', ' outperform ', ' subscriber ', ' subscribers ', ' director ',
            ' digest ', ' links '
        ];

        foreach ($enStrictWords as $w) {
            if (strpos($cleanTitle, $w) !== false && !$hasFrAccents) {
                return 'foreign_confirmed';
            }
        }

        $deStrictWords = [
            ' der ', ' die ', ' das ', ' und ', ' wie ', ' warum ', ' was ', ' wer ', ' wo ', ' wann ',
            ' nicht ', ' über ', ' durch ', ' gegen ', ' ohne ', ' zwischen ', ' heute ', ' jetzt ',
            ' immer ', ' wieder ', ' neue ', ' neues ', ' neuen ', ' erste ', ' ersten ', ' mit ',
            ' für ', ' nach ', ' beim ', ' zum ', ' zur ', ' eines ', ' einer ', ' einem ', ' einen '
        ];

        foreach ($deStrictWords as $w) {
            if (strpos($cleanTitle, $w) !== false && !$hasFrAccents) {
                return 'foreign_confirmed';
            }
        }

        if (preg_match('/\b[a-z]{3,}(?:ing|tive|tion)\b/i', $title) && !$hasFrAccents) {
            return 'foreign_confirmed';
        }

        // 4. Analyse fréquentielle du texte complet
        $cleanText = ' ' . mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text)), 'UTF-8') . ' ';
        $hasFrAccentsText = (bool)preg_match('/[éèêëàâîïôùûçœæ]/u', $text);
        $hasDeUmlauts = (bool)preg_match('/[äöüÄÖÜ]/u', $text);

        $frWords = [' le ', ' la ', ' les ', ' du ', ' des ', ' un ', ' une ', ' et ', ' est ', ' dans ', ' pour ', ' par ', ' avec ', ' sur ', ' ce ', ' cette ', ' ces ', ' sont ', ' pas ', ' plus ', ' au ', ' aux '];
        $enWords = [' the ', ' a ', ' an ', ' and ', ' is ', ' are ', ' in ', ' to ', ' for ', ' by ', ' with ', ' on ', ' of ', ' this ', ' that ', ' from ', ' at ', ' as ', ' was ', ' were ', ' out '];
        $deWords = [' der ', ' die ', ' das ', ' und ', ' ist ', ' in ', ' den ', ' von ', ' zu ', ' dem ', ' mit ', ' sich ', ' für ', ' im ', ' nicht ', ' eine ', ' einer ', ' ein ', ' als ', ' auch ', ' es ', ' an ', ' werden ', ' aus ', ' er ', ' hat ', ' dass ', ' sie ', ' nach ', ' wird ', ' bei ', ' um ', ' am ', ' sind '];

        $frCount = 0;
        $enCount = 0;
        $deCount = 0;

        foreach ($frWords as $w) if (strpos($cleanText, $w) !== false) $frCount++;
        foreach ($enWords as $w) if (strpos($cleanText, $w) !== false) $enCount++;
        foreach ($deWords as $w) if (strpos($cleanText, $w) !== false) $deCount++;

        // 5. Validation par les étiquettes de flux
        $tags = method_exists($entry, 'tags') ? (array)$entry->tags() : [];
        $frTagsCount = 0;
        foreach ($tags as $tag) {
            $tagLower = mb_strtolower(trim($tag), 'UTF-8');
            if (preg_match('/[éèêëàâîïôùûçœæ]/u', $tagLower) || in_array($tagLower, ['actualités', 'france', 'politique', 'économie', 'société'], true)) {
                $frTagsCount++;
            }
        }

        if ($frTagsCount >= 2 && $frCount >= 1 && strtolower($targetLang) === 'fr' && !preg_match('/\b(the|and|der|die|das|und)\b/i', $cleanTitle)) {
            return 'same_lang';
        }

        if ($deCount > $frCount) return 'foreign_confirmed';
        if ($enCount > $frCount) return 'foreign_confirmed';
        if ($hasDeUmlauts && $deCount >= 2) return 'foreign_confirmed';

        if ($frCount >= 2 && $frCount > $enCount && $frCount > $deCount) return 'same_lang';
        if ($hasFrAccentsText && $frCount >= 1) return 'same_lang';
        if ($frCount === 0 && !$hasFrAccentsText) return 'foreign_confirmed';

        return ($enCount > 0 || $deCount > 0) ? 'foreign_confirmed' : 'same_lang';
    }

    public static function getCanonicalHash(string $title, string $content, string $targetLang, string $mode = 'translate'): string {
        $normalizedText = trim(strip_tags($title)) . '|' . trim(strip_tags(mb_substr($content, 0, 2000)));
        return hash('sha256', $mode . ':' . $targetLang . ':' . $normalizedText);
    }

    public static function getFeedRule(int $feedId): ?string {
        try {
            $dbPath = dirname(__FILE__) . '/data/translator.db';
            if (!file_exists($dbPath)) return null;

            $pdo = new PDO('sqlite:' . $dbPath);
            $stmt = $pdo->prepare('SELECT rule FROM feed_rules WHERE feed_id = :id LIMIT 1');
            $stmt->execute([':id' => $feedId]);
            $col = $stmt->fetchColumn();
            return $col ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function setFeedRule(int $feedId, string $rule, ?string $targetLang = 'fr'): bool {
        try {
            $dbPath = dirname(__FILE__) . '/data/translator.db';
            $pdo = new PDO('sqlite:' . $dbPath);
            $stmt = $pdo->prepare('INSERT OR REPLACE INTO feed_rules (feed_id, rule, target_lang, updated_at) VALUES (:id, :rule, :lang, :ts)');
            return $stmt->execute([
                ':id'   => $feedId,
                ':rule' => $rule,
                ':lang' => $targetLang,
                ':ts'   => time(),
            ]);
        } catch (\Throwable $e) {
            return false;
        }
    }
}