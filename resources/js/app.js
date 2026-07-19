import './bootstrap';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('codeRedMap', (config) => ({
        map: null,
        resizeObserver: null,

        init() {
            this.$nextTick(() => this.mount());
        },

        mount() {
            if (this.map || !this.$refs.map) return;

            const latitude = Number(config.latitude);
            const longitude = Number(config.longitude);
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;

            this.map = L.map(this.$refs.map, {
                scrollWheelZoom: false,
            }).setView([latitude, longitude], 15);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(this.map);

            const icon = L.icon({
                iconUrl: config.markerUrl,
                iconSize: [44, 44],
                iconAnchor: [22, 44],
                popupAnchor: [0, -42],
                className: 'codered-map-marker',
            });
            const marker = L.marker([latitude, longitude], { icon, title: config.name }).addTo(this.map);
            const popup = document.createElement('div');
            popup.className = 'space-y-1 text-sm';
            const title = document.createElement('strong');
            title.textContent = config.name;
            popup.appendChild(title);
            if (config.location) {
                const location = document.createElement('p');
                location.textContent = config.location;
                popup.appendChild(location);
            }
            const coordinates = document.createElement('p');
            coordinates.textContent = `${latitude.toFixed(6)}, ${longitude.toFixed(6)}`;
            popup.appendChild(coordinates);
            const link = document.createElement('a');
            link.href = config.googleUrl;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = 'Abrir en Google Maps';
            popup.appendChild(link);
            marker.bindPopup(popup);

            window.setTimeout(() => this.map?.invalidateSize(), 0);
            this.resizeObserver = new ResizeObserver(() => this.map?.invalidateSize());
            this.resizeObserver.observe(this.$refs.map);
        },

        destroy() {
            this.resizeObserver?.disconnect();
            this.resizeObserver = null;
            this.map?.remove();
            this.map = null;
        },
    }));
});

document.addEventListener('livewire:navigating', () => {
    window.dispatchEvent(new CustomEvent('codered-map:destroy'));
});
