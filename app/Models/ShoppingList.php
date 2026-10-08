<?php

namespace App\Models;

use Closure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

#[Fillable(['name'])]
class ShoppingList extends Model
{
    public const ROLE_EDITOR = 'editor';

    public const ROLE_VIEWER = 'viewer';

    public const ROLES = [self::ROLE_EDITOR, self::ROLE_VIEWER];

    /**
     * Most of one product a list can hold, however it is added.
     */
    public const MAX_QUANTITY = 99;

    /**
     * How long an invite link works after it is created.
     */
    public const INVITE_LINK_DAYS = 7;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'shopping_list_items')
            ->withPivot('quantity', 'checked_at', 'checked_by', 'price_at_completion')
            ->withTimestamps();
    }

    /**
     * Add to an item's quantity (or add the item), up to MAX_QUANTITY.
     *
     * @return bool False if the list was finished meanwhile
     */
    public function addProduct(int $productId, int $quantity = 1): bool
    {
        return $this->changeWhileOpen(function () use ($productId, $quantity): void {
            // One UPDATE that adds to the stored value, so two editors adding at once both count.
            $updated = $this->items($productId)->update([
                'quantity' => DB::raw('CASE WHEN quantity + '.$quantity.' > '.self::MAX_QUANTITY.' THEN '.self::MAX_QUANTITY.' ELSE quantity + '.$quantity.' END'),
                'updated_at' => now(),
            ]);

            if ($updated === 0) {
                $this->products()->attach($productId, ['quantity' => min($quantity, self::MAX_QUANTITY)]);
            }
        });
    }

    /**
     * Take one off an item's quantity; at one, remove the item.
     *
     * @return bool False if the list was finished meanwhile
     */
    public function decreaseProduct(int $productId): bool
    {
        return $this->changeWhileOpen(function () use ($productId): void {
            $decreased = $this->items($productId)->where('quantity', '>', 1)
                ->update(['quantity' => DB::raw('quantity - 1'), 'updated_at' => now()]);

            if ($decreased === 0) {
                $this->items($productId)->delete();
            }
        });
    }

    /**
     * @return bool False if the list was finished meanwhile
     */
    public function removeProduct(int $productId): bool
    {
        return $this->changeWhileOpen(fn () => $this->items($productId)->delete());
    }

    /**
     * Tick an item off (or back on) and remember who did it.
     *
     * @return bool False if the list was finished meanwhile
     */
    public function toggleProductChecked(int $productId, User $user): bool
    {
        return $this->changeWhileOpen(function () use ($productId, $user): void {
            // Read under the list lock, so two editors ticking at once cannot both act on the same state.
            $item = $this->items($productId)->first(['checked_at']);

            if ($item === null) {
                return;
            }

            $this->items($productId)->update([
                'checked_at' => $item->checked_at === null ? now() : null,
                'checked_by' => $item->checked_at === null ? $user->id : null,
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Run a change to the items while holding a lock on this list's row, and only if the
     * list is still open. Every item change and finishing the list take this lock, so they
     * happen one after another and nothing can change a list that was just finished.
     */
    private function changeWhileOpen(Closure $change): bool
    {
        return DB::transaction(function () use ($change): bool {
            $list = static::query()->lockForUpdate()->find($this->id);

            if ($list === null || $list->isCompleted()) {
                return false;
            }

            $change();

            return true;
        });
    }

    private function items(int $productId): QueryBuilder
    {
        return DB::table('shopping_list_items')
            ->where('shopping_list_id', $this->id)
            ->where('product_id', $productId);
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

    /**
     * Add item_count (sum of quantities) and items_total (euros, saved prices for finished
     * lists) worked out by the database, so list overviews do not load every product.
     */
    public function scopeWithItemTotals(Builder $query): void
    {
        $items = fn () => DB::table('shopping_list_items')
            ->whereColumn('shopping_list_items.shopping_list_id', 'shopping_lists.id');

        $query->addSelect([
            'item_count' => $items()->selectRaw('COALESCE(SUM(shopping_list_items.quantity), 0)'),
            'items_total' => $items()
                ->join('products', 'products.id', '=', 'shopping_list_items.product_id')
                ->selectRaw('COALESCE(SUM(COALESCE(shopping_list_items.price_at_completion, products.current_price, 0) * shopping_list_items.quantity), 0)'),
        ]);
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
     * Finish shopping. Only ticked items count as bought. Each one's price is saved with it,
     * so the purchase history keeps the prices of that day, and the total is their sum.
     * Done under the same lock as item changes, so no edit can slip in between reading
     * the items and saving the total, and the list cannot be changed afterwards.
     *
     * @return bool False if nothing is ticked or the list was already finished
     */
    public function complete(): bool
    {
        return DB::transaction(function (): bool {
            $list = static::query()->lockForUpdate()->find($this->id);

            if ($list === null || $list->isCompleted()) {
                return false;
            }

            $bought = $list->products()->wherePivotNotNull('checked_at')->get();

            if ($bought->isEmpty()) {
                return false;
            }

            foreach ($bought as $product) {
                $list->products()->updateExistingPivot($product->id, ['price_at_completion' => $product->current_price]);
            }

            $list->forceFill([
                'completed_at' => now(),
                'completed_total' => $bought->sum(fn (Product $product): float => (float) $product->current_price * $product->pivot->quantity),
            ])->save();

            $this->setRawAttributes($list->getAttributes(), true);

            return true;
        });
    }

    /**
     * Start a new open list for $user with the same products and quantities, for buying
     * the same things again. The finished list stays as it was.
     */
    public function copyFor(User $user): self
    {
        return DB::transaction(function () use ($user): self {
            $copy = $user->shoppingLists()->create(['name' => $this->name]);

            $copy->products()->attach(
                $this->products->mapWithKeys(fn (Product $product): array => [$product->id => ['quantity' => $product->pivot->quantity]])
            );

            return $copy;
        });
    }

    /**
     * What an item cost: its saved price once the list is finished, today's price before that.
     */
    public static function itemPrice(Product $product): ?float
    {
        $price = $product->pivot->price_at_completion ?? $product->current_price;

        return $price === null ? null : (float) $price;
    }

    /**
     * Find the list an invite link points to, if the link is still switched on and not expired.
     */
    public static function forInviteToken(string $token): ?self
    {
        return static::query()
            ->where('invite_token', $token)
            ->where('invite_expires_at', '>', now())
            ->first();
    }

    /**
     * Add $user with $role unless they are the owner or already a member. Safe to run twice at
     * once (e.g. accepting in two tabs): the second one changes nothing instead of failing.
     */
    public function addMember(User $user, string $role): void
    {
        if ($this->isOwnedBy($user)) {
            return;
        }

        DB::table('shopping_list_user')->insertOrIgnore([
            'shopping_list_id' => $this->id,
            'user_id' => $user->id,
            'role' => in_array($role, self::ROLES, true) ? $role : self::ROLE_VIEWER,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
            'invite_expires_at' => 'datetime',
        ];
    }
}
