<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['title', 'store', 'original_price', 'current_price', 'unit_price', 'unit'])]
class Product extends Model
{
    public function priceHistory(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class);
    }

    public function latestPriceHistory(): HasOne
    {
        return $this->hasOne(ProductPriceHistory::class)->latestOfMany();
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
