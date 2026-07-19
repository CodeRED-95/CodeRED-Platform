import { load as parseYaml } from "js-yaml";

const visual = {
  health: { category: "Sistema", ability: "Público", title: "Estado del servicio" },
  tokenOwner: { category: "Autenticación", ability: "profile:read", title: "Validar token" },
  listAgencies: { category: "Agencias", ability: "agencies:read", title: "Listar agencias" },
  getAgencyByCode: { category: "Agencias", ability: "agencies:read", title: "Detalle de agencia" },
  searchAgencies: { category: "Agencias", ability: "agencies:read", title: "Buscar agencias" },
  agencyChanges: { category: "Agencias", ability: "agencies:read", title: "Cambios incrementales" },
  agencySnapshot: { category: "Agencias", ability: "agencies:read", title: "Snapshot completo" },
  agencyVersion: { category: "Agencias", ability: "agencies:read", title: "Versión heredada" },
  catalogMetadata: { category: "Catálogo", ability: "agencies:read", title: "Metadata del catálogo" },
};

const categories = {
  Sistema: "Disponibilidad y versión pública del servicio.",
  Autenticación: "Identidad, vigencia y abilities del token Sanctum.",
  Agencias: "Consulta y sincronización del catálogo oficial.",
  Catálogo: "Revisión, esquema y cursores del catálogo.",
};

