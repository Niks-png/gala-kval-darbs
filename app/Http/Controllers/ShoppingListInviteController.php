<?php

namespace App\Http\Controllers;

use App\Models\ShoppingList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShoppingListInviteController extends Controller
{
    /**
     * Create (or regenerate) the list's invite link.
     */
    public function store(Request $request, ShoppingList $shoppingList): RedirectResponse
    {
        Gate::authorize('manage', $shoppingList);

        $validated = $request->validate([
            'role' => ['required', Rule::in(ShoppingList::ROLES)],
        ]);

        $shoppingList->forceFill([
            'invite_token' => Str::random(40),
            'invite_role' => $validated['role'],
        ])->save();

        return back()
            ->with('success', 'Uzaicinājuma saite izveidota')
            ->with('members_modal', true);
    }

    /**
     * Disable the invite link so it can no longer be used.
     */
    public function destroy(ShoppingList $shoppingList): RedirectResponse
    {
        Gate::authorize('manage', $shoppingList);

        $shoppingList->forceFill(['invite_token' => null])->save();

        return back()
            ->with('success', 'Uzaicinājuma saite atslēgta')
            ->with('members_modal', true);
    }

    /**
     * Join a list through its invite link.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $shoppingList = ShoppingList::query()->where('invite_token', $token)->firstOrFail();
        $user = $request->user();

        if ($shoppingList->roleFor($user) !== null) {
            return to_route('cart.show', $shoppingList);
        }

        $shoppingList->members()->attach($user->id, ['role' => $shoppingList->invite_role]);
        $shoppingList->invitations()->where('user_id', $user->id)->delete();

        return to_route('cart.show', $shoppingList)->with('success', 'Tu pievienojies sarakstam');
    }
}
