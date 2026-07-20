import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { apiErrorMessage, buildApiPath, buildRequestUrl, executeApiRequest, generateCurl, generateFetch, normalizeBearerToken, parseResponseBody } from "../../resources/js/api-docs.js";

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

test("the interactive client uses same-origin relative request targets", () => {
  const source = fs.readFileSync("resources/js/api-docs.js", "utf8");
  assert.match(source, /fetch\("\/api\/v1\/me"/);
  assert.match(source, /fetchImpl\(requestTarget/);
  assert.match(source, /credentials: "omit"/);
  assert.match(source, /headers\.Authorization = authorization\.replace/);
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
