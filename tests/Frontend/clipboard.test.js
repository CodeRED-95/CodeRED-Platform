import assert from "node:assert/strict";
import test from "node:test";
import { copyToClipboard, fallbackCopy } from "../../resources/js/clipboard.js";

test("copies null numbers unicode and json through secure clipboard", async () => {
  const values = [];
  const environment = { isSecureContext: true, navigator: { clipboard: { writeText: async (value) => values.push(value) } } };
  await copyToClipboard(null, environment);
  await copyToClipboard(31, environment);
  await copyToClipboard("Ñandú", environment);
  await copyToClipboard('{"ruc":"20123456789"}', environment);
  assert.deepEqual(values, ["", "31", "Ñandú", '{"ruc":"20123456789"}']);
});

test("fallback uses a temporary readonly textarea", () => {
  let selected = false;
  let removed = false;
  const textarea = { style: {}, setAttribute: () => {}, select: () => { selected = true; }, remove: () => { removed = true; } };
  const documentRef = { createElement: () => textarea, body: { appendChild: () => {} }, execCommand: (command) => command === "copy" };
  fallbackCopy("RUC", documentRef);
  assert.equal(textarea.value, "RUC");
  assert.equal(selected, true);
  assert.equal(removed, true);
});
