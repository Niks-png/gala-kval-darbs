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

// Live password requirement checklist (resources/views/components/password-requirements.blade.php).
// Patterns mirror Illuminate\Validation\Rules\Password.
const passwordChecks = {
    lower: (value) => /\p{Ll}/u.test(value),
    upper: (value) => /\p{Lu}/u.test(value),
    letters: (value) => /\p{L}/u.test(value),
    numbers: (value) => /\p{N}/u.test(value),
    symbols: (value) => /\p{Z}|\p{S}|\p{P}/u.test(value),
};

function updatePasswordRequirements(form) {
    const [password, confirmation] = form.querySelectorAll('input[autocomplete="new-password"]');

    if (!password) {
        return;
    }

    form.querySelectorAll('[data-password-requirements]').forEach((list) => {
        const min = Number(list.dataset.min);

        list.querySelectorAll('[data-rule]').forEach((item) => {
            const rule = item.dataset.rule;
            let met;

            if (rule === 'min') {
                met = [...password.value].length >= min;
            } else if (rule === 'match') {
                met = password.value !== '' && password.value === confirmation?.value;
            } else {
                met = passwordChecks[rule]?.(password.value) ?? false;
            }

            item.toggleAttribute('data-met', met);
        });
    });
}

function updateAllPasswordRequirements() {
    document.querySelectorAll('[data-password-requirements]').forEach((list) => {
        const form = list.closest('form');

        if (form) {
            updatePasswordRequirements(form);
        }
    });
}

document.addEventListener('input', (event) => {
    if (event.target.matches?.('input[autocomplete="new-password"]') && event.target.form) {
        updatePasswordRequirements(event.target.form);
    }
});

document.addEventListener('DOMContentLoaded', updateAllPasswordRequirements);
document.addEventListener('livewire:navigated', updateAllPasswordRequirements);

// Follow bell on product cards (resources/views/components/product-card.blade.php).
// Without JS the form still works as a normal POST/DELETE with a redirect back.
document.addEventListener('submit', async (event) => {
    const form = event.target.closest?.('.watch-form');

    if (!form) {
        return;
    }

    event.preventDefault();

    const button = form.querySelector('button');
    button.disabled = true;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: new FormData(form),
        });

        if (!response.ok) {
            return;
        }

        const { watching } = await response.json();
        const label = watching ? form.dataset.labelUnwatch : form.dataset.labelWatch;

        form.toggleAttribute('data-watching', watching);
        form.querySelector('input[name="_method"]').value = watching ? 'DELETE' : 'POST';
        button.setAttribute('aria-pressed', watching ? 'true' : 'false');
        button.setAttribute('aria-label', label);
        button.title = label;
    } finally {
        button.disabled = false;
    }
});

// Livewire forms reset their inputs after saving without firing "input".
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ succeed }) => {
        succeed(() => queueMicrotask(updateAllPasswordRequirements));
    });
});
