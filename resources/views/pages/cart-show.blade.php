<x-layouts::app :title="$list->name">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div>
            <a href="{{ route('cart') }}" wire:navigate class="text-sm text-emerald-600 hover:text-emerald-700">
                {{ __('← Visi saraksti') }}
            </a>
            <flux:heading size="xl" class="mt-2">{{ $list->name }}</flux:heading>

            @if ($list->products->isEmpty())
                <flux:text class="mt-2">{{ __('Šis saraksts ir tukšs.') }}</flux:text>
            @else
                <div class="mt-6 space-y-3">
                    @foreach ($list->products as $product)
                        <div class="flex items-center justify-between rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                            <div>
                                <flux:heading size="sm">{{ $product->title }}</flux:heading>
                                <flux:text>{{ $product->store }}</flux:text>
                            </div>
                            <div class="flex items-center gap-3">
                                <form method="POST" action="{{ route('cart.lists.items.decrease', [$list, $product]) }}">
                                    @csrf
                                    <button type="submit" class="flex size-8 items-center justify-center rounded-full border border-neutral-300 text-lg transition hover:border-emerald-500 hover:text-emerald-600 dark:border-neutral-600" aria-label="{{ __('Decrease quantity') }}">-</button>
                                </form>
                                <span class="min-w-6 text-center">{{ $product->pivot->quantity }}</span>
                                <form method="POST" action="{{ route('cart.lists.items.store', [$list, $product]) }}">
                                    @csrf
                                    <button type="submit" class="flex size-8 items-center justify-center rounded-full border border-neutral-300 text-lg transition hover:border-emerald-500 hover:text-emerald-600 dark:border-neutral-600" aria-label="{{ __('Increase quantity') }}">+</button>
                                </form>
                                <flux:heading size="sm">
                                    {{ $product->current_price !== null ? number_format((float) $product->current_price * $product->pivot->quantity, 2) . ' €' : '—' }}
                                </flux:heading>
                                <form method="POST" action="{{ route('cart.lists.items.destroy', [$list, $product]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-600 transition hover:text-red-700" aria-label="{{ __('Remove item') }}">{{ __('Remove') }}</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
