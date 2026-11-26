/*
| Cartes ACCES TRANS (Leaflet + OpenStreetMap, sans framework) : composant Alpine « liveMap » utilisé par <x-ui.map>.
|
| - Les données (JSON) sont lues dans l'attribut data-map ; quand Livewire rafraîchit la page (wire:poll), l'attribut
|   change et seuls les marqueurs sont déplacés, sans recréer la carte.
| - data-map : { center: [lat, lng], zoom, fit, markers: [{ id, lat, lng, label, tone }], zones: [{ lat, lng, radius, label }] }
| - En thème sombre, les tuiles sont assombries par filtre CSS (classe .ui-map-dark).
*/
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const root = document.documentElement;

const liveMap = () => ({
    map: null,
    markers: {},
    zones: null,

    init() {
        const data = this.read();
        const container = this.$el.querySelector('[data-map-canvas]');

        this.map = L.map(container, { zoomControl: true, attributionControl: true, scrollWheelZoom: false })
            .setView(data.center ?? [5.36, -4.01], data.zoom ?? 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; contributeurs OpenStreetMap',
        }).addTo(this.map);

        this.zones = L.layerGroup().addTo(this.map);
        this.render(data, true);
        this.applyTheme();

        new MutationObserver(() => this.render(this.read(), false)).observe(this.$el, { attributes: true, attributeFilter: ['data-map'] });
        new MutationObserver(() => this.applyTheme()).observe(root, { attributes: true, attributeFilter: ['data-theme'] });
    },

    read() {
        try {
            return JSON.parse(this.$el.dataset.map || '{}');
        } catch (error) {
            return {};
        }
    },

    icon(marker) {
        return L.divIcon({
            className: '',
            html: `<span class="ui-map-marker ui-map-marker--${marker.tone ?? 'neutral'}">${marker.label}</span>`,
            iconSize: null,
            iconAnchor: [0, 12],
        });
    },

    render(data, first) {
        const seen = new Set();

        for (const marker of data.markers ?? []) {
            seen.add(String(marker.id));
            const existing = this.markers[marker.id];

            if (existing) {
                existing.setLatLng([marker.lat, marker.lng]).setIcon(this.icon(marker));
            } else {
                this.markers[marker.id] = L.marker([marker.lat, marker.lng], { icon: this.icon(marker), title: marker.label }).addTo(this.map);
            }
        }

        for (const id of Object.keys(this.markers)) {
            if (!seen.has(id)) {
                this.markers[id].remove();
                delete this.markers[id];
            }
        }

        this.zones.clearLayers();

        for (const zone of data.zones ?? []) {
            L.circle([zone.lat, zone.lng], { radius: zone.radius, color: '#c2410c', weight: 1.5, fillOpacity: 0.12 })
                .bindTooltip(zone.label)
                .addTo(this.zones);
        }

        if (first && data.fit) {
            const points = [...(data.markers ?? []), ...(data.zones ?? [])].map((point) => [point.lat, point.lng]);

            if (points.length > 1) {
                this.map.fitBounds(points, { padding: [40, 40], maxZoom: 14 });
            }
        }
    },

    applyTheme() {
        this.$el.classList.toggle('ui-map-dark', root.dataset.theme === 'dark');
    },
});

/*
| Sélecteur de lieu (<x-ui.location-picker>) : clic sur la carte, marqueur déplaçable ou recherche par nom.
| Les coordonnées sont écrites dans les propriétés Livewire indiquées (config.lat, config.lng) ; le nom du lieu
| trouvé remplit config.place s'il est encore vide. La recherche passe par la route serveur config.searchUrl.
*/
const ABIDJAN = [5.345, -4.02];

const locationPicker = (config) => ({
    map: null,
    marker: null,
    query: '',
    results: [],
    searching: false,
    error: '',

    init() {
        const canvas = this.$el.querySelector('[data-map-canvas]');

        this.map = L.map(canvas, { zoomControl: true, attributionControl: true, scrollWheelZoom: true }).setView(ABIDJAN, 11);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; contributeurs OpenStreetMap',
        }).addTo(this.map);

        this.map.on('click', (event) => this.choose(event.latlng.lat, event.latlng.lng));

        // La carte est souvent créée dans une feuille encore fermée : recalcul de la taille à l'ouverture.
        new ResizeObserver(() => {
            this.map.invalidateSize();
            this.sync(true);
        }).observe(canvas);

        this.$watch(`$wire.${config.lat}`, () => this.sync(true));
        this.$watch(`$wire.${config.lng}`, () => this.sync(true));
        this.sync(true);

        this.applyTheme();
        new MutationObserver(() => this.applyTheme()).observe(root, { attributes: true, attributeFilter: ['data-theme'] });
    },

    coordinates() {
        const lat = parseFloat(this.$wire[config.lat]);
        const lng = parseFloat(this.$wire[config.lng]);

        return Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180 ? [lat, lng] : null;
    },

    sync(recenter) {
        const position = this.coordinates();

        if (!position) {
            this.marker?.remove();
            this.marker = null;

            return;
        }

        if (this.marker) {
            this.marker.setLatLng(position);
        } else {
            this.marker = L.marker(position, {
                draggable: true,
                title: 'Lieu d\'embarquement',
                icon: L.divIcon({ className: '', html: '<span class="ui-map-pin"></span>', iconSize: [26, 26], iconAnchor: [13, 31] }),
            }).addTo(this.map);
            this.marker.on('dragend', () => {
                const { lat, lng } = this.marker.getLatLng();
                this.choose(lat, lng);
            });
        }

        if (recenter && !this.map.getBounds().pad(-0.1).contains(position)) {
            this.map.setView(position, Math.max(this.map.getZoom(), 15));
        }
    },

    choose(lat, lng, name = null) {
        this.$wire[config.lat] = lat.toFixed(6);
        this.$wire[config.lng] = lng.toFixed(6);

        if (name && config.place && !String(this.$wire[config.place] ?? '').trim()) {
            this.$wire[config.place] = name;
        }

        this.sync(false);
    },

    async search() {
        const query = this.query.trim();
        this.error = '';

        if (query.length < 3) {
            this.error = 'Saisissez au moins 3 caractères.';

            return;
        }

        this.searching = true;

        try {
            const response = await fetch(`${config.searchUrl}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const body = await response.json().catch(() => ({}));

            if (!response.ok) {
                this.results = [];
                this.error = response.status === 429 ? 'Trop de recherches : patientez une minute.' : (body.message ?? 'Recherche impossible.');

                return;
            }

            this.results = body.data ?? [];
            this.error = this.results.length ? '' : 'Aucun lieu trouvé à Abidjan. Essayez un autre nom ou cliquez sur la carte.';
        } catch (error) {
            this.error = 'Recherche impossible. Cliquez directement sur la carte.';
        } finally {
            this.searching = false;
        }
    },

    pick(result) {
        this.results = [];
        this.choose(result.lat, result.lng, result.name);
        this.map.setView([result.lat, result.lng], 16);
    },

    applyTheme() {
        this.$el.classList.toggle('ui-map-dark', root.dataset.theme === 'dark');
    },
});

const register = () => {
    window.Alpine.data('liveMap', liveMap);
    window.Alpine.data('locationPicker', locationPicker);
};

if (window.Alpine) {
    register();
} else {
    document.addEventListener('alpine:init', register);
}
