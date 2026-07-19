import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { apiErrorMessage, buildRequestUrl, generateCurl, generateFetch, normalizeBearerToken } from "../../resources/js/api-docs.js";

globalThis.window = { location: { origin: "https://platform.codered.host" } };

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
  assert.match(source, /fetch\(requestTarget/);
  assert.match(source, /credentials: "omit"/);
  assert.match(source, /headers\.Authorization = authorization\.replace/);
  assert.doesNotMatch(source, /192\.168\.18\.124|http:\/\/platform\.codered\.host/);
  assert.doesNotMatch(source, /localStorage|sessionStorage/);
});
