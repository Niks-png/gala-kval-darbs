<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductCategorizer;
use Illuminate\Console\Command;

class CategorizeProductsCommand extends Command
{
    protected $signature = 'products:categorize';

    protected $description = 'Assign a category to every product based on its title';

    public function handle(ProductCategorizer $categorizer): int
    {
        $changed = 0;

        Product::query()->select(['id', 'title', 'category'])->chunkById(500, function ($products) use ($categorizer, &$changed): void {
            $products
                ->groupBy(fn (Product $product): string => $categorizer->categorize($product->title))
                ->each(function ($group, string $category) use (&$changed): void {
                    $ids = $group->filter(fn (Product $product): bool => $product->category !== $category)->modelKeys();

                    if ($ids !== []) {
                        $changed += Product::query()->whereKey($ids)->update(['category' => $category]);
                    }
                });
        });

        $this->info("Updated the category of {$changed} products.");

        $this->table(
            ['Category', 'Products'],
            Product::query()
                ->selectRaw('category, count(*) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->get()
                ->map(fn (Product $row): array => [$row->category, $row->total]),
        );

        return self::SUCCESS;
    }
}
