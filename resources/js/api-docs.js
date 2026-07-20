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
  const headers = buildApiHeaders({ authenticated: isProtected, token });
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

export function normalizeAbilities(value) {
  if (!Array.isArray(value)) return [];

  return [...new Set(value.filter((ability) => typeof ability === "string").map((ability) => ability.trim()).filter(Boolean))];
}

export function tokenHasAbility(abilities, requiredAbility) {
  if (!requiredAbility || requiredAbility === "Público") return true;

  const normalized = normalizeAbilities(abilities);
  return normalized.includes("*") || normalized.includes(requiredAbility);
}

export function extractTokenProfile(body) {
  const user = body?.user ?? (
    body && (body.id !== undefined || body.name !== undefined)
      ? { id: body.id ?? null, name: body.name ?? null, email: body.email ?? null }
      : null
  );

  return {
    user,
    tokenName: body?.token?.name ?? body?.token_name ?? null,
    abilities: normalizeAbilities(body?.token?.abilities ?? body?.abilities),
  };
}

export function endpointAccess(endpoint, auth) {
  if (!endpoint?.protected) {
    return { available: true, state: "public", label: "Público", message: "Este endpoint no requiere token." };
  }

  if (!normalizeBearerToken(auth?.token) || !auth?.authorized) {
    return { available: false, state: "authorization-required", label: "Requiere autorización", message: "Autoriza un token para probar este endpoint." };
  }

  if (auth?.abilitiesKnown === false) {
    return { available: true, state: "unverified", label: "Ability no verificada", message: `La API no permitió consultar las abilities. El servidor validará ${endpoint.ability} al ejecutar.` };
  }

  if (tokenHasAbility(auth?.abilities, endpoint.ability)) {
    return { available: true, state: "available", label: "Disponible", message: "El token posee la ability requerida." };
  }

  return { available: false, state: "forbidden", label: "Sin permiso", message: `Este token no posee la ability requerida: ${endpoint.ability}.` };
}

export class ApiDocsValidationError extends Error {
  constructor(message) {
    super(message);
    this.name = "ApiDocsValidationError";
  }
}

export function createApiDocsAuthStore() {
  return {
    token: "",
    authorized: false,
    abilities: [],
    abilitiesKnown: false,
    user: null,
    tokenName: null,
    validatedAt: null,
    status: "idle",
    message: "No autorizado",
    details: null,

    setToken(value) {
      this.token = normalizeBearerToken(value);
    },

    clear() {
      this.token = "";
      this.authorized = false;
      this.abilities = [];
      this.abilitiesKnown = false;
      this.user = null;
      this.tokenName = null;
      this.validatedAt = null;
      this.status = "idle";
      this.message = "No autorizado";
      this.details = null;
    },
  };
}

export function buildApiHeaders({ authenticated = false, token = "" } = {}) {
  const headers = { Accept: "application/json" };

  if (authenticated) {
    const normalizedToken = normalizeBearerToken(token);
    if (!normalizedToken) {
      throw new ApiDocsValidationError("Debes autorizar un token antes de probar este endpoint.");
    }
    headers.Authorization = `Bearer ${normalizedToken}`;
  }

  return headers;
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
    showToken: false,
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
        this.$store.apiDocsAuth.message = "No fue posible cargar el contrato OpenAPI.";
        this.$dispatch("toast", { tone: "danger", message: this.$store.apiDocsAuth.message });
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

    endpointAccess(endpoint) {
      return endpointAccess(endpoint, this.$store.apiDocsAuth);
    },

    canExecute(endpoint) {
      return this.endpointAccess(endpoint).available;
    },

    availableEndpointsCount() {
      return this.endpoints.filter((endpoint) => this.canExecute(endpoint)).length;
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
      const auth = this.$store.apiDocsAuth;
      auth.setToken(auth.token);
      if (!auth.token) {
        auth.status = "invalid";
        auth.message = "Ingresa un token para autorizar.";
        return;
      }
      auth.status = "loading";
      try {
        const { response, body } = await executeApiRequest({
          requestTarget: "/api/v1/me",
          method: "GET",
          token: auth.token,
          isProtected: true,
        });
        if (!response.ok) {
          const metadataForbidden = response.status === 403;
          auth.authorized = metadataForbidden;
          auth.status = metadataForbidden ? "limited" : "invalid";
          auth.message = metadataForbidden
            ? "Token aceptado; sus abilities no se pueden consultar con este token."
            : apiErrorMessage(response.status) ?? "No fue posible validar el token.";
          auth.details = null;
          auth.abilities = [];
          auth.abilitiesKnown = false;
          auth.user = null;
          auth.tokenName = null;
          auth.validatedAt = metadataForbidden ? new Date().toISOString() : null;
          return;
        }
        const profile = extractTokenProfile(body);
        auth.authorized = true;
        auth.status = profile.abilities.includes("*") ? "full" : "valid";
        auth.message = profile.abilities.includes("*") ? "Acceso total" : "Token válido";
        auth.details = body;
        auth.abilities = profile.abilities;
        auth.abilitiesKnown = true;
        auth.user = profile.user;
        auth.tokenName = profile.tokenName;
        auth.validatedAt = new Date().toISOString();
      } catch (error) {
        auth.authorized = false;
        auth.status = "invalid";
        auth.message = error?.name === "AbortError"
          ? "La validación superó el tiempo máximo de espera."
          : "No fue posible conectar con la API. Comprueba que la documentación y la API utilicen el mismo protocolo y dominio.";
      }
    },

    clearAuthorization() {
      this.$store.apiDocsAuth.clear();
    },

    async execute(endpoint) {
      if (!endpoint) return;
      const missing = endpoint.parameters.filter((parameter) => parameter.required && String(endpoint.values[parameter.name] ?? "").trim() === "");
      if (missing.length) {
        endpoint.expanded = true;
        endpoint.response = { kind: "validation", status: 422, error: "Completa los parámetros obligatorios: " + missing.map((parameter) => parameter.name).join(", ") + ".", bodyText: "", headersText: "{}" };
        return;
      }
      const auth = this.$store.apiDocsAuth;
      const access = this.endpointAccess(endpoint);
      if (!access.available) {
        endpoint.expanded = true;
        endpoint.response = { kind: "validation", error: access.message, status: 0 };
        return;
      }

      if (endpoint.protected && !normalizeBearerToken(auth.token)) {
        endpoint.expanded = true;
        endpoint.response = { kind: "validation", error: "Debes autorizar un token antes de probar este endpoint.", status: 0 };
        return;
      }

      let url;
      try {
        url = buildRequestUrl(config.basePath, endpoint.path, endpoint.parameters, endpoint.values);
        if (url.origin !== window.location.origin || endpoint.method !== "GET") throw new Error("Endpoint no permitido");
      } catch (_error) {
        endpoint.expanded = true;
        endpoint.response = { kind: "validation", status: 0, error: "No fue posible preparar la solicitud. Revisa la ruta y sus parámetros.", bodyText: "", headersText: "{}" };
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
          token: auth.token,
          isProtected: endpoint.protected,
        });
      } catch (error) {
        endpoint.response = {
          kind: "network",
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
        kind: "http",
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
      this.swagger = null;
      this.swaggerReady = false;
      if (this.$refs.swagger) this.$refs.swagger.replaceChildren();
    },
  };
}
