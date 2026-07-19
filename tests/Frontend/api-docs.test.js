import test from "node:test";
import assert from "node:assert/strict";
import { apiErrorMessage, buildRequestUrl, generateCurl, generateFetch } from "../../resources/js/api-docs.js";

globalThis.window = { location: { origin: "https://platform.codered.host" } };

test("buildRequestUrl encodes path and sends only non-empty parameters", () => {
  const url = buildRequestUrl("https://platform.codered.host/api/v1", "/agencies/{code}", [
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
  assert.equal(apiErrorMessage(403), "El token no tiene la ability requerida.");
  assert.equal(apiErrorMessage(410), "El cursor ya no es válido; realiza una sincronización completa.");
  assert.equal(apiErrorMessage(429), "Se alcanzó el límite de solicitudes.");
  assert.equal(apiErrorMessage(500), "La API encontró un error inesperado.");
});