export function buildRequestUrl(baseUrl, path, parameters = [], values = {}) {
  let resolvedPath = path;
  const query = new URLSearchParams();

  parameters.forEach((parameter) => {
    const value = values[parameter.name];
    if (value === "" || value === null || value === undefined || value === false) return;
    if (parameter.in === "path") {
      resolvedPath = resolvedPath.replace(`{${parameter.name}}`, encodeURIComponent(String(value).trim()));
      return;
    }
    if (parameter.in === "query") query.set(parameter.name, String(value).trim());
  });

  const url = new URL(baseUrl.replace(/\/$/, "") + "/" + resolvedPath.replace(/^\//, ""), window.location.origin);
  query.forEach((value, key) => url.searchParams.set(key, value));
  return url;
}

export function generateCurl(url, isProtected) {
  const authorization = isProtected ? ' -H "Authorization: Bearer TU_TOKEN"' : "";
  return `curl -X GET "${url}" -H "Accept: application/json"${authorization}`;
}

export function generateFetch(url, isProtected) {
  const authorization = isProtected ? "\n      Authorization: 'Bearer TU_TOKEN'," : "";
  return `const response = await fetch('${url}', {\n  headers: {\n    Accept: 'application/json',${authorization}\n  },\n});\n\nconst data = await response.json();`;
}

export function apiErrorMessage(status) {
  return ({
    401: "Token inválido, expirado o revocado.",
    403: "El token no tiene la ability requerida.",
    404: "El recurso solicitado no existe.",
    409: "El cursor ya no es válido; realiza una sincronización completa.",
    410: "El cursor ya no es válido; realiza una sincronización completa.",
    422: "Revisa los parámetros enviados.",
    429: "Se alcanzó el límite de solicitudes.",
  })[status] ?? (status >= 500 ? "La API encontró un error inesperado." : null);
}

function defaultValue(parameter) {
  if (parameter.schema?.type === "boolean") return false;
  return parameter.schema?.default ?? "";
}

function parameterType(parameter) {
  if (parameter.schema?.enum) return "select";
  if (parameter.schema?.type === "boolean") return "boolean";
  if (parameter.schema?.type === "integer" || parameter.schema?.type === "number") return "number";
  return "text";
}

export function codeRedApiDocs(config) {
  return {
    spec: null,
    endpoints: [],
    activeTab: "guide",
    categoryFilter: "Todos",
    search: "",
    token: "",
    showToken: false,
    authStatus: "idle",
    authMessage: "No autorizado",
    authDetails: null,
    swagger: null,
    swaggerReady: false,

    async init() {
      try {
        const response = await fetch(config.specUrl, { headers: { Accept: "application/yaml" } });
        if (!response.ok) throw new Error("No se pudo cargar OpenAPI");
        this.spec = parseYaml(await response.text());
        this.endpoints = this.extractEndpoints();
      } catch (_error) {
        this.authMessage = "No fue posible cargar el contrato OpenAPI.";
        this.$dispatch("toast", { tone: "danger", message: this.authMessage });
      }
    },

    extractEndpoints() {
      const result = [];
      Object.entries(this.spec?.paths ?? {}).forEach(([path, methods]) => {
        Object.entries(methods).forEach(([method, operation]) => {
          if (!["get", "post", "put", "patch", "delete"].includes(method)) return;
          const meta = visual[operation.operationId] ?? {};
          const parameters = (operation.parameters ?? []).map((parameter) => ({
            ...parameter,
            inputType: parameterType(parameter),
          }));
          result.push({
            id: operation.operationId ?? method + path,
            method: method.toUpperCase(),
            path,
            fullPath: "/api/v1" + path,
            title: meta.title ?? operation.summary ?? path,
            summary: operation.summary ?? "Endpoint CodeRED",
            description: operation.description ?? operation.summary ?? "",
            category: meta.category ?? operation.tags?.[0] ?? "Otros",
            ability: meta.ability ?? (operation.security ? "Bearer" : "Público"),
            protected: Boolean(operation.security?.length),
            parameters,
            values: Object.fromEntries(parameters.map((parameter) => [parameter.name, defaultValue(parameter)])),
            expanded: false,
            loading: false,
            response: null,
            responseTab: "body",
          });
        });
      });
      return result;
    },

    get filteredEndpoints() {
      const term = this.search.trim().toLocaleLowerCase("es");
      return this.endpoints.filter((endpoint) => {
        const categoryMatches = this.categoryFilter === "Todos" || endpoint.category === this.categoryFilter;
        const haystack = [endpoint.path, endpoint.title, endpoint.description, endpoint.category, endpoint.ability].join(" ").toLocaleLowerCase("es");
        return categoryMatches && (!term || haystack.includes(term));
      });
    },

    categoryEndpoints(category) {
      return this.filteredEndpoints.filter((endpoint) => endpoint.category === category);
    },

    categoryDescription(category) {
      return categories[category] ?? "Endpoints de CodeRED Platform.";
    },

    switchTab(tab) {
      this.activeTab = tab;
      if (tab === "openapi") this.$nextTick(() => this.mountSwagger());
    },

    async mountSwagger() {
      if (this.swagger || !this.$refs.swagger) return;
      const [{ default: SwaggerUIBundle }, { default: SwaggerUIStandalonePreset }] = await Promise.all([
        import("swagger-ui-dist/swagger-ui-es-bundle.js"),
        import("swagger-ui-dist/swagger-ui-standalone-preset.js"),
        import("swagger-ui-dist/swagger-ui.css"),
      ]);
      this.swagger = SwaggerUIBundle({
        url: config.specUrl,
        domNode: this.$refs.swagger,
        deepLinking: true,
        displayRequestDuration: true,
        docExpansion: "list",
        filter: true,
        persistAuthorization: false,
        requestSnippetsEnabled: true,
        presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
        supportedSubmitMethods: ["get"],
        tryItOutEnabled: true,
        requestInterceptor: (request) => {
          const authorization = request.headers?.Authorization;
          if (typeof authorization === "string") request.headers.Authorization = authorization.replace(/^Bearer\s+Bearer\s+/i, "Bearer ");
          return request;
        },
        onComplete: () => { this.swaggerReady = true; },
      });
    },

    async authorize() {
      this.token = this.token.trim();
      if (!this.token) {
        this.authStatus = "invalid";
        this.authMessage = "Ingresa un token para autorizar.";
        return;
      }
      this.authStatus = "loading";
      try {
        const response = await fetch(new URL("/api/v1/me", window.location.origin), {
          headers: { Accept: "application/json", Authorization: `Bearer ${this.token}` },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
          this.authStatus = response.status === 403 ? "forbidden" : "invalid";
          this.authMessage = apiErrorMessage(response.status) ?? "No fue posible validar el token.";
          this.authDetails = null;
          return;
        }
        this.authStatus = "valid";
        this.authMessage = "Token válido";
        this.authDetails = data;
      } catch (_error) {
        this.authStatus = "invalid";
        this.authMessage = "No fue posible conectar con la API.";
      }
    },

    clearAuthorization() {
      this.token = "";
      this.authStatus = "idle";
      this.authMessage = "No autorizado";
      this.authDetails = null;
    },

    async execute(endpoint) {
      const missing = endpoint.parameters.filter((parameter) => parameter.required && String(endpoint.values[parameter.name] ?? "").trim() === "");
      if (missing.length) {
        endpoint.expanded = true;
        endpoint.response = { status: 422, error: "Completa los parámetros obligatorios: " + missing.map((parameter) => parameter.name).join(", ") + ".", bodyText: "", headersText: "{}" };
        return;
      }
      if (endpoint.protected && !this.token.trim()) {
        endpoint.expanded = true;
        endpoint.response = { error: "Autoriza un token antes de probar este endpoint.", status: 401 };
        return;
      }
      endpoint.loading = true;
      endpoint.expanded = true;
      const started = performance.now();
      try {
        const url = buildRequestUrl(config.baseUrl, endpoint.path, endpoint.parameters, endpoint.values);
        if (url.origin !== window.location.origin || endpoint.method !== "GET") throw new Error("Endpoint no permitido");
        const headers = { Accept: "application/json" };
        if (endpoint.protected) headers.Authorization = `Bearer ${this.token.trim()}`;
        const response = await fetch(url, { method: "GET", headers });
        const text = await response.text();
        let body = text;
        try { body = text ? JSON.parse(text) : null; } catch (_error) { /* conserva texto seguro */ }
        const safeHeaders = {};
        ["content-type", "etag", "last-modified", "x-ratelimit-limit", "x-ratelimit-remaining", "retry-after"].forEach((name) => {
          const value = response.headers.get(name);
          if (value !== null) safeHeaders[name] = value;
        });
        endpoint.response = {
          status: response.status,
          ok: response.ok,
          statusText: response.statusText,
          duration: Math.round(performance.now() - started),
          size: new Blob([text]).size,
          body,
          bodyText: typeof body === "string" ? body : JSON.stringify(body, null, 2),
          headers: safeHeaders,
          headersText: JSON.stringify(safeHeaders, null, 2),
          curl: generateCurl(url.toString(), endpoint.protected),
          fetch: generateFetch(url.toString(), endpoint.protected),
          error: response.ok ? null : apiErrorMessage(response.status),
        };
      } catch (_error) {
        endpoint.response = { status: 0, error: "No fue posible conectar con la API.", bodyText: "", headersText: "{}" };
      } finally {
        endpoint.loading = false;
      }
    },

    useNextCursor(endpoint) {
      const cursor = endpoint.response?.body?.meta?.next_cursor;
      if (cursor) endpoint.values.cursor = cursor;
    },

    async copy(value, label = "Contenido") {
      const text = String(value ?? "");
      if (!text) return;
      try {
        await navigator.clipboard.writeText(text);
        this.$dispatch("toast", { tone: "success", message: label + " copiado correctamente." });
      } catch (_error) {
        this.$dispatch("toast", { tone: "warning", message: "No fue posible copiar automáticamente." });
      }
    },

    destroy() {
      this.token = "";
      this.authDetails = null;
      this.swagger = null;
      this.swaggerReady = false;
      if (this.$refs.swagger) this.$refs.swagger.replaceChildren();
    },
  };
}
