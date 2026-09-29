<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class ShoppingList extends Model
{
    public const ROLE_EDITOR = 'editor';

    public const ROLE_VIEWER = 'viewer';

    public const ROLES = [self::ROLE_EDITOR, self::ROLE_VIEWER];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'shopping_list_items')
            ->withPivot('quantity', 'checked_at', 'checked_by')
            ->withTimestamps();
    }

    public function addProduct(int $productId, int $quantity = 1): void
    {
        $existing = $this->products()->whereKey($productId)->first();

        if ($existing) {
            $this->products()->updateExistingPivot($productId, [
                'quantity' => $existing->pivot->quantity + $quantity,
            ]);
        } else {
            $this->products()->attach($productId, ['quantity' => $quantity]);
        }
    }

    public function decreaseProduct(int $productId): void
    {
        $existing = $this->products()->whereKey($productId)->first();
        $quantity = ($existing?->pivot->quantity ?? 0) - 1;

        if ($quantity > 0) {
            $this->products()->updateExistingPivot($productId, ['quantity' => $quantity]);
        } else {
            $this->products()->detach($productId);
        }
    }

    /**
     * Tick an item off (or back on) and remember who did it.
     */
    public function toggleProductChecked(int $productId, User $user): void
    {
        $existing = $this->products()->whereKey($productId)->first();

        if (! $existing) {
            return;
        }

        $checked = $existing->pivot->checked_at === null;

        $this->products()->updateExistingPivot($productId, [
            'checked_at' => $checked ? now() : null,
            'checked_by' => $checked ? $user->id : null,
        ]);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ShoppingListInvitation::class);
    }

    /**
     * Lists the user owns or can edit as an invited editor.
     */
    public function scopeEditableBy(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('user_id', $user->id)
            ->orWhereHas('members', fn (Builder $members) => $members
                ->whereKey($user->id)
                ->where('shopping_list_user.role', self::ROLE_EDITOR)));
    }

    /**
     * Lists the user owns or was invited to, in any role.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('user_id', $user->id)
            ->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->id)));
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->whereNotNull('completed_at');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Finish shopping: remember when and how much was spent. If items were
     * ticked off, only those count as bought; otherwise the whole list does.
     */
    public function complete(): void
    {
        $products = $this->products()->get();
        $bought = $products->whereNotNull('pivot.checked_at');
        $counted = $bought->isNotEmpty() ? $bought : $products;

        $this->forceFill([
            'completed_at' => now(),
            'completed_total' => $counted->sum(fn (Product $product): float => (float) $product->current_price * $product->pivot->quantity),
        ])->save();
    }

    public function reopen(): void
    {
        $this->forceFill(['completed_at' => null, 'completed_total' => null])->save();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function roleFor(User $user): ?string
    {
        if ($this->isOwnedBy($user)) {
            return 'owner';
        }

        return $this->members()->whereKey($user->id)->first()?->pivot->role;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'completed_total' => 'decimal:2',
        ];
    }
}
