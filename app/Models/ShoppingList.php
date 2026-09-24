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
            ->withPivot('quantity')
            ->withTimestamps();
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
}
