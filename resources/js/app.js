import "./bootstrap";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import { codeRedTokenCopy } from "./api-token-copy";

document.addEventListener("alpine:init", () => {
  window.codeRedFloating = (config = {}) => ({
    open: false,
    placement: "bottom",
    panelStyle: "",
    cleanupCallbacks: [],

    init() {
      const reposition = () => {
        if (this.open) this.positionPanel();
      };
      const closeOnNavigation = () => this.closePanel();
      const eventIsInsideOverlay = (event) =>
        this.$refs.trigger?.contains(event.target) ||
        this.$refs.panel?.contains(event.target);
      const closeOnOutsidePointer = (event) => {
        if (this.open && !eventIsInsideOverlay(event)) this.closePanel();
      };
      const closeOnOutsideFocus = (event) => {
        if (this.open && !eventIsInsideOverlay(event)) this.closePanel();
      };

      window.addEventListener("resize", reposition);
      window.addEventListener("scroll", reposition, true);
      document.addEventListener("livewire:navigating", closeOnNavigation);
      document.addEventListener("pointerdown", closeOnOutsidePointer);
      document.addEventListener("focusin", closeOnOutsideFocus);

      this.cleanupCallbacks = [
        () => window.removeEventListener("resize", reposition),
        () => window.removeEventListener("scroll", reposition, true),
        () =>
          document.removeEventListener(
            "livewire:navigating",
            closeOnNavigation,
          ),
        () =>
          document.removeEventListener("pointerdown", closeOnOutsidePointer),
        () => document.removeEventListener("focusin", closeOnOutsideFocus),
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

    focusTrigger() {
      this.$refs.trigger?.focus({ preventScroll: true });
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
      const availableBelow =
        window.innerHeight - triggerRect.bottom - viewportMargin - gap;
      const availableAbove = triggerRect.top - viewportMargin - gap;
      const measuredHeight = Math.min(
        panel.scrollHeight || preferredMaxHeight,
        preferredMaxHeight,
      );
      const openAbove =
        availableBelow < measuredHeight && availableAbove > availableBelow;
      const maxHeight = Math.max(
        96,
        Math.min(
          preferredMaxHeight,
          openAbove ? availableAbove : availableBelow,
        ),
      );
      const requestedWidth =
        config.matchWidth === false
          ? Number(config.width ?? panel.offsetWidth ?? triggerRect.width)
          : triggerRect.width;
      const width = Math.min(
        requestedWidth,
        window.innerWidth - viewportMargin * 2,
      );
      const requestedLeft =
        config.align === "end" ? triggerRect.right - width : triggerRect.left;
      const left = Math.min(
        Math.max(viewportMargin, requestedLeft),
        Math.max(viewportMargin, window.innerWidth - width - viewportMargin),
      );
      const top = openAbove
        ? Math.max(
            viewportMargin,
            triggerRect.top - gap - Math.min(measuredHeight, maxHeight),
          )
        : Math.min(
            window.innerHeight - viewportMargin,
            triggerRect.bottom + gap,
          );

      this.placement = openAbove ? "top" : "bottom";
      this.panelStyle = [
        "position: fixed",
        `top: ${Math.round(top)}px`,
        `left: ${Math.round(left)}px`,
        `width: ${Math.round(width)}px`,
        `max-height: ${Math.round(maxHeight)}px`,
      ].join("; ");
    },
  });
  window.Alpine.data("codeRedFloating", window.codeRedFloating);
  window.Alpine.data("codeRedTokenCopy", codeRedTokenCopy);

  window.Alpine.data("codeRedAgencyMap", (config) => ({
    map: null,
    markerLayer: null,
    resizeObserver: null,
    markers: [],

    init() {
      this.markers = (config.markers ?? []).filter((marker) => {
        const latitude = Number(marker.latitude);
        const longitude = Number(marker.longitude);

        return (
          Number.isFinite(latitude) &&
          latitude >= -90 &&
          latitude <= 90 &&
          Number.isFinite(longitude) &&
          longitude >= -180 &&
          longitude <= 180
        );
      });
      this.$nextTick(() => this.mount());
    },

    mount() {
      if (this.map || !this.$refs.map || this.markers.length === 0) return;

      this.map = L.map(this.$refs.map, {
        scrollWheelZoom: false,
        zoomControl: true,
      });
      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution:
          '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      }).addTo(this.map);
      this.markerLayer = L.layerGroup().addTo(this.map);
      this.map.on("zoomend moveend", () => this.renderClusters());

      const bounds = L.latLngBounds(
        this.markers.map((marker) => [
          Number(marker.latitude),
          Number(marker.longitude),
        ]),
      );
      this.map.fitBounds(bounds, { padding: [36, 36], maxZoom: 15 });
      this.renderClusters();

      window.setTimeout(() => this.map?.invalidateSize(), 0);
      this.resizeObserver = new ResizeObserver(() =>
        this.map?.invalidateSize(),
      );
      this.resizeObserver.observe(this.$refs.map);
    },

    renderClusters() {
      if (!this.map || !this.markerLayer) return;

      this.markerLayer.clearLayers();
      const cellSize = this.map.getZoom() >= 13 ? 54 : 72;
      const groups = new Map();
      this.markers.forEach((marker) => {
        const point = this.map.project(
          [Number(marker.latitude), Number(marker.longitude)],
          this.map.getZoom(),
        );
        const key =
          Math.floor(point.x / cellSize) + ":" + Math.floor(point.y / cellSize);
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(marker);
      });

      groups.forEach((group) => {
        if (group.length === 1) {
          const agency = group[0];
          L.marker([Number(agency.latitude), Number(agency.longitude)], {
            icon: L.icon({
              iconUrl: config.markerUrl,
              iconSize: [38, 38],
              iconAnchor: [19, 38],
              popupAnchor: [0, -36],
              className: "codered-map-marker",
            }),
            title: agency.name,
          })
            .bindPopup(this.popupContent(agency))
            .addTo(this.markerLayer);

          return;
        }

        const bounds = L.latLngBounds(
          group.map((agency) => [
            Number(agency.latitude),
            Number(agency.longitude),
          ]),
        );
        const cluster = L.marker(bounds.getCenter(), {
          icon: L.divIcon({
            className: "codered-cluster-icon",
            html: '<span aria-hidden="true">' + group.length + "</span>",
            iconSize: [44, 44],
            iconAnchor: [22, 22],
          }),
          title: group.length + " agencias agrupadas",
          keyboard: true,
        });
        cluster.on("click", () =>
          this.map?.fitBounds(bounds, { padding: [48, 48], maxZoom: 17 }),
        );
        cluster.addTo(this.markerLayer);
      });
    },

    popupContent(agency) {
      const content = document.createElement("div");
      content.className = "codered-map-popup";
      const code = document.createElement("span");
      code.className = "codered-map-popup__code";
      code.textContent = agency.code;
      const title = document.createElement("strong");
      title.textContent = agency.name;
      const location = document.createElement("p");
      location.textContent = agency.location;
      const address = document.createElement("p");
      address.textContent = agency.address || "Dirección no registrada";
      const status = document.createElement("span");
      status.className = "codered-map-popup__status";
      status.textContent = agency.status_label;
      const actions = document.createElement("div");
      actions.className = "codered-map-popup__actions";
      const detail = document.createElement("a");
      detail.href = agency.detail_url;
      detail.textContent = "Ver detalle";
      const maps = document.createElement("a");
      maps.href = agency.maps_url;
      maps.target = "_blank";
      maps.rel = "noopener noreferrer";
      maps.textContent = "Abrir Google Maps";
      actions.append(detail, maps);
      content.append(code, title, location, address, status, actions);

      return content;
    },

    focusAgency(id) {
      const agency = this.markers.find(
        (marker) => Number(marker.id) === Number(id),
      );
      if (!agency || !this.map) return;

      const coordinates = [Number(agency.latitude), Number(agency.longitude)];
      this.map.setView(coordinates, Math.max(this.map.getZoom(), 16), {
        animate: true,
      });
      window.setTimeout(() => {
        this.renderClusters();
        L.popup()
          .setLatLng(coordinates)
          .setContent(this.popupContent(agency))
          .openOn(this.map);
      }, 280);
    },

    destroy() {
      this.resizeObserver?.disconnect();
      this.resizeObserver = null;
      this.map?.off();
      this.map?.remove();
      this.map = null;
      this.markerLayer = null;
    },
  }));

  window.Alpine.data("codeRedSwaggerDocs", (config) => ({
    ui: null,
    ready: false,

    init() {
      this.$nextTick(() => this.mount());
    },

    async mount() {
      if (this.ui || !this.$refs.swagger) return;

      const [{ default: SwaggerUIBundle }, { default: SwaggerUIStandalonePreset }] = await Promise.all([
        import("swagger-ui-dist/swagger-ui-es-bundle.js"),
        import("swagger-ui-dist/swagger-ui-standalone-preset.js"),
        import("swagger-ui-dist/swagger-ui.css"),
      ]);

      this.ui = SwaggerUIBundle({
        url: config.specUrl,
        domNode: this.$refs.swagger,
        deepLinking: true,
        displayRequestDuration: true,
        docExpansion: "list",
        filter: true,
        persistAuthorization: false,
        requestSnippetsEnabled: true,
        presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
        requestInterceptor: (request) => {
          const authorization = request.headers?.Authorization;
          if (typeof authorization === "string") {
            request.headers.Authorization = authorization.replace(
              /^Bearer\s+Bearer\s+/i,
              "Bearer ",
            );
          }

          return request;
        },
        supportedSubmitMethods: ["get"],
        tryItOutEnabled: true,
        onComplete: () => {
          this.ready = true;
        },
      });
    },

    destroy() {
      this.ui = null;
      this.ready = false;
      if (this.$refs.swagger) this.$refs.swagger.replaceChildren();
    },
  }));

  window.Alpine.data("codeRedMap", (config) => ({
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

      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution:
          '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      }).addTo(this.map);

      const icon = L.icon({
        iconUrl: config.markerUrl,
        iconSize: [44, 44],
        iconAnchor: [22, 44],
        popupAnchor: [0, -42],
        className: "codered-map-marker",
      });
      const marker = L.marker([latitude, longitude], {
        icon,
        title: config.name,
      }).addTo(this.map);
      const popup = document.createElement("div");
      popup.className = "space-y-1 text-sm";
      const title = document.createElement("strong");
      title.textContent = config.name;
      popup.appendChild(title);
      if (config.location) {
        const location = document.createElement("p");
        location.textContent = config.location;
        popup.appendChild(location);
      }
      const coordinates = document.createElement("p");
      coordinates.textContent = `${latitude.toFixed(6)}, ${longitude.toFixed(6)}`;
      popup.appendChild(coordinates);
      const link = document.createElement("a");
      link.href = config.googleUrl;
      link.target = "_blank";
      link.rel = "noopener noreferrer";
      link.textContent = "Abrir en Google Maps";
      popup.appendChild(link);
      marker.bindPopup(popup);

      window.setTimeout(() => this.map?.invalidateSize(), 0);
      this.resizeObserver = new ResizeObserver(() =>
        this.map?.invalidateSize(),
      );
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

document.addEventListener("livewire:navigating", () => {
  window.dispatchEvent(new CustomEvent("codered-map:destroy"));
  window.dispatchEvent(new CustomEvent("codered-agency-map:destroy"));
});
