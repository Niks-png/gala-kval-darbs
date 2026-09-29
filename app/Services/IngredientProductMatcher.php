<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finds the cheapest store product for a recipe ingredient.
 *
 * TheMealDB ingredients are in English, while product titles are Latvian,
 * so ingredients are first translated to Latvian search terms.
 */
class IngredientProductMatcher
{
    /**
     * English ingredient (singular) => Latvian terms, most specific first.
     *
     * @var array<string, list<string>>
     */
    private const TERMS = [
        'chicken breast' => ['VISTAS FILEJA', 'VISTAS KRŪTIŅ'],
        'chicken thigh' => ['VISTAS ŠĶIŅĶ', 'VISTAS STILBIŅ'],
        'chicken stock' => ['BULJON'],
        'beef stock' => ['BULJON'],
        'vegetable stock' => ['BULJON'],
        'stock' => ['BULJON'],
        'chicken' => ['VISTAS', 'VISTA'],
        'minced beef' => ['LIELLOPU MALTĀ', 'LIELLOPA MALTĀ', 'MALTĀ GAĻA', 'MALTĀ MASA'],
        'minced pork' => ['CŪKGAĻAS MALTĀ', 'MALTĀ GAĻA', 'MALTĀ MASA'],
        'mince' => ['MALTĀ GAĻA', 'MALTĀ MASA'],
        'beef' => ['LIELLOPA', 'LIELLOPU'],
        'pork' => ['CŪKGAĻA', 'CŪKGAĻAS'],
        'lamb' => ['JĒRA'],
        'turkey' => ['TĪTARA', 'TĪTARS'],
        'bacon' => ['BEKONS', 'BEKONA'],
        'ham' => ['ŠĶIŅĶIS'],
        'sausage' => ['DESIŅAS', 'DESA', 'CĪSIŅI'],
        'salmon' => ['LASIS', 'LAŠA'],
        'tuna' => ['TUNCIS', 'TUNČA'],
        'cod' => ['MENCA', 'MENCAS'],
        'prawn' => ['GARNELES'],
        'shrimp' => ['GARNELES'],
        'coconut milk' => ['KOKOSRIEKSTU PIENS', 'KOKOSA PIENS'],
        'milk' => ['PIENS'],
        'butter' => ['SVIESTS'],
        'egg' => ['OLAS'],
        'cream cheese' => ['KRĒMSIERS'],
        'cheddar' => ['ČEDARS', 'SIERS'],
        'parmesan' => ['PARMEZĀNS', 'PARMEZĀNA'],
        'mozzarella' => ['MOCARELLA', 'MOZZARELLA'],
        'feta' => ['FETA'],
        'cheese' => ['SIERS'],
        'sour cream' => ['SKĀBAIS KRĒJUMS', 'KRĒJUMS'],
        'double cream' => ['SALDKRĒJUMS'],
        'heavy cream' => ['SALDKRĒJUMS'],
        'cream' => ['SALDKRĒJUMS', 'KRĒJUMS'],
        'yogurt' => ['JOGURTS'],
        'flour' => ['MILTI', 'KVIEŠU MILTI'],
        'sugar' => ['CUKURS'],
        'salt' => ['SĀLS'],
        'black pepper' => ['PIPARI'],
        'red pepper' => ['PAPRIKA'],
        'green pepper' => ['PAPRIKA'],
        'bell pepper' => ['PAPRIKA'],
        'pepper' => ['PIPARI'],
        'rice' => ['RĪSI'],
        'spaghetti' => ['SPAGETI', 'MAKARONI'],
        'pasta' => ['MAKARONI', 'SPAGETI', 'PASTA'],
        'penne' => ['MAKARONI'],
        'noodle' => ['NŪDELES'],
        'bread' => ['MAIZE'],
        'oat' => ['AUZU PĀRSLAS'],
        'honey' => ['MEDUS'],
        'potato' => ['KARTUPEĻI'],
        'red onion' => ['SARKANIE SĪPOLI', 'SĪPOLI'],
        'spring onion' => ['LOKI', 'SĪPOLLOKI'],
        'onion' => ['SĪPOLI'],
        'garlic' => ['ĶIPLOKI', 'ĶIPLOKS'],
        'carrot' => ['BURKĀNI'],
        'tomato puree' => ['TOMĀTU PASTA'],
        'tomato paste' => ['TOMĀTU PASTA'],
        'tomato' => ['TOMĀTI'],
        'cucumber' => ['GURĶI'],
        'cabbage' => ['KĀPOSTI'],
        'mushroom' => ['ŠAMPINJONI', 'SĒNES'],
        'lemon' => ['CITRONI', 'CITRONS'],
        'lime' => ['LAIMI', 'LAIMS'],
        'apple' => ['ĀBOLI'],
        'banana' => ['BANĀNI'],
        'orange' => ['APELSĪNI'],
        'strawberry' => ['ZEMENES'],
        'spinach' => ['SPINĀTI'],
        'broccoli' => ['BROKOLIS', 'BROKOĻI'],
        'aubergine' => ['BAKLAŽĀNI'],
        'eggplant' => ['BAKLAŽĀNI'],
        'courgette' => ['CUKINI'],
        'zucchini' => ['CUKINI'],
        'pea' => ['ZIRNĪŠI', 'ZIRŅI'],
        'bean' => ['PUPIŅAS'],
        'sweetcorn' => ['KUKURŪZA'],
        'corn' => ['KUKURŪZA'],
        'olive oil' => ['OLĪVEĻĻA'],
        'sunflower oil' => ['SAULESPUĶU EĻĻA'],
        'vegetable oil' => ['SAULESPUĶU EĻĻA', 'EĻĻA'],
        'oil' => ['EĻĻA'],
        'vinegar' => ['ETIĶIS'],
        'soy sauce' => ['SOJAS MĒRCE'],
        'ketchup' => ['KEČUPS'],
        'mayonnaise' => ['MAJONĒZE'],
        'mustard' => ['SINEPES'],
        'chocolate' => ['ŠOKOLĀDE'],
        'parsley' => ['PĒTERSĪĻI'],
        'dill' => ['DILLES'],
        'basil' => ['BAZILIKS'],
        'paprika' => ['PAPRIKA'],
        'cinnamon' => ['KANĒLIS'],
        'baking powder' => ['CEPAMAIS PULVERIS'],
        'yeast' => ['RAUGS'],
        'coffee' => ['KAFIJA'],
        'beer' => ['ALUS'],
        'wine' => ['VĪNS'],
    ];

