/*
| Cartes EVENT TRANS (Leaflet + OpenStreetMap, sans framework) : composant Alpine « liveMap » utilisé par <x-ui.map>.
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

const register = () => window.Alpine.data('liveMap', liveMap);

if (window.Alpine) {
    register();
} else {
    document.addEventListener('alpine:init', register);
}
