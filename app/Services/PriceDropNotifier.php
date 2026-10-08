<?php

namespace App\Services;

use App\Models\ProductPriceHistory;
use App\Notifications\PriceDropped;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class PriceDropNotifier
{
    /**
     * Tell followers about every recorded price drop not announced yet.
     *
     * Works from the saved price history, not from an import in progress, so it
     * runs only after the new prices are committed. Each drop is sent and marked
     * as sent in one transaction, so it is announced exactly once; if sending
     * fails, the drop stays unsent and the next call tries again.
     *
     * @return int Number of notifications sent
     */
    public function sendPending(): int
    {
        $sent = 0;

        $drops = ProductPriceHistory::query()
            ->whereNull('alerts_sent_at')
            ->where('previous_price', '>', 0)
            ->whereColumn('new_price', '<', 'previous_price')
            ->with([
                'product.watchers',
                // Only the window isLowestPriceInDays() looks at, not the whole history.
                'product.priceHistory' => fn ($query) => $query->where('created_at', '>=', now()->subDays(30)),
            ])
            ->orderBy('id')
            ->get();

        foreach ($drops as $drop) {
            DB::transaction(function () use ($drop, &$sent): void {
                $product = $drop->product;

                if ($product->watchers->isNotEmpty()) {
                    Notification::send($product->watchers, new PriceDropped(
                        $product,
                        (float) $drop->previous_price,
                        (float) $drop->new_price,
                        $product->isLowestPriceInDays(30),
                    ));

                    $sent += $product->watchers->count();
                }

                $drop->forceFill(['alerts_sent_at' => now()])->save();
            });
        }

        return $sent;
    }
}
