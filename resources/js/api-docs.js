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

export function buildApiPath(path, basePath = "/api/v1") {
  const normalizedPath = String(path ?? "").trim();
  const normalizedBase = "/" + String(basePath || "/api/v1").replace(/^\/+|\/+$/g, "");

  if (normalizedPath === normalizedBase || normalizedPath.startsWith(normalizedBase + "/")) {
    return normalizedPath;
  }

  return normalizedBase + "/" + normalizedPath.replace(/^\/+/, "");
}

export function buildRequestUrl(basePath, path, parameters = [], values = {}) {
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

  const url = new URL(buildApiPath(resolvedPath, basePath), window.location.origin);
  query.forEach((value, key) => url.searchParams.set(key, value));
  return url;
}

export async function parseResponseBody(response) {
  if (response.status === 204 || response.status === 304) {
    return { body: null, text: "" };
  }

  const text = await response.text();
  if (text === "") return { body: null, text };

  const contentType = response.headers.get("content-type") ?? "";
  if (contentType.includes("application/json")) {
    try {
      return { body: JSON.parse(text), text };
    } catch (_error) {
      return { body: { parseError: true, raw: text }, text };
    }
  }

  return { body: text, text };
}

export async function executeApiRequest({ requestTarget, method, token, isProtected, timeoutMs = 15000, fetchImpl = fetch }) {
  const controller = new AbortController();
  const timeoutId = globalThis.setTimeout(() => controller.abort(), timeoutMs);
  const headers = { Accept: "application/json" };
  if (isProtected) headers.Authorization = `Bearer ${normalizeBearerToken(token)}`;
  const options = { method, headers, signal: controller.signal };
  if (isProtected) options.credentials = "omit";
  const started = performance.now();

  try {
    const response = await fetchImpl(requestTarget, options);
    const parsed = await parseResponseBody(response);
    return { response, ...parsed, duration: Math.round(performance.now() - started) };
  } finally {
    globalThis.clearTimeout(timeoutId);
  }
}

export function generateCurl(url, isProtected) {
  const authorization = isProtected ? ' -H "Authorization: Bearer TU_TOKEN"' : "";
  return `curl -X GET "${url}" -H "Accept: application/json"${authorization}`;
}

export function generateFetch(url, isProtected) {
  const parsedUrl = new URL(url, window.location.origin);
  const target = parsedUrl.origin === window.location.origin ? parsedUrl.pathname + parsedUrl.search : parsedUrl.toString();
  const authorization = isProtected ? "\n      Authorization: 'Bearer TU_TOKEN'," : "";
  return `const response = await fetch('${target}', {\n  headers: {\n    Accept: 'application/json',${authorization}\n  },\n});\n\nconst data = await response.json();`;
}

export function normalizeBearerToken(value) {
  return String(value ?? "").trim().replace(/^Bearer\s+/i, "");
}

