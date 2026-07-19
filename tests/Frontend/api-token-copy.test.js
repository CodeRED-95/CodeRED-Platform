import test from "node:test";
import assert from "node:assert/strict";
import { selectTokenText, writeTokenToClipboard } from "../../resources/js/api-token-copy.js";

test("copies the exact token through Clipboard API", async () => {
  const token = "25|exact-token-value";
  let copied = null;
  await writeTokenToClipboard(token, { writeText: async (value) => { copied = value; } });
  assert.equal(copied, token);
});

test("reports unavailable Clipboard API", async () => {
  await assert.rejects(() => writeTokenToClipboard("25|token", null), /Clipboard API no disponible/);
});

test("selects the visible token without scrolling", () => {
  let focusedOptions = null;
  const element = { focus: (options) => { focusedOptions = options; } };
  const range = { selectNodeContents: (node) => assert.equal(node, element) };
  const selection = { removeAllRanges() {}, addRange: (value) => assert.equal(value, range) };
  global.document = { createRange: () => range };
  global.window = { getSelection: () => selection };
  assert.equal(selectTokenText(element), true);
  assert.deepEqual(focusedOptions, { preventScroll: true });
  delete global.document;
  delete global.window;
});
