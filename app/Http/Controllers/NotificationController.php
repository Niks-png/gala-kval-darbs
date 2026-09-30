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

        $alerts = $user->notifications()->latest()->limit(50)->get();

        // Opening the page counts as reading; the view still highlights what was new.
        $user->unreadNotifications()->update(['read_at' => now()]);

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
