<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'store', 'category', 'description', 'price', 'original_price', 'current_price', 'unit_price', 'unit', 'image_url'])]
class Product extends Model
{
    public function priceHistory(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class);
    }

    /**
     * Price over time, oldest first, for sparklines.
     *
     * @return list<float>
     */
    public function pricePoints(): array
    {
        $history = $this->priceHistory->sortBy('created_at')->values();

        if ($history->isEmpty()) {
            return [];
        }

        return [
            (float) $history->first()->previous_price,
            ...$history->map(fn (ProductPriceHistory $entry) => (float) $entry->new_price),
        ];
    }

    /**
     * Discount of the latest price change in whole percent, or null if the price did not drop.
     */
    public function latestDropPercent(): ?int
    {
        $latest = $this->priceHistory->sortBy('created_at')->last();

        if (! $latest || (float) $latest->previous_price <= 0 || (float) $latest->new_price >= (float) $latest->previous_price) {
            return null;
        }

        return (int) round(((float) $latest->previous_price - (float) $latest->new_price) / (float) $latest->previous_price * 100);
    }

    /**
     * Whether the current price is the lowest seen in the last $days days, after a drop in that window.
     */
    public function isLowestPriceInDays(int $days = 30): bool
    {
        if ($this->current_price === null) {
            return false;
        }

        $prices = $this->priceHistory
            ->where('created_at', '>=', now()->subDays($days))
            ->flatMap(fn (ProductPriceHistory $entry) => [(float) $entry->previous_price, (float) $entry->new_price]);

        $current = (float) $this->current_price;

        return $prices->isNotEmpty() && $current <= $prices->min() && $current < $prices->max();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_price' => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }
}
