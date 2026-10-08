<?php

namespace App\Http\Controllers;

use App\Models\ShoppingList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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
            'invite_expires_at' => now()->addDays(ShoppingList::INVITE_LINK_DAYS),
        ])->save();

        return back()
            ->with('success', __('Uzaicinājuma saite izveidota'))
            ->with('members_modal', true);
    }

    /**
     * Disable the invite link so it can no longer be used.
     */
    public function destroy(ShoppingList $shoppingList): RedirectResponse
    {
        Gate::authorize('manage', $shoppingList);

        $shoppingList->forceFill(['invite_token' => null, 'invite_expires_at' => null])->save();

        return back()
            ->with('success', __('Uzaicinājuma saite atslēgta'))
            ->with('members_modal', true);
    }

    /**
     * Opening an invite link only shows what it is. Joining needs the button (a POST), so link
     * previews and prefetching cannot add anyone to a list.
     */
    public function show(Request $request, string $token): View|RedirectResponse|Response
    {
        $shoppingList = ShoppingList::forInviteToken($token);

        if ($shoppingList === null) {
            return response()->view('pages.invite-join', ['list' => null], 404);
        }

        if ($shoppingList->roleFor($request->user()) !== null) {
            return to_route('cart.show', $shoppingList);
        }

        return view('pages.invite-join', [
            'list' => $shoppingList->load('user'),
            'token' => $token,
        ]);
    }

    /**
     * Join a list through its invite link.
     */
    public function join(Request $request, string $token): RedirectResponse
    {
        $shoppingList = ShoppingList::forInviteToken($token);
        abort_if($shoppingList === null, 404);

        $user = $request->user();

        if ($shoppingList->roleFor($user) === null) {
            $shoppingList->addMember($user, $shoppingList->invite_role);
            $shoppingList->invitations()->where('user_id', $user->id)->delete();
        }

        return to_route('cart.show', $shoppingList)->with('success', __('Tu pievienojies sarakstam'));
    }
}
