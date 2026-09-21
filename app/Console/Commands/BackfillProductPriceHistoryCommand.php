<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use Illuminate\Console\Command;

class BackfillProductPriceHistoryCommand extends Command
{
    protected $signature = 'products:backfill-price-history';

    protected $description = 'Add an initial price-history snapshot for products without history';

    public function handle(): int
    {
        $created = 0;

        Product::query()
            ->whereNotNull('current_price')
            ->whereDoesntHave('priceHistory')
            ->chunkById(500, function ($products) use (&$created): void {
                $now = now();
                $history = $products->map(fn (Product $product): array => [
                    'product_id' => $product->id,
                    'previous_price' => $product->current_price,
                    'new_price' => $product->current_price,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($history !== []) {
                    ProductPriceHistory::insert($history);
                    $created += count($history);
                }
            });

        $this->info("Added {$created} product price-history snapshots.");

        return self::SUCCESS;
    }
}
