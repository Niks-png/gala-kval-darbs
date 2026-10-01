<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $users = User::query()
            ->withCount(['shoppingLists', 'watchedProducts'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
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

        $user->forceFill(['is_admin' => $validated['is_admin']])->save();

        return back()->with('success', $user->is_admin
            ? __(':name tagad ir administrators.', ['name' => $user->name])
            : __(':name vairs nav administrators.', ['name' => $user->name]));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403);

        // Their lists, memberships, invitations and follows are removed by cascading foreign keys.
        $user->delete();

        return back()->with('success', __('Lietotājs :name dzēsts.', ['name' => $user->name]));
    }
}
