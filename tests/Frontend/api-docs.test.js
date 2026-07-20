import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { ApiDocsValidationError, apiErrorMessage, buildApiHeaders, buildApiPath, buildRequestUrl, createApiDocsAuthStore, executeApiRequest, generateCurl, generateFetch, normalizeBearerToken, parseResponseBody } from "../../resources/js/api-docs.js";

globalThis.window = { location: { origin: "https://platform.codered.host" } };

test("buildApiPath applies the API prefix exactly once", () => {
  assert.equal(buildApiPath("/health"), "/api/v1/health");
  assert.equal(buildApiPath("health"), "/api/v1/health");
  assert.equal(buildApiPath("/api/v1/health"), "/api/v1/health");
  assert.equal(buildApiPath("/api/v1/agencies"), "/api/v1/agencies");
  assert.equal(buildApiPath("/api/v1"), "/api/v1");
});

test("buildRequestUrl encodes path and sends only non-empty parameters", () => {
  const url = buildRequestUrl("/api/v1", "/agencies/{code}", [
    { name: "code", in: "path" },
    { name: "search", in: "query" },
    { name: "empty", in: "query" },
    { name: "disabled", in: "query" },
  ], { code: "SHA 000610", search: "Lima norte", empty: "", disabled: false });

  assert.equal(url.toString(), "https://platform.codered.host/api/v1/agencies/SHA%20000610?search=Lima+norte");
});

test("generated examples never expose the in-memory token", () => {
  const url = "https://platform.codered.host/api/v1/agencies?per_page=10";
  const curl = generateCurl(url, true);
  const fetchExample = generateFetch(url, true);

  assert.match(curl, /Authorization: Bearer TU_TOKEN/);
  assert.match(fetchExample, /Authorization: 'Bearer TU_TOKEN'/);
  assert.ok(fetchExample.includes("fetch('/api/v1/agencies?per_page=10'"));
  assert.doesNotMatch(curl + fetchExample, /secret-real-token/);
});

test("API errors have safe actionable messages", () => {
  assert.equal(apiErrorMessage(401), "Token inválido, expirado o revocado.");
  assert.equal(apiErrorMessage(403), "El token no tiene la ability necesaria para realizar esta consulta.");
  assert.equal(apiErrorMessage(410), "El cursor dejó de ser válido. Se requiere una sincronización completa.");
  assert.equal(apiErrorMessage(422), "Los parámetros enviados no son válidos.");
  assert.equal(apiErrorMessage(429), "Se alcanzó el límite de solicitudes. Inténtalo nuevamente más tarde.");
  assert.equal(apiErrorMessage(500), "La API encontró un error interno.");
});

test("Bearer input is normalized without altering the token body", () => {
  assert.equal(normalizeBearerToken("  Bearer 1|abc.DEF  "), "1|abc.DEF");
  assert.equal(normalizeBearerToken("1|abc.DEF"), "1|abc.DEF");
});

test("authentication store keeps one normalized in-memory token and clears all state", () => {
  const store = createApiDocsAuthStore();
  store.setToken("  Bearer 1|abc.DEF  ");
  store.authorized = true;
  store.abilities = ["agencies:read"];
  store.user = { id: 1 };
  assert.equal(store.token, "1|abc.DEF");

  store.clear();
  assert.deepEqual({ token: store.token, authorized: store.authorized, abilities: store.abilities, user: store.user }, {
    token: "", authorized: false, abilities: [], user: null,
  });
});

test("central headers add Bearer exactly once and never add Content-Type to GET", () => {
  assert.deepEqual(buildApiHeaders(), { Accept: "application/json" });
  assert.deepEqual(buildApiHeaders({ authenticated: true, token: "Bearer 1|abc.DEF" }), {
    Accept: "application/json",
    Authorization: "Bearer 1|abc.DEF",
  });
  assert.throws(() => buildApiHeaders({ authenticated: true }), ApiDocsValidationError);
});

