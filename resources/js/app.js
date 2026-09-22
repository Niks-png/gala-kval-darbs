import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

let map;

function initStoreMap() {
    const container = document.getElementById('store-map');

    if (map) {
        map.remove();
        map = null;
    }

    if (!container) {
        return;
    }

    const stores = JSON.parse(container.dataset.stores || '[]');

    map = L.map(container).setView([56.9496, 24.1052], 11);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    const chainColors = {
        'maxima.lv': '#e30613',
        'etop.lv': '#00843d',
    };

    const markers = stores.map((store) => {
        const color = chainColors[store.chain] ?? '#3388ff';
        const icon = L.divIcon({
            className: '',
            html: `<span style="display:block;width:16px;height:16px;border-radius:50%;background:${color};border:2px solid #fff;box-shadow:0 0 2px rgba(0,0,0,0.6);"></span>`,
            iconSize: [16, 16],
            iconAnchor: [8, 8],
            popupAnchor: [0, -8],
        });

        return L.marker([store.lat, store.lng], { icon })
            .addTo(map)
            .bindPopup(`<strong>${store.name}</strong><br>${store.address}, ${store.city}`);
    });

    if (markers.length) {
        map.fitBounds(L.featureGroup(markers).getBounds().pad(0.2));
    }
}

document.addEventListener('livewire:navigated', initStoreMap);
