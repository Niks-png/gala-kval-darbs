<?php

namespace App\Services;

/**
 * Picks a category from a Latvian product title.
 *
 * Keywords anywhere in the title are not enough: "ČIPSI AR SIERA GARŠU" mentions
 * cheese but is a snack. So:
 *  - everything after " AR " ("with ...") is a flavour or filling and is ignored;
 *  - words are matched exactly, not as substrings;
 *  - "strong" words name the product itself (usually nominative: SIERS, ČIPSI),
 *    "weak" words only describe it (usually genitive: SIERA, KARTUPEĻU). A weak
 *    word is used only when the title has no strong word, so "KARTUPEĻU PLĀKSNES"
 *    is a snack and "SIERA DESA" is meat.
 *
 * Multi-word keywords win over single words at the same position.
 */
class ProductCategorizer
{
    public const OTHER = 'Citi';

    /**
     * @var array<string, array{strong: list<string>, weak?: list<string>}>
     */
    private const RULES = [
        'Dzīvnieku barība' => [
            'strong' => ['BARĪBA', 'KAĶIEM', 'SUŅIEM', 'KAĶU', 'SUŅU'],
        ],
        'Bērnu preces' => [
            'strong' => ['AUTIŅBIKSĪTES', 'AUTIŅB', 'BIEZENIS', 'BIEZPUTRA', 'PIENA MAISĪJUMS', 'ZĪDAIŅIEM'],
        ],
        'Higiēna un kosmētika' => [
            'strong' => [
                'ŠAMPŪNS', 'DUŠAS', 'ZIEPES', 'KRĒMZIEPES', 'ŠĶIDRĀS ZIEPES', 'ZOBU', 'ZOB', 'DEZODORANTS', 'DEZOD', 'DEZ',
                'ĶERMEŅA', 'ĶERM', 'SEJAS', 'SEJ', 'ROKU', 'MATU', 'SKŪŠANĀS', 'SKŪŠ', 'SKUVEKLIS', 'SKUVEKĻI', 'SKUV',
                'BALZAMS', 'KONDIC', 'KONDICIONIERIS', 'HIGIĒNISKĀS', 'HIG', 'IELIKTNĪŠI', 'IELIKTN', 'PREZERVATĪVI',
                'LOSJONS', 'MICEL', 'DEPILĀC', 'VATES', 'LŪPU', 'NAGU', 'KOSM', 'TUALETES ŪDENS', 'SMARŽAS',
                'NAKTS KRĒMS', 'DIENAS KRĒMS', 'TAMPONI', 'SERUMS', 'ACU KRĒMS', 'MASKA',
            ],
        ],
        'Uztura bagātinātāji' => [
            'strong' => ['UZTURA', 'VITAMĪNI', 'KOLAGĒNS', 'MAGNIJS'],
        ],
        'Apģērbi' => [
            'strong' => [
                'ZEĶES', 'ZEĶĪTES', 'ZEĶBIKSES', 'ZEĶUBIKSES', 'ZEĶBIK', 'ZEĶB', 'APAKŠBIKSES', 'BOKSERŠORTI', 'LEGINGI',
                'PIDŽAMA', 'KREKLS', 'ČĪBAS', 'CEPURE', 'ŠALLE', 'DŽEMPERIS', 'BIKSES', 'KRŪŠTURIS',
                'DARBA CIMDI', 'CIMDI', 'APAKŠVEĻA',
            ],
            'weak' => ['SIEVIEŠU', 'SIEV', 'VĪRIEŠU'],
        ],
        'Rotaļlietas un hobiji' => [
            'strong' => ['ROTAĻLIETA', 'ROTAĻLIETAS', 'ROTAĻU', 'LEGO', 'KONSTR', 'KONSTRUKTORS', 'PUZLE', 'RADOŠAIS', 'KRĀSOJAMĀ'],
            'weak' => ['HELOVĪNA'],
        ],
        'Elektronika' => [
            'strong' => ['KABELIS', 'AUSTIŅAS', 'LĀDĒTĀJS', 'BEZVADU', 'SPULDZE', 'LUKTURIS', 'POWERBANK'],
        ],
        'Mājsaimniecības preces' => [
            'strong' => [
                'TRAUKU', 'VEĻAS', 'VEĻ', 'MAZG', 'TĪR', 'TĪRĪŠ', 'TĪRĪŠANAS', 'TUAL', 'TUALETES', 'SALVETES', 'SALV',
                'PAPĪRA', 'PAPĪRS', 'DVIEĻI', 'DVIELIS', 'MAISI', 'ATKR', 'GAISA', 'BATERIJAS', 'ŪDENS MĪKSTINĀTĀJS',
                'MĪKST', 'MĪKSTINĀTĀJS', 'LĪDZ', 'LĪDZEKLIS', 'TR', 'SMARŽU GRANULAS', 'SVECES', 'SŪKLIS', 'SŪKĻI',
                'REPELENTS', 'UNIV', 'UNIVERS', 'LOGU', 'STIKLU', 'TRAIPU', 'VIRT',
                // Kitchenware, home and decorations
                'SVECE', 'PANNA', 'KATLS', 'NAZIS', 'NAŽI', 'KAROTE', 'KAROTES', 'DAKŠAS', 'ŠĶĪVIS', 'ŠĶĪVJI', 'TRAUKS',
                'BĻODA', 'KRŪZE', 'GLĀZE', 'GLĀZES', 'VĀKS', 'RĪVE', 'CEPAMFORMA', 'ĀMURS', 'GRIEŠANAS DĒLĪTIS',
                'UZGLABĀŠANAS', 'UZGLAB', 'KASTE', 'VIRTENE', 'DIFUZORS', 'DEKORĀCIJA', 'UZLĪMES', 'LUPATIŅA', 'LUPATIŅAS',
                'MIKROŠĶ', 'MIKROŠĶIEDRAS', 'MOPS', 'BIRSTE', 'SLOTA', 'SPAINIS',
            ],
        ],
        'Dzērieni' => [
            'strong' => [
                'DZĒRIENS', 'DZĒRIENI', 'DZĒR', 'SULA', 'NEKTĀRS', 'ŪDENS', 'MINERĀLŪDENS', 'LIMONĀDE', 'KAFIJA',
                'TĒJA', 'ALUS', 'SIDRS', 'VĪNS', 'KOKTEILIS', 'KOKT', 'BEZALK', 'KVASS', 'SMŪTIJS', 'KAF', 'GĀZ',
            ],
            'weak' => ['KAFIJAS', 'TĒJAS', 'KAKAO', 'ENERĢIJAS', 'SULAS'],
        ],
        'Saldumi un deserti' => [
            'strong' => [
                'ŠOKOLĀDE', 'ŠOK', 'ŠOKOL', 'KONFEKTES', 'KONF', 'ŽELEJKONFEKTES', 'CEPUMI', 'VAFELES', 'ZEFĪRS',
                'MARSHMALLOW', 'DRAŽEJAS', 'KARAMELES', 'LEDENES', 'GUMIJA', 'BATONIŅŠ', 'BATONIŅI', 'BAT', 'BATON',
                'SALDĒJUMS', 'DESERTS', 'PUDIŅŠ', 'KŪKA', 'TORTE', 'TORTĪNE', 'NAŠĶIS', 'SAUSIŅI', 'IEVĀRĪJUMS',
                'DŽEMS', 'MEDUS', 'KAKAO KRĒMS', 'MUSS', 'LED', 'SMALKMAIZĪTES', 'KĒKSS', 'BISKVĪTS', 'VIRTULIS', 'KŪCIŅAS',
                'KONFEKŠU KĀRBA', 'VAFEĻU TRUBIŅAS', 'VAFEĻU RULLĪŠI', 'MUSLI BATONIŅŠ',
            ],
            'weak' => ['ŠOKOLĀDES', 'BISKVĪTA', 'SALDĒJUMA'],
        ],
        'Uzkodas, rieksti un sēklas' => [
            'strong' => [
                'ČIPSI', 'PLĀKSNES', 'PLĀKSN', 'UZKODA', 'UZKODAS', 'NŪJIŅAS', 'KREKERI', 'POPKORNS', 'KLIŅĢERĪŠI',
                'KLIŅĢ', 'SĀLSSTANDIŅI', 'ZEMESRIEKSTI', 'RIEKSTI', 'VALRIEKSTI', 'MANDELES', 'PISTĀCIJAS', 'SĒKLAS',
                'LINSĒKLAS', 'MAISĪJUMS', 'GALETES',
            ],
            'weak' => ['RIEKSTU', 'SĒKLU'],
        ],
        'Gatavie ēdieni un konservi' => [
            'strong' => [
                'PELMEŅI', 'PICA', 'PICAS', 'SAUTĒJUMS', 'ZUPA', 'KOTLETES', 'NAGETI', 'KONSERVI', 'PASTĒTE', 'RAGŪ',
                'VARENIKI', 'PANKŪKAS', 'BLINIŅI', 'PLOVS', 'LAZANJA', 'BURGERS',
            ],
            'weak' => ['ZUPAS'],
        ],
        'Mērces, eļļas un garšvielas' => [
            'strong' => [
                'MĒRCE', 'KEČUPS', 'MAJONĒZE', 'SINEPES', 'GARŠVIELA', 'GARŠVIELAS', 'SĀLS', 'PIPARI', 'ETIĶIS', 'EĻĻA',
                'OLĪVEĻĻA', 'MĀRRUTKI', 'BULJONS', 'PASTA', 'MARINĀDE', 'KANĒLIS', 'RAUGS',
            ],
            'weak' => ['MĒRCES', 'EĻĻAS'],
        ],
        'Piena produkti un olas' => [
            'strong' => [
                'PIENS', 'KEFĪRS', 'SIERS', 'SIERI', 'SIERIŅŠ', 'SIERIŅI', 'KRĒMSIERS', 'JOGURTS', 'JOGURTI', 'KRĒJUMS',
                'SALDKRĒJUMS', 'PUTUKRĒJUMS', 'SVIESTS', 'BIEZPIENS', 'PANIŅAS', 'RŪGUŠPIENS', 'MARGARĪNS', 'OLAS',
                'MOCARELLA', 'MOZZARELLA', 'BIEZP', 'JOG', 'PIENI',
            ],
            'weak' => ['PIENA', 'SIERA', 'BIEZPIENA', 'JOGURTA', 'KRĒJUMA', 'SVIESTA', 'KEFĪRA'],
        ],
        'Gaļa un gaļas izstrādājumi' => [
            'strong' => [
                'GAĻA', 'CŪKGAĻA', 'DESA', 'DESAS', 'DESIŅAS', 'DESIŅA', 'DŪMDESA', 'CĪSIŅI', 'SARDELES', 'ŠĶIŅĶIS',
                'ŠĶIŅĶI', 'BEKONS', 'SALAMI', 'SERVELĀDE', 'KARBONĀDE', 'ŠAŠLIKS', 'SPĀRNI', 'PUSSPĀRNI', 'STILBI',
                'STILBIŅI', 'STEIKS', 'GULAŠS', 'VISTA', 'TĪTARS', 'SPEĶIS', 'AKNAS', 'DOKTORDESA',
            ],
            'weak' => ['CŪKGAĻAS', 'LIELLOPA', 'LIELLOPU', 'VISTAS', 'CĀĻA', 'CĀĻU', 'TĪTARA', 'TEĻA', 'JĒRA', 'TRUŠA', 'PĪLES', 'GAĻAS'],
        ],
        'Zivis un jūras veltes' => [
            'strong' => [
                'ZIVS', 'ZIVIS', 'LASIS', 'MENCA', 'TUNCIS', 'GARNELES', 'ŠPROTES', 'ROLMOPŠI', 'SIĻĶE', 'SIĻĶES',
                'IKRI', 'KALMĀRI', 'SKUMBRIJA',
            ],
            'weak' => ['LAŠA', 'SIĻĶU', 'MENCAS', 'TUNČA', 'GARNEĻU', 'ZIVJU', 'IKRU', 'ŠPROTU'],
        ],
        'Maize un maizes izstrādājumi' => [
            'strong' => [
                'MAIZE', 'BALTMAIZE', 'RUDZUMAIZE', 'SALDSKĀBMAIZE', 'TOSTERMAIZE', 'SAUSMAIZĪTES', 'MAIZĪTES', 'BAGETE',
                'BULCIŅA', 'BULCIŅAS', 'KLAIPS', 'LAVAŠS', 'TORTILJAS', 'KRUASĀNS', 'KRUASĀNI', 'PITA', 'GARDMAIZE',
            ],
            'weak' => ['MAIZES', 'KRUASANA'],
        ],
        'Graudaugi, makaroni un milti' => [
            'strong' => [
                'MILTI', 'RĪSI', 'MAKARONI', 'SPAGETI', 'SPAGHETTI', 'NŪDELES', 'GRIĶI', 'PĀRSLAS', 'PĀRSL', 'PUTRAIMI',
                'PUTRA', 'BROKASTIS', 'BROK', 'MANNA', 'CUKURS', 'KUSKUSS', 'BULGURS', 'KVINOJA', 'MUSLI', 'MUSLS',
            ],
            'weak' => ['AUZU', 'RĪSU', 'KVIEŠU', 'RUDZU', 'GRIĶU'],
        ],
        'Augļi un dārzeņi' => [
            'strong' => [
                'KARTUPEĻI', 'SĪPOLI', 'SĪPOLS', 'BURKĀNI', 'TOMĀTI', 'GURĶI', 'KĀPOSTI', 'ĀBOLI', 'BANĀNI', 'CITRONI',
                'CITRONS', 'APELSĪNI', 'MANDARĪNI', 'ZEMENES', 'VĪNOGAS', 'BUMBIERI', 'PERSIKI', 'ŠAMPINJONI', 'SĒNES',
                'PAPRIKA', 'ĶIPLOKI', 'ĶIPLOKS', 'SALĀTI', 'BIETES', 'ZIRNĪŠI', 'ZIRŅI', 'PUPIŅAS', 'OLĪVAS', 'DATELES',
                'INGVERS', 'ROZĪNES', 'KUKURŪZA', 'BROKOLIS', 'BROKOĻI', 'ĶIRBIS', 'CUKINI', 'AVOKADO', 'LAIMI', 'KIVI',
                'ANANASS', 'ARBŪZS', 'MELONE', 'SPINĀTI', 'DILLES', 'PĒTERSĪĻI', 'LOKI',
            ],
            'weak' => ['KARTUPEĻU', 'KART', 'TOMĀTU', 'ĀBOLU', 'BURKĀNU', 'SĪPOLU', 'KUKURŪZAS', 'PUPIŅU', 'MANGO'],
        ],
    ];

