<?php

namespace App\Http\Controllers;

use App\Models\ShoppingListInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShoppingListInvitationController extends Controller
{
    public function index(Request $request): View
    {
        $invitations = $request->user()->shoppingListInvitations()
            ->with(['shoppingList.user', 'inviter'])
            ->latest()
            ->get();

        return view('pages.notifications', [
            'invitations' => $invitations,
        ]);
    }

    public function accept(Request $request, ShoppingListInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->user_id === $request->user()->id, 403);

        $list = $invitation->shoppingList;

        if ($list->roleFor($request->user()) === null) {
            $list->members()->attach($invitation->user_id, ['role' => $invitation->role]);
        }

        $invitation->delete();

        return to_route('cart.show', $list)->with('success', 'Tu pievienojies sarakstam');
    }

    /**
     * The invitee rejects the invite, or the list owner cancels it.
     */
    public function destroy(Request $request, ShoppingListInvitation $invitation): RedirectResponse
    {
        $user = $request->user();
        $isInvitee = $invitation->user_id === $user->id;

        abort_unless($isInvitee || $invitation->shoppingList->isOwnedBy($user), 403);

        $invitation->delete();

        if ($isInvitee) {
            return back()->with('success', 'Uzaicinājums noraidīts');
        }

        return back()->with('success', 'Uzaicinājums atsaukts')->with('members_modal', true);
    }
}
