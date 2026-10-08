<?php

namespace App\Models;

use App\Exceptions\LastAdminException;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_admin
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_admin' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Lists are deleted with their owner (foreign key cascade), but a shared list belongs
        // to its members too, so hand it over first. Covers both the user and an admin deleting.
        static::deleting(function (User $user): void {
            $user->ensureNotLastAdmin();
            $user->handOverSharedShoppingLists();
        });
    }

    /**
     * Whether this is the only admin left, for showing why an action is not allowed.
     */
    public function isLastAdmin(): bool
    {
        return $this->is_admin && static::query()->where('is_admin', true)->count() <= 1;
    }

    /**
     * Take away admin rights, unless this is the last admin.
     *
     * @return bool False if this is the last admin
     */
    public function revokeAdmin(): bool
    {
        return DB::transaction(function (): bool {
            if ($this->lockedAdminIds()->all() === [$this->id]) {
                return false;
            }

            $this->forceFill(['is_admin' => false])->save();

            return true;
        });
    }

    /**
     * Stop the last admin from being deleted. Run inside the deleting transaction, so the
     * admin rows stay locked until the delete is done.
     */
    public function ensureNotLastAdmin(): void
    {
        if ($this->is_admin && $this->lockedAdminIds()->all() === [$this->id]) {
            throw new LastAdminException;
        }
    }

    /**
     * Admin ids, with their rows locked so two admins changing each other at the same moment
     * are handled one after the other and cannot leave nobody in charge.
     *
     * @return Collection<int, int>
     */
    private function lockedAdminIds(): Collection
    {
        return static::query()->where('is_admin', true)->lockForUpdate()->orderBy('id')->pluck('id');
    }

    public function shoppingLists(): HasMany
    {
        return $this->hasMany(ShoppingList::class);
    }

    /**
     * Give each list this user owns to another member: the longest-standing editor, else the
     * longest-standing viewer. Lists nobody else uses are left to be deleted with the user.
     */
    public function handOverSharedShoppingLists(): void
    {
        foreach ($this->shoppingLists()->has('members')->get() as $list) {
            $heir = $list->members()
                ->orderByRaw('CASE WHEN shopping_list_user.role = ? THEN 0 ELSE 1 END', [ShoppingList::ROLE_EDITOR])
                ->orderBy('shopping_list_user.created_at')
                ->first();

            DB::transaction(function () use ($list, $heir): void {
                $list->forceFill(['user_id' => $heir->id])->save();
                // The new owner is not also a member.
                $list->members()->detach($heir->id);
            });
        }
    }

    public function shoppingListInvitations(): HasMany
    {
        return $this->hasMany(ShoppingListInvitation::class);
    }

    /**
     * Products the user follows for price-drop alerts.
     */
    public function watchedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_watches')->withTimestamps();
    }

    public function isWatching(Product $product): bool
    {
        return $this->watchedProducts()->whereKey($product->id)->exists();
    }

    public function sharedShoppingLists(): BelongsToMany
    {
        return $this->belongsToMany(ShoppingList::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
