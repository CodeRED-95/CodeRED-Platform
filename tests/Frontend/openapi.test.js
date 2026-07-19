import test from "node:test";
import assert from "node:assert/strict";
import SwaggerParser from "@apidevtools/swagger-parser";

test("OpenAPI contract is valid and documents every public v1 endpoint", async () => {
  const api = await SwaggerParser.validate("docs/openapi.yaml");
  assert.equal(api.openapi, "3.0.3");
  assert.deepEqual(api.components.securitySchemes.bearerAuth, { type: "http", scheme: "bearer", bearerFormat: "Sanctum" });
  for (const path of ["/health", "/me", "/agencies", "/agencies/changes", "/agencies/{code}", "/catalog/metadata"]) {
    assert.ok(api.paths[path]?.get, path + " must be documented");
  }
});
