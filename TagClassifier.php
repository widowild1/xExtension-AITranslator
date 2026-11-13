<?php

class AITranslator_TagClassifier {

    /**
     * Évalue si un article est dans la langue cible ou dans une langue étrangère à traduire.
     * Retourne 'same_lang' ou 'foreign_confirmed'.
     */
    public static function evaluateArticle($entry, string $targetLang, $ext): string {
        if (!$entry) {
            return 'same_lang';
        }

        $targetBase = self::normalizeLangCode($targetLang);
        $title = (string)$entry->title();
        $content = (string)$entry->content();

        // Nettoyage des métadonnées techniques RSS (pieds de page Reddit/Lemmy)
        $cleanBody = strip_tags(mb_substr($content, 0, 1500));
        $cleanBody = preg_replace(
            '/\bsubmitted by\s+\/u\/[^\s]+(?:\s+to\s+\/?r\/[^\s]+)?\s*\[link\]\s*\[comments\]/i',
            '',
            $cleanBody
        );
        $text = trim($title . ' ' . $cleanBody);

        // 1. Exclusion explicite des releases multimédia francophones
        if ($targetBase === 'fr') {
            if (preg_match('/\b(truefrench|french|vostfr|vf)\b/i', $title) ||
               (preg_match('/\b(multi|webrip|hdlight|x265|bluray)\b/i', $title) &&
                preg_match('/\b(drame|thriller|horreur|action|comédie|film)\b/i', implode(' ', (array)$entry->tags())))) {
                return 'same_lang';
            }
        }

        // 2. Détection universelle des écritures non latines (Unicode scripts)
        $scriptMap = [
            '/[\p{Cyrillic}]/u'             => ['ru', 'uk', 'bg', 'sr', 'mk', 'be', 'kk', 'ky', 'tg', 'mn'],
            '/[\p{Hiragana}\p{Katakana}]/u' => ['ja'],
            '/[\p{Hangul}]/u'               => ['ko'],
            '/[\p{Han}]/u'                  => ['zh', 'ja'],
            '/[\p{Arabic}]/u'               => ['ar', 'fa', 'ur', 'ps', 'ku'],
            '/[\p{Greek}]/u'                => ['el'],
            '/[\p{Hebrew}]/u'               => ['he', 'yi'],
            '/[\p{Thai}]/u'                 => ['th'],
            '/[\p{Devanagari}]/u'           => ['hi', 'mr', 'ne', 'sa'],
            '/[\p{Bengali}]/u'              => ['bn', 'as'],
            '/[\p{Georgian}]/u'             => ['ka'],
            '/[\p{Armenian}]/u'             => ['hy'],
            '/[\p{Tamil}]/u'                => ['ta'],
            '/[\p{Telugu}]/u'               => ['te'],
            '/[\p{Gujarati}]/u'             => ['gu'],
            '/[\p{Gurmukhi}]/u'             => ['pa'],
            '/[\p{Kannada}]/u'              => ['kn'],
            '/[\p{Malayalam}]/u'            => ['ml'],
            '/[\p{Sinhala}]/u'              => ['si'],
            '/[\p{Khmer}]/u'                => ['km'],
            '/[\p{Lao}]/u'                  => ['lo'],
            '/[\p{Myanmar}]/u'              => ['my'],
            '/[\p{Tibetan}]/u'              => ['bo'],
            '/[\p{Ethiopic}]/u'             => ['am', 'ti'],
        ];

        foreach ($scriptMap as $pattern => $scriptLangs) {
            if (preg_match($pattern, $text)) {
                return in_array($targetBase, $scriptLangs, true) ? 'same_lang' : 'foreign_confirmed';
            }
        }

        // 3. Marqueurs orthographiques exclusifs aux langues à alphabet latin
        $uniqueOrthography = [
            '/[ß]/u'                               => ['de'],
            '/[¿¡ñÑ]/u'                            => ['es'],
            '/[ãõÃÕ]/u'                            => ['pt'],
            '/[łŁąćęńśźżĄĆĘŃŚŹŻ]/u'                => ['pl'],
            '/[řčšžůďťňŘČŠŽŮĎŤŇ]/u'                => ['cs', 'sk'],
            '/[șțȘȚăĂ]/u'                          => ['ro'],
            '/[ğĞıİşŞ]/u'                          => ['tr', 'az'],
            '/[øØåÅæÆ]/u'                          => ['sv', 'da', 'nb', 'no'],
            '/[őűŐŰ]/u'                            => ['hu'],
            '/[āēīōūĀĒĪŌŪģķļņĢĶĻŅ]/u'              => ['lv'],
            '/[ąčęėįšųūžĄČĘĖĮŠŲŪŽ]/u'              => ['lt'],
            '/[đĐ]/u'                              => ['hr', 'vi'],
            '/[ăâêôơưĂÂÊÔƠƯảãạằắẳẵặầấẩẫậềếểễệỉĩịỏõọồốổỗộờớởỡợủũụừứửữựỳỷỹỵ]/u' => ['vi'],
            '/[þðÞÐ]/u'                            => ['is'],
            '/[ċġħżĊĠĦŻ]/u'                        => ['mt'],
        ];

        foreach ($uniqueOrthography as $pattern => $orthoLangs) {
            if (preg_match($pattern, $text)) {
                return in_array($targetBase, $orthoLangs, true) ? 'same_lang' : 'foreign_confirmed';
            }
        }

        $cleanTitle = ' ' . mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title)), 'UTF-8') . ' ';
        $cleanText  = ' ' . mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text)), 'UTF-8') . ' ';

        $dictionaries = self::getLanguageDictionaries();
        $targetAccentRegex = $dictionaries[$targetBase]['accent_regex'] ?? '';
        $hasTargetAccentsTitle = (!empty($targetAccentRegex) && preg_match($targetAccentRegex, $title));
        $hasTargetAccentsText  = (!empty($targetAccentRegex) && preg_match($targetAccentRegex, $text));

        // 4. Détection rapide sur les marqueurs stricts du titre lorsqu'aucun accent de la langue cible n'est présent
        if (!$hasTargetAccentsTitle) {
            foreach ($dictionaries as $lang => $dict) {
                if ($lang === $targetBase) {
                    continue;
                }
                foreach ($dict['title_strict'] as $w) {
                    if (strpos($cleanTitle, $w) !== false) {
                        return 'foreign_confirmed';
                    }
                }
            }

            if ($targetBase !== 'en' && preg_match('/\b[a-z]{3,}(?:ing|tive|ness|ship|less)\b/i', $title)) {
                return 'foreign_confirmed';
            }
        }

        // 5. Scoring multilingue par analyse lexicale et accentuelle (titre + corps)
        $scores = [];
        foreach ($dictionaries as $lang => $dict) {
            $score = 0;

            foreach ($dict['title_strict'] as $w) {
                if (strpos($cleanTitle, $w) !== false) {
                    $score += 3;
                }
            }

            foreach ($dict['stop_words'] as $w) {
                if (strpos($cleanText, $w) !== false) {
                    $score += 1;
                }
            }

            if (!empty($dict['accent_regex']) && preg_match($dict['accent_regex'], $text)) {
                $score += 3;
            }

            $scores[$lang] = $score;
        }

        // 6. Validation complémentaire par les étiquettes de flux (si langue cible = fr)
        if ($targetBase === 'fr') {
            $tags = method_exists($entry, 'tags') ? (array)$entry->tags() : [];
            $frTagsCount = 0;
            foreach ($tags as $tag) {
                $tagLower = mb_strtolower(trim((string)$tag), 'UTF-8');
                if (preg_match('/[éèêëàâîïôùûçœæ]/u', $tagLower) ||
                    in_array($tagLower, ['actualités', 'france', 'politique', 'économie', 'société'], true)) {
                    $frTagsCount++;
                }
            }

            if ($frTagsCount >= 2 && ($scores['fr'] ?? 0) >= 2) {
                $maxForeign = 0;
                foreach ($scores as $lang => $val) {
                    if ($lang !== 'fr' && $val > $maxForeign) {
                        $maxForeign = $val;
                    }
                }
                if ($scores['fr'] > $maxForeign) {
                    return 'same_lang';
                }
            }
        }

        // 7. Comparaison neutre entre la langue cible et la meilleure langue étrangère
        $targetScore = (int)($scores[$targetBase] ?? 0);
        $maxForeignScore = 0;
        foreach ($scores as $lang => $val) {
            if ($lang !== $targetBase && $val > $maxForeignScore) {
                $maxForeignScore = (int)$val;
            }
        }

        if ($maxForeignScore > $targetScore) {
            return 'foreign_confirmed';
        }

        if ($maxForeignScore === $targetScore && $maxForeignScore > 0 && !$hasTargetAccentsText) {
            return 'foreign_confirmed';
        }

        if ($targetScore <= 1 && !$hasTargetAccentsText) {
            return 'foreign_confirmed';
        }

        return 'same_lang';
    }

    /**
     * Normalise un code de langue (ex. 'fr-FR' -> 'fr', 'pt_BR' -> 'pt', 'zh-Hans' -> 'zh').
     */
    private static function normalizeLangCode(string $lang): string {
        $clean = strtolower(trim($lang));
        if (strpos($clean, '-') !== false) {
            $clean = explode('-', $clean)[0];
        } elseif (strpos($clean, '_') !== false) {
            $clean = explode('_', $clean)[0];
        }
        return $clean ?: 'fr';
    }

    /**
     * Dictionnaires grammaticaux et accentuels pour les langues à alphabet latin.
     */
    private static function getLanguageDictionaries(): array {
        return [
            'fr' => [
                'accent_regex' => '/[éèêëàâîïôùûçœæ]/u',
                'title_strict' => [
                    ' les ', ' des ', ' aux ', ' dans ', ' pour ', ' avec ', ' sur ', ' sous ',
                    ' chez ', ' vers ', ' cette ', ' ces ', ' sont ', ' mais ', ' donc ', ' ainsi ',
                    ' après ', ' avant ', ' pendant ', ' pourquoi ', ' comment ', ' quand ',
                    ' quotidien ', ' quotidienne ', ' hebdomadaire ', ' aujourd hui '
                ],
                'stop_words' => [
                    ' le ', ' la ', ' les ', ' du ', ' des ', ' un ', ' une ', ' et ', ' est ',
                    ' dans ', ' pour ', ' avec ', ' sur ', ' ce ', ' cette ', ' ces ',
                    ' sont ', ' pas ', ' au ', ' aux ', ' ses ', ' leur ', ' leurs ', ' mais ', ' dont '
                ],
            ],
            'en' => [
                'accent_regex' => '',
                'title_strict' => [
                    ' the ', ' my ', ' you ', ' your ', ' it ', ' its ', ' we ', ' our ', ' they ', ' their ',
                    ' onto ', ' into ', ' with ', ' without ', ' from ', ' about ', ' between ', ' through ',
                    ' how ', ' why ', ' what ', ' where ', ' when ', ' who ', ' should ', ' could ', ' would ',
                    ' released ', ' releases ', ' announced ', ' update ', ' review ', ' guide ', ' makes '
                ],
                'stop_words' => [
                    ' the ', ' a ', ' an ', ' and ', ' is ', ' are ', ' in ', ' to ', ' for ', ' by ',
                    ' with ', ' on ', ' of ', ' this ', ' that ', ' from ', ' at ', ' as ', ' was ',
                    ' were ', ' out ', ' have ', ' has ', ' will ', ' can ', ' not ', ' but ', ' which '
                ],
            ],
            'de' => [
                'accent_regex' => '/[äöüÄÖÜ]/u',
                'title_strict' => [
                    ' der ', ' die ', ' das ', ' und ', ' wie ', ' warum ', ' was ', ' wer ', ' wo ', ' wann ',
                    ' nicht ', ' über ', ' durch ', ' gegen ', ' ohne ', ' zwischen ', ' heute ', ' jetzt ',
                    ' immer ', ' wieder ', ' neue ', ' neues ', ' neuen ', ' erste ', ' ersten ', ' mit ',
                    ' für ', ' nach ', ' beim ', ' zum ', ' zur ', ' eines ', ' einer ', ' einem ', ' einen '
                ],
                'stop_words' => [
                    ' der ', ' die ', ' das ', ' und ', ' ist ', ' in ', ' den ', ' von ', ' zu ', ' dem ',
                    ' mit ', ' sich ', ' für ', ' im ', ' nicht ', ' eine ', ' einer ', ' ein ', ' als ',
                    ' auch ', ' es ', ' an ', ' werden ', ' aus ', ' er ', ' hat ', ' dass ', ' sie ',
                    ' nach ', ' wird ', ' bei ', ' um ', ' am ', ' sind '
                ],
            ],
            'es' => [
                'accent_regex' => '/[áíóúÁÍÓÚ]/u',
                'title_strict' => [
                    ' el ', ' los ', ' las ', ' del ', ' al ', ' para ', ' con ', ' sin ', ' sobre ', ' entre ',
                    ' hasta ', ' desde ', ' hacia ', ' según ', ' tras ', ' mediante ', ' contra ',
                    ' cómo ', ' qué ', ' quién ', ' cuándo ', ' dónde ', ' cuál ', ' pero ', ' sino ',
                    ' aunque ', ' porque ', ' mientras ', ' también ', ' además ', ' hoy ', ' ayer ',
                    ' mañana ', ' ahora ', ' nuevo ', ' nueva ', ' nuevos ', ' nuevas ', ' gobierno ',
                    ' españa ', ' francia ', ' mundo ', ' años ', ' ante ', ' bajo ', ' sus ', ' esto ', ' esta '
                ],
                'stop_words' => [
                    ' el ', ' los ', ' las ', ' del ', ' al ', ' y ', ' para ', ' con ', ' sin ', ' sobre ',
                    ' hasta ', ' desde ', ' hacia ', ' tras ', ' su ', ' sus ', ' lo ', ' como ', ' más ',
                    ' pero ', ' ya ', ' este ', ' esta ', ' estos ', ' estas ', ' todo ', ' toda ', ' todos ',
                    ' todas ', ' otro ', ' otra ', ' otros ', ' otras ', ' muy ', ' también ', ' así ',
                    ' puede ', ' pueden ', ' han ', ' sido ', ' está ', ' están '
                ],
            ],
            'it' => [
                'accent_regex' => '/[àèéìòùÀÈÉÌÒÙ]/u',
                'title_strict' => [
                    ' il ', ' gli ', ' dello ', ' della ', ' delle ', ' degli ', ' nello ', ' nella ',
                    ' nelle ', ' negli ', ' allo ', ' alla ', ' alle ', ' agli ', ' dallo ', ' dalla ',
                    ' dalle ', ' dagli ', ' sul ', ' sulla ', ' sulle ', ' sugli ', ' che ', ' non ',
                    ' per ', ' sono ', ' come ', ' più ', ' anche ', ' questo ', ' questa ', ' questi ',
                    ' queste ', ' quello ', ' quella ', ' perché ', ' quando ', ' dove ', ' dopo ',
                    ' prima ', ' nuovo ', ' nuova ', ' nuovi ', ' nuove ', ' governo ', ' italia ', ' anni '
                ],
                'stop_words' => [
                    ' il ', ' gli ', ' dello ', ' della ', ' delle ', ' degli ', ' nella ', ' nelle ',
                    ' alla ', ' alle ', ' dalla ', ' sulla ', ' che ', ' non ', ' per ', ' con ', ' sono ',
                    ' come ', ' più ', ' anche ', ' questo ', ' questa ', ' questi ', ' queste ', ' stato ',
                    ' stata ', ' essere ', ' hanno ', ' può ', ' tutti ', ' tutte ', ' molto ', ' sempre ',
                    ' così ', ' ogni ', ' fatto ', ' fra ', ' tra ', ' suo ', ' sua ', ' suoi ', ' sue '
                ],
            ],
            'pt' => [
                'accent_regex' => '/[áàâãéêíóôõúçÁÀÂÃÉÊÍÓÔÕÚÇ]/u',
                'title_strict' => [
                    ' não ', ' para ', ' com ', ' uma ', ' uns ', ' umas ', ' dos ', ' das ', ' pelo ',
                    ' pela ', ' pelos ', ' pelas ', ' num ', ' numa ', ' como ', ' mais ', ' mas ', ' já ',
                    ' este ', ' esta ', ' estes ', ' estas ', ' isso ', ' isto ', ' aquilo ', ' muito ',
                    ' também ', ' assim ', ' pode ', ' podem ', ' foram ', ' sido ', ' está ', ' estão ',
                    ' sobre ', ' entre ', ' até ', ' desde ', ' após ', ' contra ', ' sem ', ' sob ',
                    ' são ', ' foi ', ' tem ', ' têm ', ' governo ', ' brasil ', ' portugal ', ' anos '
                ],
                'stop_words' => [
                    ' não ', ' para ', ' com ', ' uma ', ' uns ', ' umas ', ' dos ', ' das ', ' pelo ',
                    ' pela ', ' pelos ', ' pelas ', ' como ', ' mais ', ' mas ', ' já ', ' este ', ' esta ',
                    ' todo ', ' toda ', ' todos ', ' todas ', ' outro ', ' outra ', ' outros ', ' outras ',
                    ' muito ', ' também ', ' assim ', ' pode ', ' podem ', ' foram ', ' sido ', ' está ',
                    ' estão ', ' sobre ', ' entre ', ' até ', ' desde ', ' após ', ' sem ', ' são ', ' foi '
                ],
            ],
            'nl' => [
                'accent_regex' => '',
                'title_strict' => [
                    ' het ', ' een ', ' van ', ' dat ', ' die ', ' op ', ' voor ', ' met ', ' zijn ',
                    ' niet ', ' aan ', ' ook ', ' als ', ' maar ', ' om ', ' uit ', ' nog ', ' wel ',
                    ' geen ', ' meer ', ' door ', ' over ', ' bij ', ' naar ', ' tot ', ' onder ', ' tegen ',
                    ' tussen ', ' zonder ', ' hoe ', ' waarom ', ' wat ', ' wie ', ' waar ', ' wanneer ',
                    ' deze ', ' dit ', ' onze ', ' hun ', ' wordt ', ' worden ', ' heeft ', ' hebben ', ' nieuwe '
                ],
                'stop_words' => [
                    ' het ', ' een ', ' van ', ' dat ', ' die ', ' op ', ' voor ', ' met ', ' zijn ',
                    ' niet ', ' aan ', ' ook ', ' als ', ' maar ', ' om ', ' uit ', ' nog ', ' wel ',
                    ' geen ', ' meer ', ' door ', ' over ', ' bij ', ' naar ', ' tot ', ' onder ', ' tegen ',
                    ' tussen ', ' zonder ', ' deze ', ' dit ', ' onze ', ' hun ', ' wordt ', ' worden ',
                    ' heeft ', ' hebben ', ' kan ', ' kunnen ', ' nieuwe ', ' alle ', ' veel '
                ],
            ],
            'pl' => [
                'accent_regex' => '/[łŁąćęńśźżĄĆĘŃŚŹŻ]/u',
                'title_strict' => [
                    ' nie ', ' się ', ' jest ', ' jak ', ' ale ', ' czy ', ' już ', ' tylko ', ' przez ',
                    ' przy ', ' przed ', ' dla ', ' bez ', ' pod ', ' nad ', ' oraz ', ' który ', ' która ',
                    ' które ', ' wszystko ', ' bardzo ', ' może ', ' będzie ', ' był ', ' była ', ' było '
                ],
                'stop_words' => [
                    ' nie ', ' się ', ' jest ', ' na ', ' do ', ' jak ', ' ale ', ' czy ', ' już ', ' tylko ',
                    ' przez ', ' przy ', ' przed ', ' po ', ' od ', ' dla ', ' bez ', ' pod ', ' nad ',
                    ' oraz ', ' który ', ' która ', ' które ', ' wszystko ', ' bardzo ', ' może ', ' będzie ',
                    ' był ', ' była ', ' było ', ' są ', ' tych ', ' tym '
                ],
            ],
            'sv' => [
                'accent_regex' => '/[åÅäÄöÖ]/u',
                'title_strict' => [
                    ' och ', ' att ', ' det ', ' som ', ' för ', ' med ', ' till ', ' inte ', ' har ',
                    ' kan ', ' från ', ' men ', ' ett ', ' var ', ' är ', ' ska ', ' eller ', ' också ',
                    ' efter ', ' under ', ' mellan ', ' utan ', ' hur ', ' varför ', ' vad ', ' vem ', ' när '
                ],
                'stop_words' => [
                    ' och ', ' att ', ' det ', ' som ', ' för ', ' med ', ' till ', ' inte ', ' har ',
                    ' kan ', ' från ', ' men ', ' om ', ' ett ', ' var ', ' är ', ' ska ', ' eller ',
                    ' också ', ' efter ', ' under ', ' mellan ', ' utan ', ' nya ', ' nytt ', ' alla '
                ],
            ],
            'da' => [
                'accent_regex' => '/[æÆøØåÅ]/u',
                'title_strict' => [
                    ' og ', ' det ', ' som ', ' ikke ', ' med ', ' til ', ' har ', ' fra ', ' men ',
                    ' efter ', ' under ', ' mellem ', ' uden ', ' hvordan ', ' hvorfor ', ' hvad ', ' hvem '
                ],
                'stop_words' => [
                    ' og ', ' at ', ' det ', ' som ', ' for ', ' med ', ' til ', ' ikke ', ' har ',
                    ' kan ', ' fra ', ' men ', ' om ', ' var ', ' skal ', ' eller ', ' også ', ' efter '
                ],
            ],
            'no' => [
                'accent_regex' => '/[æÆøØåÅ]/u',
                'title_strict' => [
                    ' og ', ' det ', ' som ', ' ikke ', ' med ', ' til ', ' har ', ' fra ', ' men ',
                    ' etter ', ' mellom ', ' uten ', ' hvordan ', ' hvorfor ', ' hva ', ' hvem ', ' når '
                ],
                'stop_words' => [
                    ' og ', ' at ', ' det ', ' som ', ' for ', ' med ', ' til ', ' ikke ', ' har ',
                    ' kan ', ' fra ', ' men ', ' om ', ' var ', ' skal ', ' eller ', ' også ', ' etter '
                ],
            ],
            'fi' => [
                'accent_regex' => '/[äÄöÖ]/u',
                'title_strict' => [
                    ' että ', ' mutta ', ' kuin ', ' myös ', ' vain ', ' sekä ', ' kaikki ', ' tämä ',
                    ' miten ', ' miksi ', ' mitä ', ' kuka ', ' missä ', ' milloin ', ' uusi ', ' uudet '
                ],
                'stop_words' => [
                    ' ja ', ' on ', ' ei ', ' että ', ' se ', ' mutta ', ' kuin ', ' myös ', ' vain ',
                    ' sekä ', ' kaikki ', ' tämä ', ' ovat ', ' oli ', ' ollut ', ' hyvin ', ' vielä '
                ],
            ],
            'cs' => [
                'accent_regex' => '/[áčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ]/u',
                'title_strict' => [
                    ' ale ', ' nebo ', ' jako ', ' jsou ', ' není ', ' bude ', ' které ', ' který ',
                    ' která ', ' proč ', ' jak ', ' kde ', ' kdy ', ' nový ', ' nová ', ' nové ', ' podle '
                ],
                'stop_words' => [
                    ' ale ', ' nebo ', ' jako ', ' jsou ', ' není ', ' bude ', ' které ', ' který ',
                    ' která ', ' pro ', ' před ', ' pod ', ' nad ', ' bez ', ' přes ', ' mezi ', ' již '
                ],
            ],
            'sk' => [
                'accent_regex' => '/[áäčďéíĺľňóôŕšťúýžÁÄČĎÉÍĹĽŇÓÔŔŠŤÚÝŽ]/u',
                'title_strict' => [
                    ' ale ', ' alebo ', ' ako ', ' nie ', ' bude ', ' ktoré ', ' ktorý ', ' ktorá ',
                    ' prečo ', ' kde ', ' kedy ', ' nový ', ' nová ', ' nové ', ' podľa ', ' veľmi '
                ],
                'stop_words' => [
                    ' ale ', ' alebo ', ' ako ', ' nie ', ' bude ', ' ktoré ', ' ktorý ', ' ktorá ',
                    ' pre ', ' pred ', ' pod ', ' nad ', ' bez ', ' cez ', ' medzi ', ' už ', ' len '
                ],
            ],
            'ro' => [
                'accent_regex' => '/[ăâîșțĂÂÎȘȚ]/u',
                'title_strict' => [
                    ' care ', ' pentru ', ' dintr ', ' într ', ' despre ', ' după ', ' către ', ' fără ',
                    ' sunt ', ' este ', ' fost ', ' cum ', ' când ', ' unde ', ' cine ', ' nouă ', ' noi '
                ],
                'stop_words' => [
                    ' care ', ' pentru ', ' din ', ' într ', ' despre ', ' după ', ' către ', ' fără ',
                    ' sunt ', ' este ', ' fost ', ' mai ', ' dar ', ' sau ', ' dacă ', ' doar ', ' toate '
                ],
            ],
            'hu' => [
                'accent_regex' => '/[áéíóöőúüűÁÉÍÓÖŐÚÜŰ]/u',
                'title_strict' => [
                    ' hogy ', ' nem ', ' egy ', ' mint ', ' csak ', ' már ', ' még ', ' vagy ', ' sem ',
                    ' hogyan ', ' miért ', ' mikor ', ' új ', ' első ', ' szerint ', ' után ', ' alatt '
                ],
                'stop_words' => [
                    ' hogy ', ' nem ', ' egy ', ' mint ', ' csak ', ' már ', ' még ', ' vagy ', ' sem ',
                    ' volt ', ' lesz ', ' van ', ' vannak ', ' után ', ' alatt ', ' között ', ' nélkül '
                ],
            ],
            'tr' => [
                'accent_regex' => '/[çğıİöşüÇĞÖŞÜ]/u',
                'title_strict' => [
                    ' için ', ' ile ', ' ama ', ' fakat ', ' veya ', ' gibi ', ' daha ', ' çok ', ' yeni ',
                    ' nasıl ', ' neden ', ' nedir ', ' kim ', ' nerede ', ' sonra ', ' önce ', ' göre '
                ],
                'stop_words' => [
                    ' bir ', ' için ', ' ile ', ' ama ', ' fakat ', ' veya ', ' gibi ', ' daha ', ' çok ',
                    ' olarak ', ' olan ', ' kadar ', ' sonra ', ' önce ', ' göre ', ' karşı ', ' ancak '
                ],
            ],
            'id' => [
                'accent_regex' => '',
                'title_strict' => [
                    ' yang ', ' dan ', ' untuk ', ' dengan ', ' dari ', ' pada ', ' dalam ', ' tidak ',
                    ' akan ', ' bisa ', ' juga ', ' sudah ', ' telah ', ' baru ', ' bagaimana ', ' mengapa '
                ],
                'stop_words' => [
                    ' yang ', ' dan ', ' untuk ', ' dengan ', ' dari ', ' pada ', ' dalam ', ' tidak ',
                    ' akan ', ' bisa ', ' juga ', ' sudah ', ' telah ', ' oleh ', ' karena ', ' atau '
                ],
            ],
            'ca' => [
                'accent_regex' => '/[àèéíïòóúüçÀÈÉÍÏÒÓÚÜÇ·]/u',
                'title_strict' => [
                    ' els ', ' les ', ' amb ', ' per ', ' sense ', ' sobre ', ' entre ', ' fins ', ' des ',
                    ' com ', ' què ', ' qui ', ' quan ', ' però ', ' també ', ' avui ', ' nou ', ' nova '
                ],
                'stop_words' => [
                    ' els ', ' les ', ' amb ', ' per ', ' sense ', ' sobre ', ' entre ', ' fins ', ' des ',
                    ' més ', ' però ', ' també ', ' aquest ', ' aquesta ', ' tot ', ' tots ', ' molt '
                ],
            ],
            'ga' => [
                'accent_regex' => '/[áéíóúÁÉÍÓÚ]/u',
                'title_strict' => [
                    ' agus ', ' leis ', ' faoi ', ' chuig ', ' gan ', ' idir ', ' tar ', ' roimh ',
                    ' conas ', ' cén ', ' cathain ', ' nua ', ' éireann ', ' rialtas '
                ],
                'stop_words' => [
                    ' agus ', ' leis ', ' faoi ', ' chuig ', ' gan ', ' idir ', ' ach ', ' nó ', ' mar ',
                    ' sin ', ' seo ', ' tá ', ' bhí ', ' beidh ', ' atá ', ' nach '
                ],
            ],
            'eo' => [
                'accent_regex' => '/[ĉĝĥĵŝŭĈĜĤĴŜŬ]/u',
                'title_strict' => [
                    ' kaj ', ' kun ', ' por ', ' pri ', ' sen ', ' sub ', ' super ', ' inter ', ' kiel ',
                    ' kial ', ' kiam ', ' kie ', ' kiu ', ' nova ', ' novaj ', ' estas ', ' estis '
                ],
                'stop_words' => [
                    ' kaj ', ' kun ', ' por ', ' pri ', ' sen ', ' sub ', ' super ', ' inter ', ' sed ',
                    ' aŭ ', ' kiel ', ' estas ', ' estis ', ' estos ', ' pli ', ' plej ', ' nur '
                ],
            ],
        ];
    }

    public static function getCanonicalHash(string $title, string $content, string $targetLang, string $mode = 'translate'): string {
        $normalizedText = trim(strip_tags($title)) . '|' . trim(strip_tags(mb_substr($content, 0, 2000)));
        return hash('sha256', $mode . ':' . $targetLang . ':' . $normalizedText);
    }

    public static function getFeedRule(int $feedId): ?string {
        try {
            $dbPath = dirname(__FILE__) . '/data/translator.db';
            if (!file_exists($dbPath)) {
                return null;
            }

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
