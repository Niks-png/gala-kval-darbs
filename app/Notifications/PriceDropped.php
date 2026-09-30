<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Notifications\Notification;

class PriceDropped extends Notification
{
    public function __construct(
        public Product $product,
        public float $previousPrice,
        public float $newPrice,
        public bool $lowestIn30Days = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Snapshot of the product so the alert still reads correctly after later price changes.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'product_id' => $this->product->id,
            'title' => $this->product->title,
            'store' => $this->product->store,
            'previous_price' => $this->previousPrice,
            'new_price' => $this->newPrice,
            'drop_percent' => (int) round(($this->previousPrice - $this->newPrice) / $this->previousPrice * 100),
            'lowest_30_days' => $this->lowestIn30Days,
        ];
    }
}
