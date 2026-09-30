<?php

namespace App\Services;

use App\Models\Product;
use App\Notifications\PriceDropped;
use Illuminate\Support\Facades\Notification;

class PriceDropNotifier
{
    /**
     * Notify followers of every product whose price went down.
     * Call after the new prices and history rows are saved.
     *
     * @param  iterable<array{product_id: int, previous_price: mixed, new_price: mixed}>  $changes
     * @return int Number of notifications sent
     */
    public function notify(iterable $changes): int
    {
        $drops = collect($changes)
            ->filter(fn (array $change) => (float) $change['previous_price'] > 0
                && (float) $change['new_price'] < (float) $change['previous_price'])
            ->keyBy('product_id');

        if ($drops->isEmpty()) {
            return 0;
        }

        $products = Product::query()
            ->whereKey($drops->keys())
            ->whereHas('watchers')
            ->with(['watchers', 'priceHistory'])
            ->get();

        $sent = 0;

        foreach ($products as $product) {
            $change = $drops->get($product->id);

            Notification::send($product->watchers, new PriceDropped(
                $product,
                (float) $change['previous_price'],
                (float) $change['new_price'],
                $product->isLowestPriceInDays(30),
            ));

            $sent += $product->watchers->count();
        }

        return $sent;
    }
}
