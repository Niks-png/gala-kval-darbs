<?php

namespace App\Policies;

use App\Models\ShoppingList;
use App\Models\User;

class ShoppingListPolicy
{
    /**
     * Owners and every invited member can see the list.
     */
    public function view(User $user, ShoppingList $shoppingList): bool
    {
        return $shoppingList->roleFor($user) !== null;
    }

    /**
     * Owners and editors can add, change and remove items while the list is open.
     */
    public function editItems(User $user, ShoppingList $shoppingList): bool
    {
        return ! $shoppingList->isCompleted() && $this->complete($user, $shoppingList);
    }

    /**
     * Owners and editors can finish shopping and reopen a finished list.
     */
    public function complete(User $user, ShoppingList $shoppingList): bool
    {
        return in_array($shoppingList->roleFor($user), ['owner', ShoppingList::ROLE_EDITOR], true);
    }

    /**
     * Only the owner can rename, delete and manage members.
     */
    public function manage(User $user, ShoppingList $shoppingList): bool
    {
        return $shoppingList->isOwnedBy($user);
    }
}