test("the interactive client uses same-origin relative request targets", () => {
  const source = fs.readFileSync("resources/js/api-docs.js", "utf8");
  assert.match(source, /requestTarget: "\/api\/v1\/me"/);
  assert.match(source, /fetchImpl\(requestTarget/);
  assert.match(source, /options\.credentials = "omit"/);
  assert.match(source, /buildApiHeaders/);
  assert.match(source, /apiDocsAuth/);
  assert.doesNotMatch(source, /192\.168\.18\.124|http:\/\/platform\.codered\.host/);
  assert.doesNotMatch(source, /localStorage|sessionStorage/);
});

test("parseResponseBody preserves status-compatible empty, text and invalid JSON bodies", async () => {
  assert.deepEqual(await parseResponseBody(new Response(null, { status: 204 })), { body: null, text: "" });
  assert.deepEqual(await parseResponseBody(new Response(null, { status: 304 })), { body: null, text: "" });
  assert.deepEqual(await parseResponseBody(new Response("plain", { status: 200, headers: { "content-type": "text/plain" } })), { body: "plain", text: "plain" });
  assert.deepEqual(await parseResponseBody(new Response("", { status: 200, headers: { "content-type": "application/json" } })), { body: null, text: "" });
  assert.deepEqual(await parseResponseBody(new Response("{broken", { status: 500, headers: { "content-type": "application/json" } })), { body: { parseError: true, raw: "{broken" }, text: "{broken" });
});

test("executeApiRequest sends a clean GET and does not turn HTTP errors into network errors", async () => {
  let captured;
  const result = await executeApiRequest({
    requestTarget: "/api/v1/agencies?per_page=1",
    method: "GET",
    token: "Bearer secret-not-logged",
    isProtected: true,
    fetchImpl: async (url, options) => {
      captured = { url, options };
      return new Response(JSON.stringify({ message: "interno" }), { status: 500, headers: { "content-type": "application/json" } });
    },
  });

  assert.equal(captured.url, "/api/v1/agencies?per_page=1");
  assert.equal(captured.options.method, "GET");
  assert.equal(captured.options.credentials, "omit");
  assert.equal(captured.options.headers.Accept, "application/json");
  assert.equal(captured.options.headers.Authorization, "Bearer secret-not-logged");
  assert.equal(captured.options.headers["Content-Type"], undefined);
  assert.equal(captured.options.mode, undefined);
  assert.equal(result.response.status, 500);
  assert.deepEqual(result.body, { message: "interno" });
});

test("agency detail card sends the shared Bearer token and receives 200", async () => {
  let captured;
  const auth = createApiDocsAuthStore();
  auth.setToken("Bearer 1|shared-token");
  const success = await executeApiRequest({
    requestTarget: "/api/v1/agencies/SHA-000297",
    method: "GET",
    token: auth.token,
    isProtected: true,
    fetchImpl: async (url, options) => {
      captured = { url, options };
      return new Response(JSON.stringify({ data: { code: "SHA-000297" } }), {
        status: 200,
        headers: { "content-type": "application/json" },
      });
    },
  });

  assert.equal(captured.url, "/api/v1/agencies/SHA-000297");
  assert.equal(captured.options.headers.Authorization, "Bearer 1|shared-token");
  assert.equal(captured.options.credentials, "omit");
  assert.equal(success.response.status, 200);
  assert.equal(success.body.data.code, "SHA-000297");
});

test("every HTTP error remains a response with its real status and body", async () => {
  for (const status of [401, 403, 404, 422, 429, 500]) {
    const result = await executeApiRequest({
      requestTarget: "/api/v1/agencies/SHA-000297",
      method: "GET",
      token: "1|valid-token",
      isProtected: true,
      fetchImpl: async () => new Response(JSON.stringify({ message: `HTTP ${status}` }), {
        status,
        headers: { "content-type": "application/json" },
      }),
    });
    assert.equal(result.response.status, status);
    assert.deepEqual(result.body, { message: `HTTP ${status}` });
    assert.notEqual(apiErrorMessage(status), null);
  }
});

test("public requests omit Authorization and credential overrides", async () => {
  let captured;
  await executeApiRequest({
    requestTarget: "/api/v1/health", method: "GET", token: "", isProtected: false,
    fetchImpl: async (_url, options) => { captured = options; return new Response("", { status: 200 }); },
  });
  assert.equal(captured.headers.Authorization, undefined);
  assert.equal(captured.credentials, undefined);
});

test("each request owns a fresh AbortController and timeout aborts explicitly", async () => {
  const signals = [];
  const fetchImpl = (_url, options) => new Promise((_resolve, reject) => {
    signals.push(options.signal);
    options.signal.addEventListener("abort", () => reject(new DOMException("timeout", "AbortError")), { once: true });
  });

  await assert.rejects(() => executeApiRequest({ requestTarget: "/api/v1/health", method: "GET", token: "", isProtected: false, timeoutMs: 5, fetchImpl }), { name: "AbortError" });
  await assert.rejects(() => executeApiRequest({ requestTarget: "/api/v1/health", method: "GET", token: "", isProtected: false, timeoutMs: 5, fetchImpl }), { name: "AbortError" });
  assert.notEqual(signals[0], signals[1]);
});

test("real fetch failures remain rejected as network errors", async () => {
  await assert.rejects(() => executeApiRequest({
    requestTarget: "/api/v1/health", method: "GET", token: "", isProtected: false,
    fetchImpl: async () => { throw new TypeError("Failed to fetch"); },
  }), TypeError);
});
