<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\LastAdminException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        // ?q[]=x sends an array; treat anything but text as no search instead of crashing.
        $search = is_string($q = $request->query('q')) ? trim($q) : '';

        $users = User::query()
            ->withCount(['shoppingLists', 'watchedProducts'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereContains('name', $search)
                ->orWhereContains('email', $search)))
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('pages.admin.users', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        // Admins can't lock themselves out of the panel.
        abort_if($user->is($request->user()), 403);

        $validated = $request->validate(['is_admin' => ['required', 'boolean']]);

        if ($validated['is_admin']) {
            $user->forceFill(['is_admin' => true])->save();
        } elseif (! $user->revokeAdmin()) {
            return back()->with('error', __('Sistēmā jāpaliek vismaz vienam administratoram.'));
        }

        return back()->with('success', $user->is_admin
            ? __(':name tagad ir administrators.', ['name' => $user->name])
            : __(':name vairs nav administrators.', ['name' => $user->name]));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403);

        // Shared lists they own pass to another member first (User::booted); the rest of their
        // lists, memberships, invitations and follows are removed by cascading foreign keys.
        // One transaction, so a failure cannot leave lists handed over but the user still there.
        try {
            DB::transaction(fn () => $user->delete());
        } catch (LastAdminException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('Lietotājs :name dzēsts.', ['name' => $user->name]));
    }
}
