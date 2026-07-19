import './bootstrap';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

document.addEventListener('alpine:init', () => {
    window.codeRedFloating = (config = {}) => ({
        open: false,
        placement: 'bottom',
        panelStyle: '',
        cleanupCallbacks: [],

        init() {
            const reposition = () => {
                if (this.open) this.positionPanel();
            };
            const closeOnNavigation = () => this.closePanel();
            const eventIsInsideOverlay = (event) => (
                this.$refs.trigger?.contains(event.target) || this.$refs.panel?.contains(event.target)
            );
            const closeOnOutsidePointer = (event) => {
                if (this.open && !eventIsInsideOverlay(event)) this.closePanel();
            };
            const closeOnOutsideFocus = (event) => {
                if (this.open && !eventIsInsideOverlay(event)) this.closePanel();
            };

            window.addEventListener('resize', reposition);
            window.addEventListener('scroll', reposition, true);
            document.addEventListener('livewire:navigating', closeOnNavigation);
            document.addEventListener('pointerdown', closeOnOutsidePointer);
            document.addEventListener('focusin', closeOnOutsideFocus);

            this.cleanupCallbacks = [
                () => window.removeEventListener('resize', reposition),
                () => window.removeEventListener('scroll', reposition, true),
                () => document.removeEventListener('livewire:navigating', closeOnNavigation),
                () => document.removeEventListener('pointerdown', closeOnOutsidePointer),
                () => document.removeEventListener('focusin', closeOnOutsideFocus),
            ];
        },

        destroy() {
            this.cleanupCallbacks.forEach((cleanup) => cleanup());
            this.cleanupCallbacks = [];
        },

        openPanel() {
            this.open = true;
            this.$nextTick(() => {
                this.positionPanel();
                window.requestAnimationFrame(() => this.positionPanel());
            });
        },

        closePanel() {
            this.open = false;
        },

        togglePanel() {
            this.open ? this.closePanel() : this.openPanel();
        },

        positionPanel() {
            const trigger = this.$refs.trigger;
            const panel = this.$refs.panel;
            if (!trigger || !panel) return;

            const viewportMargin = Number(config.viewportMargin ?? 8);
            const gap = Number(config.gap ?? 8);
            const preferredMaxHeight = Number(config.maxHeight ?? 288);
            const triggerRect = trigger.getBoundingClientRect();
            const availableBelow = window.innerHeight - triggerRect.bottom - viewportMargin - gap;
            const availableAbove = triggerRect.top - viewportMargin - gap;
            const measuredHeight = Math.min(panel.scrollHeight || preferredMaxHeight, preferredMaxHeight);
            const openAbove = availableBelow < measuredHeight && availableAbove > availableBelow;
            const maxHeight = Math.max(96, Math.min(preferredMaxHeight, openAbove ? availableAbove : availableBelow));
            const requestedWidth = config.matchWidth === false
                ? Number(config.width ?? panel.offsetWidth ?? triggerRect.width)
                : triggerRect.width;
            const width = Math.min(requestedWidth, window.innerWidth - (viewportMargin * 2));
            const requestedLeft = config.align === 'end' ? triggerRect.right - width : triggerRect.left;
            const left = Math.min(
                Math.max(viewportMargin, requestedLeft),
                Math.max(viewportMargin, window.innerWidth - width - viewportMargin),
            );
            const top = openAbove
                ? Math.max(viewportMargin, triggerRect.top - gap - Math.min(measuredHeight, maxHeight))
                : Math.min(window.innerHeight - viewportMargin, triggerRect.bottom + gap);

            this.placement = openAbove ? 'top' : 'bottom';
            this.panelStyle = [
                'position: fixed',
                `top: ${Math.round(top)}px`,
                `left: ${Math.round(left)}px`,
                `width: ${Math.round(width)}px`,
                `max-height: ${Math.round(maxHeight)}px`,
            ].join('; ');
        },
    });
    window.Alpine.data('codeRedFloating', window.codeRedFloating);

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
