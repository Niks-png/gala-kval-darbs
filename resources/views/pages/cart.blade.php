<x-layouts::app :title="__('Cart')">
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
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">
                {{ __('Izveidot sarakstu') }}
            </button>
        </form>

        @if ($lists->isEmpty())
            <flux:text class="mt-2">{{ __('Tev vēl nav neviena iepirkumu saraksta.') }}</flux:text>
        @else
            <div class="mt-2 space-y-3">
                @foreach ($lists as $list)
                    @php
                        $itemCount = $list->products->sum('pivot.quantity');
                        $total = $list->products->sum(fn ($product) => (float) ($product->current_price ?? 0) * $product->pivot->quantity);
                        $isActive = $list->id === $activeListId;
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4 shadow-sm transition hover:shadow-md dark:border-neutral-700 {{ $isActive ? 'border-emerald-500' : 'border-neutral-200' }}">
                        <a href="{{ route('cart.show', $list) }}" wire:navigate class="min-w-48 flex-1">
                            <flux:heading size="sm" class="flex items-center gap-2">
                                {{ $list->name }}
                                @if ($isActive)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300">{{ __('Aktīvs') }}</span>
                                @endif
                            </flux:heading>
                            <flux:text class="mt-1">
                                {{ __(':count preces', ['count' => $itemCount]) }} · {{ number_format($total, 2) }} €
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
    </div>
</x-layouts::app>
