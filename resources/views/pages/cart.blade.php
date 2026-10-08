<x-layouts::app :title="__('Iepirkumu saraksti')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div>
            <flux:heading size="xl">{{ __('Iepirkumu saraksti') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Izveido vairākus sarakstus un pārslēdzies starp tiem.') }}</flux:text>
        </div>

        @if (session('success'))
            <flux:text class="text-emerald-600 dark:text-emerald-400">{{ session('success') }}</flux:text>
        @endif

        <form method="POST" action="{{ route('cart.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-48">
                <label for="name" class="mb-1 block text-sm font-medium">{{ __('Jauna saraksta nosaukums') }}</label>
                <input type="text" name="name" id="name" required placeholder="{{ __('Piem., Nedēļas iepirkumi') }}"
                    class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900" />
            </div>
            <button type="submit" class="rounded-lg bg-linear-to-r from-emerald-400 to-emerald-700 px-4 py-2 text-sm font-semibold text-ink transition hover:brightness-110">
                {{ __('Izveidot sarakstu') }}
            </button>
        </form>

        @if ($lists->isEmpty())
            <flux:text class="mt-2">{{ __('Tev nav neviena atvērta iepirkumu saraksta.') }}</flux:text>
        @else
            <div class="mt-2 space-y-3">
                @foreach ($lists as $list)
                    @php
                        $itemCount = (int) $list->item_count;
                        $total = (float) $list->items_total;
                        $isActive = $list->id === $activeListId;
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4 shadow-sm transition hover:shadow-md dark:border-neutral-700 {{ $isActive ? 'border-emerald-500' : 'border-neutral-200' }}">
                        <a href="{{ route('cart.show', $list) }}" wire:navigate class="min-w-48 flex-1">
                            <flux:heading size="sm" class="flex items-center gap-2">
                                {{ $list->name }}
                                @if ($isActive)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300">{{ __('Aktīvs') }}</span>
                                @endif
                                @if ($list->members_count > 0)
                                    <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">{{ __('Kopīgots ar :count', ['count' => $list->members_count]) }}</span>
                                @endif
                            </flux:heading>
                            <flux:text class="mt-1">
                                {{ __(':count preces', ['count' => $itemCount]) }} · {{ lv_number($total, 2) }} €
                            </flux:text>
                        </a>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('cart.update', $list) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="name" value="{{ $list->name }}"
                                    class="w-40 rounded-lg border border-neutral-300 px-2 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-900" />
                                <button type="submit" class="rounded-lg border border-neutral-300 px-3 py-1.5 text-sm transition hover:border-emerald-500 hover:text-emerald-600 dark:border-neutral-600">
                                    {{ __('Pārdēvēt') }}
                                </button>
                            </form>
                            @unless ($isActive)
                                <form method="POST" action="{{ route('cart.activate', $list) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-neutral-300 px-3 py-1.5 text-sm transition hover:border-emerald-500 hover:text-emerald-600 dark:border-neutral-600">
                                        {{ __('Aktivizēt') }}
                                    </button>
                                </form>
                            @endunless
                            <form method="POST" action="{{ route('cart.destroy', $list) }}" onsubmit="return confirm('{{ __('Dzēst šo sarakstu?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 transition hover:text-red-700">{{ __('Dzēst') }}</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($sharedLists->isNotEmpty())
            <div class="mt-4">
                <flux:heading size="lg">{{ __('Kopīgoti ar mani') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Saraksti, kuros tevi uzaicināja citi lietotāji.') }}</flux:text>
            </div>
            <div class="space-y-3">
                @foreach ($sharedLists as $list)
                    @php
                        $itemCount = (int) $list->item_count;
                        $total = (float) $list->items_total;
                        $isActive = $list->id === $activeListId;
                        $canEdit = $list->pivot->role === \App\Models\ShoppingList::ROLE_EDITOR;
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4 shadow-sm transition hover:shadow-md dark:border-neutral-700 {{ $isActive ? 'border-emerald-500' : 'border-neutral-200' }}">
                        <a href="{{ route('cart.show', $list) }}" wire:navigate class="min-w-48 flex-1">
                            <flux:heading size="sm" class="flex items-center gap-2">
                                {{ $list->name }}
                                @if ($isActive)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300">{{ __('Aktīvs') }}</span>
                                @endif
                                <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">{{ $canEdit ? __('Rediģētājs') : __('Skatītājs') }}</span>
                            </flux:heading>
                            <flux:text class="mt-1">
                                {{ __('Īpašnieks: :name', ['name' => $list->user->name]) }} · {{ __(':count preces', ['count' => $itemCount]) }} · {{ lv_number($total, 2) }} €
                            </flux:text>
                        </a>
                        <div class="flex items-center gap-2">
                            @if ($canEdit && ! $isActive)
                                <form method="POST" action="{{ route('cart.activate', $list) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-neutral-300 px-3 py-1.5 text-sm transition hover:border-emerald-500 hover:text-emerald-600 dark:border-neutral-600">
                                        {{ __('Aktivizēt') }}
                                    </button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('cart.members.destroy', [$list, auth()->user()]) }}" onsubmit="return confirm('{{ __('Pamest šo sarakstu?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 transition hover:text-red-700">{{ __('Pamest') }}</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-4">
            <flux:heading size="lg">{{ __('Iepirkumu vēsture') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Pabeigtie saraksti un iztērētā summa pa mēnešiem.') }}</flux:text>
        </div>

        @php($maxMonthly = max(1, $monthlySpending->max('total')))
        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:text>{{ __('Iztērēts šomēnes') }}</flux:text>
                <flux:heading size="xl" class="mt-1">{{ lv_number($monthlySpending->last()['total'], 2) }} €</flux:heading>
                <flux:text class="mt-1 text-xs">{{ __('Pēdējos 6 mēnešos kopā: :total €', ['total' => lv_number($monthlySpending->sum('total'), 2)]) }}</flux:text>
            </div>
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <div class="flex h-28 items-end gap-3">
                    @foreach ($monthlySpending as $month)
                        <div class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                            <span class="text-xs text-neutral-500">{{ $month['total'] > 0 ? lv_number($month['total'], 0) . ' €' : '' }}</span>
                            <div class="w-full rounded-t bg-emerald-500 {{ $loop->last ? '' : 'opacity-60' }}" style="height: {{ max(2, $month['total'] / $maxMonthly * 80) }}%"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-1 flex gap-3">
                    @foreach ($monthlySpending as $month)
                        <span class="flex-1 truncate text-center text-xs text-neutral-500">{{ $month['label'] }}</span>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($completedLists->isEmpty())
            <flux:text>{{ __('Vēl nav neviena pabeigta saraksta. Kad iepirkšanās beigusies, atver sarakstu un spied "Pabeigt iepirkšanos".') }}</flux:text>
        @else
            <div class="divide-y divide-neutral-200 rounded-xl border border-neutral-200 dark:divide-neutral-700 dark:border-neutral-700">
                @foreach ($completedLists as $list)
                    <a href="{{ route('cart.show', $list) }}" wire:navigate class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                        <div class="min-w-0">
                            <flux:heading size="sm">{{ $list->name }}</flux:heading>
                            <flux:text class="text-xs">
                                {{ $list->completed_at->local()->format('d.m.Y') }}
                                · {{ __(':count preces', ['count' => (int) $list->item_count]) }}
                                @unless ($list->user_id === auth()->id())
                                    · {{ __('Īpašnieks: :name', ['name' => $list->user->name]) }}
                                @endunless
                            </flux:text>
                        </div>
                        <span class="font-semibold">{{ lv_number((float) $list->completed_total, 2) }} €</span>
                    </a>
                @endforeach
            </div>

            {{ $completedLists->links() }}
        @endif
    </div>
</x-layouts::app>