    /**
     * Ingredients nobody needs to buy.
     */
    private const SKIPPED = ['water', 'ice', 'boiling water', 'cold water'];

    /**
     * Title words that mean the product is only flavoured with the ingredient,
     * or is not food for people at all.
     */
    private const EXCLUDED_WORDS = [
        'GARŠ', 'BARĪBA', 'KAĶ', 'SUŅ', 'BIEZENIS', 'ČIPSI', 'PLĀKSNES', 'PUDIŅ',
        'NŪDELES', 'DZĒRIENS', 'MAZG', 'SULA', 'KONFEKT', 'CEPUMI', 'SNEK', 'FRĪ', 'UZKOD',
    ];

    /**
     * @param  list<string>  $ingredients
     * @return list<array{ingredient: string, product: ?Product}>
     */
    public function matchMany(array $ingredients): array
    {
        return array_values(array_map(fn (string $ingredient): array => [
            'ingredient' => $ingredient,
            'product' => $this->match($ingredient),
        ], $ingredients));
    }

    public function match(string $ingredient): ?Product
    {
        $ingredient = mb_strtolower(trim($ingredient));

        if ($ingredient === '' || in_array($ingredient, self::SKIPPED, true)) {
            return null;
        }

        $terms = $this->termsFor($ingredient);

        if ($terms === []) {
            return null;
        }

        $candidates = Product::query()
            ->whereNotNull('current_price')
            ->where(function (Builder $query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->orWhere('title', 'like', "%{$term}%");
                }
            })
            ->limit(300)
            ->get();

        return $candidates
            ->map(fn (Product $product): array => [
                'product' => $product,
                'score' => $this->score(mb_strtoupper($product->title), $terms),
            ])
            ->filter(fn (array $candidate): bool => $candidate['score'] !== null)
            ->sortBy([
                ['score', 'asc'],
                fn (array $a, array $b): int => (float) $a['product']->current_price <=> (float) $b['product']->current_price,
            ])
            ->first()['product'] ?? null;
    }

    /**
     * @return list<string>
     */
    private function termsFor(string $ingredient): array
    {
        $keys = array_keys(self::TERMS);
        usort($keys, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($keys as $key) {
            if (preg_match('/\b'.preg_quote($key, '/').'(s|es)?\b/u', $ingredient)) {
                return self::TERMS[$key];
            }
        }

        return [];
    }

    /**
     * Lower is better: 0 = title starts with the term, 1 = a word in the title
     * starts with it. Null means the product should not be offered.
     *
     * @param  list<string>  $terms
     */
    private function score(string $title, array $terms): ?int
    {
        foreach (self::EXCLUDED_WORDS as $word) {
            if (str_contains($title, $word) && ! $this->termsContain($terms, $word)) {
                return null;
            }
        }

        $best = null;

        foreach ($terms as $index => $term) {
            $termPenalty = $index * 2;

            if (str_starts_with($title, $term)) {
                $best = min($best ?? PHP_INT_MAX, $termPenalty);
            } elseif (preg_match('/(^|[^\p{L}])'.preg_quote($term, '/').'/u', $title)) {
                $best = min($best ?? PHP_INT_MAX, $termPenalty + 1);
            }
        }

        return $best;
    }

    /**
     * @param  list<string>  $terms
     */
    private function termsContain(array $terms, string $word): bool
    {
        foreach ($terms as $term) {
            if (str_contains($term, $word)) {
                return true;
            }
        }

        return false;
    }
}
