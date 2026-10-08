<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $alerts = $user->notifications()->latest()->paginate(20);

        // Seeing an alert counts as reading it, but only the ones on this page: unread alerts on
        // later pages stay unread. The view still highlights what was new (loaded before this update).
        $user->notifications()
            ->whereKey($alerts->getCollection()->modelKeys())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('pages.notifications', [
            'invitations' => $user->shoppingListInvitations()
                ->with(['shoppingList.user', 'inviter'])
                ->latest()
                ->get(),
            'alerts' => $alerts,
            'watchedProducts' => $user->watchedProducts()->orderBy('title')->get(),
        ]);
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->whereKey($notification)->delete();

        return back();
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $request->user()->notifications()->delete();

        return back()->with('success', __('Cenu paziņojumi notīrīti'));
    }
}