export function apiErrorMessage(status) {
  return ({
    401: "Token inválido, expirado o revocado.",
    403: "El token no tiene la ability necesaria para realizar esta consulta.",
    404: "El endpoint o recurso solicitado no existe.",
    409: "El cursor dejó de ser válido. Se requiere una sincronización completa.",
    410: "El cursor dejó de ser válido. Se requiere una sincronización completa.",
    422: "Los parámetros enviados no son válidos.",
    429: "Se alcanzó el límite de solicitudes. Inténtalo nuevamente más tarde.",
    500: "La API encontró un error interno.",
  })[status] ?? (status >= 500 ? "La API encontró un error interno." : null);
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

    get apiBaseUrl() {
      return window.location.origin + config.basePath;
    },

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
          const requestUrl = new URL(request.url, window.location.origin);
          if (requestUrl.origin === window.location.origin && requestUrl.pathname.startsWith("/api/v1/")) {
            request.credentials = "omit";
          }
          return request;
        },
        onComplete: () => { this.swaggerReady = true; },
      });
    },

    async authorize() {
      this.token = normalizeBearerToken(this.token);
      if (!this.token) {
        this.authStatus = "invalid";
        this.authMessage = "Ingresa un token para autorizar.";
        return;
      }
      this.authStatus = "loading";
      try {
        const response = await fetch("/api/v1/me", {
          credentials: "omit",
          headers: { Accept: "application/json", Authorization: `Bearer ${this.token}` },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
          this.authStatus = response.status === 403 ? "forbidden" : "invalid";
          this.authMessage = response.status === 403
            ? "Token válido, pero sin permiso profile:read."
            : apiErrorMessage(response.status) ?? "No fue posible validar el token.";
          this.authDetails = null;
          return;
        }
        this.authStatus = "valid";
        this.authMessage = "Token válido";
        this.authDetails = data;
      } catch (_error) {
        this.authStatus = "invalid";
        this.authMessage = "No fue posible conectar con la API. Comprueba que la documentación y la API utilicen el mismo protocolo y dominio.";
      }
    },

    clearAuthorization() {
      this.token = "";
      this.authStatus = "idle";
      this.authMessage = "No autorizado";
      this.authDetails = null;
    },

    async execute(endpoint) {
      if (!endpoint) return;
      const missing = endpoint.parameters.filter((parameter) => parameter.required && String(endpoint.values[parameter.name] ?? "").trim() === "");
      if (missing.length) {
        endpoint.expanded = true;
        endpoint.response = { status: 422, error: "Completa los parámetros obligatorios: " + missing.map((parameter) => parameter.name).join(", ") + ".", bodyText: "", headersText: "{}" };
        return;
      }
      if (endpoint.protected && !normalizeBearerToken(this.token)) {
        endpoint.expanded = true;
        endpoint.response = { error: "Autoriza un token antes de probar este endpoint.", status: 401 };
        return;
      }

      let url;
      try {
        url = buildRequestUrl(config.basePath, endpoint.path, endpoint.parameters, endpoint.values);
        if (url.origin !== window.location.origin || endpoint.method !== "GET") throw new Error("Endpoint no permitido");
      } catch (_error) {
        endpoint.expanded = true;
        endpoint.response = { status: 0, error: "No fue posible preparar la solicitud. Revisa la ruta y sus parámetros.", bodyText: "", headersText: "{}" };
        return;
      }

      endpoint.loading = true;
      endpoint.expanded = true;
      const requestTarget = url.pathname + url.search;
      let result;
      try {
        result = await executeApiRequest({
          requestTarget,
          method: endpoint.method,
          token: this.token,
          isProtected: endpoint.protected,
        });
      } catch (error) {
        endpoint.response = {
          status: 0,
          requestTarget,
          method: endpoint.method,
          error: error?.name === "AbortError"
            ? "La solicitud superó el tiempo máximo de espera."
            : "No fue posible conectar con la API. Comprueba que la documentación y la API utilicen el mismo protocolo y dominio.",
          bodyText: "",
          headersText: "{}",
        };
        endpoint.loading = false;
        return;
      }

      const { response, body, text, duration } = result;
      const safeHeaders = {};
      ["content-type", "etag", "last-modified", "x-ratelimit-limit", "x-ratelimit-remaining", "retry-after"].forEach((name) => {
        const value = response.headers.get(name);
        if (value !== null) safeHeaders[name] = value;
      });
      endpoint.response = {
        status: response.status,
        ok: response.ok,
        statusText: response.statusText,
        duration,
        size: new TextEncoder().encode(text).length,
        requestTarget,
        method: endpoint.method,
        body,
        bodyText: typeof body === "string" ? body : JSON.stringify(body, null, 2),
        headers: safeHeaders,
        headersText: JSON.stringify(safeHeaders, null, 2),
        curl: generateCurl(url.toString(), endpoint.protected),
        fetch: generateFetch(url.toString(), endpoint.protected),
        error: response.ok ? null : apiErrorMessage(response.status),
      };
      endpoint.loading = false;
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
