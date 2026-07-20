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
  assert.ok(api.components.schemas.Agency.required.includes("estado"));
  assert.ok(api.components.schemas.Agency.required.includes("centro_operaciones"));
  assert.equal(api.components.schemas.Agency.properties.centro_operaciones.type, "boolean");
  assert.deepEqual(api.components.schemas.AgencyPublicStatus.enum, ["Activa", "Inactiva", "Cerrada temporalmente", "En revisión", "Trasladada"]);
  assert.ok(api.components.schemas.AgencyChanges.properties.data.properties.upserted.items.required.includes("estado"));
  assert.ok(api.components.schemas.AgencyChanges.properties.data.properties.upserted.items.required.includes("centro_operaciones"));
  assert.equal(api.components.schemas.Metadata.properties.schema_version.example, 2);
});
