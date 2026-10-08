<x-layouts::app :title="__('Lietotāji')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('Lietotāji') }}</flux:heading>
                <flux:text class="mt-2">{{ trans_choice('{1} :count lietotājs|[2,*] :count lietotāji', $users->total()) }}</flux:text>
            </div>
            <flux:button :href="route('admin.index')" icon="arrow-left" wire:navigate>{{ __('Administrācija') }}</flux:button>
        </div>

        @if (session('error'))
            <flux:text class="text-amber-600 dark:text-amber-400" data-test="admin-error">{{ session('error') }}</flux:text>
        @endif
        @if (session('success'))
            <flux:text class="text-emerald-600 dark:text-emerald-400">{{ session('success') }}</flux:text>
        @endif

        <form method="GET" action="{{ route('admin.users') }}" class="flex max-w-md gap-2">
            <label for="user-search" class="sr-only">{{ __('Meklēt lietotājus') }}</label>
            <input
                id="user-search"
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="{{ __('Vārds vai e-pasts') }}"
                class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900"
            >
            <flux:button type="submit">{{ __('Meklēt') }}</flux:button>
        </form>

        <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                    <tr>
                        <th class="px-4 py-3">{{ __('Lietotājs') }}</th>
                        <th class="px-4 py-3">{{ __('Reģistrējies') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Saraksti') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Seko') }}</th>
                        <th class="px-4 py-3">{{ __('Loma') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $user->name }}</div>
                                <div class="text-xs text-neutral-500">
                                    {{ $user->email }}
                                    @if (! $user->email_verified_at)
                                        · <span class="text-amber-600 dark:text-amber-400">{{ __('nav apstiprināts') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap font-mono tabular-nums">{{ $user->created_at?->local()->format('d.m.Y') }}</td>
                            <td class="px-4 py-3 text-right font-mono tabular-nums">{{ $user->shopping_lists_count }}</td>
                            <td class="px-4 py-3 text-right font-mono tabular-nums">{{ $user->watched_products_count }}</td>
                            <td class="px-4 py-3">
                                @if ($user->is_admin)
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">{{ __('Administrators') }}</span>
                                @else
                                    <span class="text-neutral-500">{{ __('Lietotājs') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($user->is(auth()->user()))
                                    <span class="text-xs text-neutral-500">{{ __('Tu') }}</span>
                                @else
                                    <div class="flex justify-end gap-2">
                                        <form method="POST" action="{{ route('admin.users.update', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="is_admin" value="{{ $user->is_admin ? 0 : 1 }}">
                                            <flux:button type="submit" size="sm">
                                                {{ $user->is_admin ? __('Noņemt admin') : __('Padarīt par admin') }}
                                            </flux:button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm(@js(__('Dzēst lietotāju :name un visus viņa sarakstus?', ['name' => $user->name])))">
                                            @csrf
                                            @method('DELETE')
                                            <flux:button type="submit" size="sm" variant="danger" icon="trash" :aria-label="__('Dzēst')" />
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-neutral-500">{{ __('Neviens lietotājs neatbilst meklējumam.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $users->links() }}
    </div>
</x-layouts::app>
