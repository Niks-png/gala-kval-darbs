@php
    $history = $product->priceHistory;
    $prices = $history->isNotEmpty()
        ? [(float) $history->first()->previous_price, ...$history->pluck('new_price')->map(fn ($price) => (float) $price)]
        : [];
    $latest = $history->last();
    $trend = ! $latest || (float) $latest->previous_price === (float) $latest->new_price
        ? 'flat'
        : ((float) $latest->new_price < (float) $latest->previous_price ? 'down' : 'up');
    $price = $product->current_price !== null ? (float) $product->current_price : null;
@endphp

<x-layouts::app :title="$product->title">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}" class="text-sm text-emerald-600 hover:text-emerald-700">
            {{ __('← Atpakaļ') }}
        </a>

        @if (session('success'))
            <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                {{ session('success') }}
                <a href="{{ route('cart') }}" wire:navigate class="ms-1 font-medium underline">{{ __('Uz sarakstiem') }}</a>
            </div>
        @endif

        <div class="grid gap-6 md:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
            <div class="flex aspect-square items-center justify-center overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700">
                @if ($product->image_url)
                    <img src="{{ $product->image_url }}" alt="{{ $product->title }}" class="max-h-full max-w-full object-contain">
                @else
                    <flux:icon.photo class="size-16 text-neutral-300" />
                @endif
            </div>

            <div class="flex flex-col gap-5">
                <div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-neutral-100 px-2 py-0.5 font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">{{ $product->store }}</span>
                        @if ($product->category)
                            <a href="{{ route('dashboard', ['category' => $product->category]) }}" wire:navigate class="rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900 dark:text-emerald-300">{{ $product->category }}</a>
                        @endif
                    </div>
                    <flux:heading size="xl" class="mt-3">{{ $product->title }}</flux:heading>
                </div>

                <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                    <span class="text-3xl font-semibold">{{ $price !== null ? number_format($price, 2) . ' €' : '—' }}</span>
                    @if ($latest && $price !== null && (float) $latest->previous_price > $price)
                        <span class="text-lg text-neutral-500 line-through">{{ number_format((float) $latest->previous_price, 2) }} €</span>
                    @endif
                    @if ($product->unit_price !== null && $product->unit !== null)
                        <flux:text>{{ number_format((float) $product->unit_price, 2) }} {{ $product->unit }}</flux:text>
                    @endif
                </div>
                @if ($product->original_price)
                    <flux:text>{{ __('Parastā cena: :price', ['price' => $product->original_price]) }}</flux:text>
                @endif

                <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                    @if ($lists->isEmpty())
                        <flux:text>{{ __('Tev nav neviena atvērta saraksta, kurā pievienot produktu.') }}</flux:text>
                        <a href="{{ route('cart') }}" wire:navigate class="mt-2 inline-block text-sm font-medium text-emerald-600">{{ __('Izveidot sarakstu') }}</a>
                    @else
                        <form method="POST" action="{{ route('products.add-to-list', $product) }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="min-w-48 flex-1">
                                <label for="shopping_list_id" class="mb-1 block text-sm font-medium">{{ __('Saraksts') }}</label>
                                <select name="shopping_list_id" id="shopping_list_id" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                                    @foreach ($lists as $list)
                                        <option value="{{ $list->id }}" @selected($list->id === $activeListId)>{{ $list->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="w-24">
                                <label for="quantity" class="mb-1 block text-sm font-medium">{{ __('Daudzums') }}</label>
                                <input type="number" name="quantity" id="quantity" value="1" min="1" max="99" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                            </div>
                            <flux:button type="submit" variant="primary" icon="plus">{{ __('Pievienot sarakstam') }}</flux:button>
                        </form>
                        @error('quantity')
                            <flux:text class="mt-2 text-red-600">{{ $message }}</flux:text>
                        @enderror
                    @endif
                </div>
            </div>
        </div>

        <section>
            <flux:heading size="lg">{{ __('Līdzīgi produkti citos veikalos') }}</flux:heading>
            @if ($similarProducts->isEmpty())
                <flux:text class="mt-2">{{ __('Citos veikalos līdzīgs produkts netika atrasts.') }}</flux:text>
            @else
                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($similarProducts as $similar)
                        @php($difference = $price !== null ? (float) $similar->current_price - $price : null)
                        <a href="{{ route('products.show', $similar) }}" wire:navigate class="flex gap-3 rounded-xl border border-neutral-200 p-3 transition hover:border-emerald-500 dark:border-neutral-700">
                            <div class="flex size-16 shrink-0 items-center justify-center rounded-lg bg-white">
                                @if ($similar->image_url)
                                    <img src="{{ $similar->image_url }}" alt="" class="max-h-full max-w-full object-contain" loading="lazy">
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="line-clamp-2 text-sm font-medium">{{ $similar->title }}</p>
                                <p class="mt-1 text-xs text-neutral-500">{{ $similar->store }}</p>
                                <p class="mt-1 flex flex-wrap items-baseline gap-x-2 text-sm">
                                    <span class="font-semibold">{{ number_format((float) $similar->current_price, 2) }} €</span>
                                    @if ($similar->unit_price !== null && $similar->unit !== null)
                                        <span class="text-xs text-neutral-500">{{ number_format((float) $similar->unit_price, 2) }} {{ $similar->unit }}</span>
                                    @endif
                                    @if ($difference !== null && abs($difference) >= 0.01)
                                        <span class="text-xs font-medium {{ $difference < 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                            {{ $difference < 0
                                                ? __(':amount € lētāk', ['amount' => number_format(abs($difference), 2)])
                                                : __(':amount € dārgāk', ['amount' => number_format($difference, 2)]) }}
                                        </span>
                                    @endif
                                </p>
                            </div>
                        </a>
                    @endforeach
                </div>
                <flux:text class="mt-2 text-xs">{{ __('Produkti atlasīti pēc nosaukuma līdzības, tāpēc iepakojuma izmērs var atšķirties. Salīdzini cenu par kg vai litru.') }}</flux:text>
            @endif
        </section>

        <section>
            <flux:heading size="lg">{{ __('Cenu vēsture') }}</flux:heading>
            @if ($history->isEmpty())
                <flux:text class="mt-2">{{ __('Šim produktam cenu vēsture vēl nav reģistrēta.') }}</flux:text>
            @else
                <div class="mt-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                    <x-price-sparkline :points="$prices" :trend="$trend" class="h-24 w-full" />
                    <div class="mt-2 flex justify-between text-xs text-neutral-500">
                        <span>{{ $history->first()->created_at->format('d.m.Y') }}</span>
                        <span>{{ $latest->created_at->format('d.m.Y') }}</span>
                    </div>
                </div>

                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-start font-medium">{{ __('Datums') }}</th>
                                <th class="px-3 py-2 text-start font-medium">{{ __('Iepriekšējā cena') }}</th>
                                <th class="px-3 py-2 text-start font-medium">{{ __('Jaunā cena') }}</th>
                                <th class="px-3 py-2 text-start font-medium">{{ __('Izmaiņas') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($history->reverse() as $change)
                                @php($changeAmount = (float) $change->new_price - (float) $change->previous_price)
                                <tr>
                                    <td class="px-3 py-2">{{ $change->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="px-3 py-2">{{ number_format((float) $change->previous_price, 2) }} €</td>
                                    <td class="px-3 py-2 font-medium">{{ number_format((float) $change->new_price, 2) }} €</td>
                                    <td class="px-3 py-2 {{ $changeAmount < 0 ? 'text-emerald-600' : ($changeAmount > 0 ? 'text-red-600' : 'text-neutral-500') }}">
                                        {{ $changeAmount == 0 ? __('Bez izmaiņām') : ($changeAmount > 0 ? '+' : '') . number_format($changeAmount, 2) . ' €' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-layouts::app>