    /**
     * @var array{strong: list<array{0: string, 1: list<string>}>, weak: list<array{0: string, 1: list<string>}>}|null
     */
    private ?array $keywords = null;

    public function categorize(string $title): string
    {
        $words = $this->words($this->productPart($title));

        return $this->find($words, 'strong')
            ?? $this->find($words, 'weak')
            ?? self::OTHER;
    }

    /**
     * The part of the title that names the product: without unit prices in
     * brackets and without "ar ..." (flavour, filling, side ingredients).
     */
    private function productPart(string $title): string
    {
        $title = mb_strtoupper($title);
        $title = (string) preg_replace('/\(.*?\)/us', ' ', $title);

        return preg_split('/\sAR\s/u', $title, 2)[0];
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @param  list<string>  $words
     */
    private function find(array $words, string $strength): ?string
    {
        $keywords = $this->keywords()[$strength];

        foreach (array_keys($words) as $position) {
            foreach ($keywords as [$category, $tokens]) {
                if ($this->matchesAt($words, $position, $tokens)) {
                    return $category;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $words
     * @param  list<string>  $tokens
     */
    private function matchesAt(array $words, int $position, array $tokens): bool
    {
        return array_slice($words, $position, count($tokens)) === $tokens;
    }

    /**
     * Keywords split into words, longest phrases first.
     *
     * @return array{strong: list<array{0: string, 1: list<string>}>, weak: list<array{0: string, 1: list<string>}>}
     */
    private function keywords(): array
    {
        if ($this->keywords !== null) {
            return $this->keywords;
        }

        $keywords = ['strong' => [], 'weak' => []];

        foreach (self::RULES as $category => $rule) {
            foreach (['strong', 'weak'] as $strength) {
                foreach ($rule[$strength] ?? [] as $keyword) {
                    $keywords[$strength][] = [$category, explode(' ', mb_strtoupper($keyword))];
                }
            }
        }

        foreach ($keywords as &$list) {
            usort($list, fn (array $a, array $b): int => count($b[1]) <=> count($a[1]));
        }

        return $this->keywords = $keywords;
    }
}
