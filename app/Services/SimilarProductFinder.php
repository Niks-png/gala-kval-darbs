<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Finds the closest products in other stores. Stores word titles differently
 * ("PIENS OPĀ 2.5% 0.9L" vs "Piens FARM MILK, 1 l, 2%"), so titles are compared
 * by word stems, not exactly.
 */
class SimilarProductFinder
{
    private const MIN_SCORE = 0.4;

    /**
     * Words that say nothing about what the product is.
     */
    private const IGNORED_WORDS = [
        'AR', 'UN', 'BEZ', 'NO', 'UZ', 'IN', 'TIP', 'TOP', 'OPĀ', 'SVER', 'SVERAMS', 'FAS', 'GAB', 'PET', 'CAN',
        'STIKLS', 'PAKA', 'IEPAK', 'KG', 'ML',
    ];

    /**
     * @return Collection<int, Product>
     */
    public function find(Product $product, int $limit = 3): Collection
    {
        $stems = $this->stems($product->title);

        if ($stems === []) {
            return collect();
        }

        return Product::query()->onOffer()
            ->whereNotNull('current_price')
            ->where('store', '!=', $product->store)
            ->when($product->category !== null && $product->category !== ProductCategorizer::OTHER,
                fn ($query) => $query->where('category', $product->category))
            ->get()
            ->map(function (Product $candidate) use ($stems): Product {
                $candidate->similarity = $this->similarity($stems, $this->stems($candidate->title));

                return $candidate;
            })
            ->filter(fn (Product $candidate): bool => $candidate->similarity >= self::MIN_SCORE)
            ->sortByDesc('similarity')
            ->take($limit)
            ->values();
    }

    /**
     * Dice coefficient of two stem sets: 1 = same words, 0 = nothing shared.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function similarity(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        return 2 * count(array_intersect($a, $b)) / (count($a) + count($b));
    }

    /**
     * Word stems without endings, so "PIENS" and "PIENA" count as the same word.
     *
     * @return list<string>
     */
    private function stems(string $title): array
    {
        $title = mb_strtoupper((string) preg_replace('/\(.*?\)/us', ' ', $title));
        $words = preg_split('/[^\p{L}]+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stems = [];
        foreach ($words as $word) {
            $length = mb_strlen($word);

            if ($length < 3 || in_array($word, self::IGNORED_WORDS, true)) {
                continue;
            }

            $stems[] = mb_substr($word, 0, min(6, max(4, $length - 2)));
        }

        return array_values(array_unique($stems));
    }
}
