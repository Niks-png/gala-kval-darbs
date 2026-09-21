<x-layouts::app :title="__('Karte')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div>
            <flux:heading size="xl">{{ __('Karte') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Maxima veikalu atrašanās vietas Rīgā.') }}</flux:text>
        </div>

        @if ($stores->isEmpty())
            <flux:text>{{ __('Veikalu dati vēl nav pievienoti.') }}</flux:text>
        @else
            <div
                id="store-map"
                data-stores="{{ $stores->map(fn ($store) => [
                    'name' => $store->name,
                    'address' => $store->address,
                    'city' => $store->city,
                    'lat' => (float) $store->lat,
                    'lng' => (float) $store->lng,
                ])->toJson() }}"
                class="h-[32rem] w-full overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700"
            ></div>
        @endif
    </div>
</x-layouts::app>
