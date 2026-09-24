<?php

namespace App\Http\Controllers;

use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShoppingListMemberController extends Controller
{
    public function store(Request $request, ShoppingList $shoppingList): RedirectResponse
    {
        Gate::authorize('manage', $shoppingList);

        $validated = $request->validate([
            'email' => ['required', 'email', Rule::exists('users', 'email')],
            'role' => ['required', Rule::in(ShoppingList::ROLES)],
        ], [
            'email.exists' => 'Lietotājs ar šādu e-pastu nav atrasts.',
        ]);

        $member = User::query()->where('email', $validated['email'])->firstOrFail();

        if ($shoppingList->isOwnedBy($member)) {
            throw ValidationException::withMessages(['email' => 'Tu jau esi šī saraksta īpašnieks.']);
        }

        if ($shoppingList->members()->whereKey($member->id)->exists()) {
            throw ValidationException::withMessages(['email' => 'Šis lietotājs jau ir pievienots sarakstam.']);
        }

        if ($shoppingList->invitations()->where('user_id', $member->id)->exists()) {
            throw ValidationException::withMessages(['email' => 'Šim lietotājam jau ir nosūtīts uzaicinājums.']);
        }

        $shoppingList->invitations()->create([
            'user_id' => $member->id,
            'invited_by' => $request->user()->id,
            'role' => $validated['role'],
        ]);

        return back()->with('success', "Uzaicinājums nosūtīts: {$member->name}")->with('members_modal', true);
    }

    public function update(Request $request, ShoppingList $shoppingList, User $user): RedirectResponse
    {
        Gate::authorize('manage', $shoppingList);

        $validated = $request->validate([
            'role' => ['required', Rule::in(ShoppingList::ROLES)],
        ]);

        abort_unless($shoppingList->members()->whereKey($user->id)->exists(), 404);

        $shoppingList->members()->updateExistingPivot($user->id, ['role' => $validated['role']]);

        return back()->with('success', 'Tiesības atjauninātas')->with('members_modal', true);
    }

    public function destroy(Request $request, ShoppingList $shoppingList, User $user): RedirectResponse
    {
        $leaving = $request->user()->is($user);

        if (! $leaving) {
            Gate::authorize('manage', $shoppingList);
        }

        abort_unless($shoppingList->members()->whereKey($user->id)->exists(), 404);

        $shoppingList->members()->detach($user->id);

        if ($leaving) {
            return to_route('cart')->with('success', 'Tu pameti sarakstu');
        }

        return back()->with('success', 'Lietotājs noņemts no saraksta')->with('members_modal', true);
    }
}
