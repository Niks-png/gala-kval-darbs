<x-layouts::app :title="__('Karte')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div>
            <flux:heading size="xl">{{ __('Veikalu karte') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Top un Maxima veikalu atrašanās vietas Latvijā.') }}</flux:text>
        </div>

        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div id="store-map" class="h-[32rem] w-full" aria-label="{{ __('Store map') }}">
                <div class="p-4 text-sm text-zinc-500">{{ __('Loading store locations...') }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <flux:heading size="sm">{{ __('Veikalu slāņi') }}</flux:heading>
            <flux:text id="store-count" class="mt-1">{{ __('Ielādē veikalu adreses...') }}</flux:text>
        </div>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>
        const map = L.map('store-map').setView([56.9496, 24.1052], 8);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        const maximaLayer = L.layerGroup().addTo(map);
        const topLayer = L.layerGroup().addTo(map);
        L.control.layers(null, {
            'Maxima': maximaLayer,
            'Top': topLayer,
        }, { collapsed: false }).addTo(map);

        fetch('{{ asset('data/store-locations.json') }}')
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Store locations have not been synchronized yet.');
                }

                return response.json();
            })
            .then((data) => {
                const bounds = [];
                const stores = (data.locations ?? [])
                    .filter((store) => store.latitude && store.longitude)
                    .filter((store, index, all) => {
                        const latitude = store.latitude;
                        const longitude = store.longitude;
                        return all.findIndex((candidate) => {
                            const candidateLatitude = candidate.latitude;
                            const candidateLongitude = candidate.longitude;
                            return candidateLatitude === latitude && candidateLongitude === longitude;
                        }) === index;
                    });

                stores.forEach((store) => {
                    const latitude = store.latitude;
                    const longitude = store.longitude;
                    const address = store.address || 'Adrese nav norādīta';

                    const isTop = store.network === 'Top';
                    const layer = isTop ? topLayer : maximaLayer;
                    const storeName = store.network;
                    bounds.push([latitude, longitude]);
                    L.marker([latitude, longitude])
                        .addTo(layer)
                        .bindPopup(`<strong>${storeName}</strong><br>${address || `${storeName} veikals`}`);
                });

                const maximaCount = maximaLayer.getLayers().length;
                const topCount = topLayer.getLayers().length;
                if (stores.length === 0) {
                    addFallbackMarkers();
                    return;
                }

                document.getElementById('store-count').textContent = `Maxima: ${maximaCount}; Top: ${topCount}. Izmanto slāņu izvēlni kartes augšējā labajā stūrī.`;
                if (bounds.length > 0) {
                    map.fitBounds(bounds, { padding: [20, 20] });
                }
            })
            .catch(() => {
                addFallbackMarkers();
            });

        function addFallbackMarkers() {
            [
                ['Maxima', 56.9496, 24.1052, 'Rīga, pilsētas centrs'],
                ['Maxima', 56.9677, 24.1128, 'Rīga, Purvciems'],
                ['Top', 56.9492, 24.1147, 'Rīga, centrs'],
                ['Top', 56.9568, 24.1601, 'Rīga, Ķengarags'],
            ].forEach(([name, latitude, longitude, address]) => {
                L.marker([latitude, longitude])
                    .addTo(name === 'Top' ? topLayer : maximaLayer)
                    .bindPopup(`<strong>${name}</strong><br>${address}<br><small>Aptuvena atrašanās vieta</small>`);
            });

            document.getElementById('store-count').textContent = 'Maxima: 2; Top: 2. Parādītas aptuvenas atrašanās vietas.';
        }
    </script>
</x-layouts::app>
