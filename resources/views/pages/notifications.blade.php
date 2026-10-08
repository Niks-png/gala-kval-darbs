<x-layouts::app :title="__('Paziņojumi')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div>
            <flux:heading size="xl">{{ __('Paziņojumi') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Uzaicinājumi uz iepirkumu sarakstiem un cenu kritumi produktiem, kuriem tu seko.') }}</flux:text>
        </div>

        @if (session('success'))
            <flux:text class="text-emerald-600 dark:text-emerald-400">{{ session('success') }}</flux:text>
        @endif

        @if ($invitations->isEmpty() && $alerts->isEmpty())
            <div class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                <flux:icon.bell class="size-8 text-neutral-400" />
                <flux:text>{{ __('Tev nav jaunu paziņojumu.') }}</flux:text>
                @if ($watchedProducts->isEmpty())
                    <flux:text class="text-sm">{{ __('Atver produktu un spied "Sekot cenai", lai saņemtu ziņu, kad tā cena kritīsies.') }}</flux:text>
                @endif
            </div>
        @endif

        @if ($invitations->isNotEmpty())
            <section class="space-y-3">
                <flux:heading size="lg">{{ __('Uzaicinājumi') }}</flux:heading>
                @foreach ($invitations as $invitation)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-700 dark:bg-plum dark:text-emerald-300">
                                {{ $invitation->inviter->initials() }}
                            </span>
                            <div class="min-w-0">
                                <flux:heading size="sm">
                                    {{ __(':name tevi uzaicināja uz sarakstu ":list"', ['name' => $invitation->inviter->name, 'list' => $invitation->shoppingList->name]) }}
                                </flux:heading>
                                <flux:text>
                                    {{ $invitation->role === \App\Models\ShoppingList::ROLE_EDITOR ? __('Loma: Rediģētājs') : __('Loma: Skatītājs') }}
                                    · {{ $invitation->created_at->diffForHumans() }}
                                </flux:text>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('invitations.destroy', $invitation) }}">
                                @csrf
                                @method('DELETE')
                                <flux:button type="submit" size="sm">{{ __('Noraidīt') }}</flux:button>
                            </form>
                            <form method="POST" action="{{ route('invitations.accept', $invitation) }}">
                                @csrf
                                <flux:button type="submit" size="sm" variant="primary">{{ __('Pieņemt') }}</flux:button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        @if ($alerts->isNotEmpty())
            <section class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('Cenu kritumi') }}</flux:heading>
                    <form method="POST" action="{{ route('notifications.destroy-all') }}">
                        @csrf
                        @method('DELETE')
                        <flux:button type="submit" size="sm" variant="ghost">{{ __('Notīrīt visus') }}</flux:button>
                    </form>
                </div>

                @foreach ($alerts as $alert)
                    @php($data = $alert->data)
                    <div @class([
                        'flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900',
                        'shadow-[inset_3px_0_0_var(--color-emerald-400)]' => $alert->read_at === null,
                    ])>
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-green-400/15 font-mono text-xs font-semibold text-green-400">
                                −{{ $data['drop_percent'] }}%
                            </span>
                            <div class="min-w-0">
                                <flux:heading size="sm" class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('products.show', $data['product_id']) }}" wire:navigate class="hover:text-emerald-700 dark:hover:text-emerald-400">{{ $data['title'] }}</a>
                                    @if ($alert->read_at === null)
                                        <span class="rounded-full bg-emerald-400/15 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400">{{ __('Jauns') }}</span>
                                    @endif
                                </flux:heading>
                                <flux:text class="font-mono text-sm tabular-nums">
                                    <span class="line-through">{{ lv_number($data['previous_price']) }} €</span>
                                    → <span class="font-semibold text-green-400">{{ lv_number($data['new_price']) }} €</span>
                                    · {{ $data['store'] }}
                                    @if ($data['lowest_30_days'])
                                        · <span class="text-green-400">{{ __('30 d. zemākā') }}</span>
                                    @endif
                                </flux:text>
                                <flux:text class="text-xs">{{ $alert->created_at->diffForHumans() }}</flux:text>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('notifications.destroy', $alert->id) }}">
                            @csrf
                            @method('DELETE')
                            <flux:button type="submit" size="sm" variant="ghost" icon="x-mark" :aria-label="__('Dzēst paziņojumu')" />
                        </form>
                    </div>
                @endforeach

                {{ $alerts->links() }}
            </section>
        @endif

        @if ($watchedProducts->isNotEmpty())
            <section class="space-y-3">
                <flux:heading size="lg">{{ __('Produkti, kuriem seko') }}</flux:heading>
                <div class="divide-y divide-neutral-200 rounded-xl border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-700 dark:bg-neutral-900">
                    @foreach ($watchedProducts as $watched)
                        <div class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <a href="{{ route('products.show', $watched) }}" wire:navigate class="block truncate text-sm font-medium text-zinc-900 hover:text-emerald-700 dark:text-zinc-100 dark:hover:text-emerald-400">{{ $watched->title }}</a>
                                <flux:text class="text-xs">
                                    {{ $watched->store }} · <span class="font-mono tabular-nums">{{ $watched->current_price !== null ? lv_number($watched->current_price).' €' : '—' }}</span>
                                </flux:text>
                            </div>
                            <form method="POST" action="{{ route('products.unwatch', $watched) }}">
                                @csrf
                                @method('DELETE')
                                <flux:button type="submit" size="sm" variant="ghost">{{ __('Nesekot') }}</flux:button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts::app>
