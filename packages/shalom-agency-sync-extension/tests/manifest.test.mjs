import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
test('manifiesto independiente y permisos limitados',async()=>{
  const manifest=JSON.parse(await readFile(new URL('../manifest.json',import.meta.url),'utf8'));
  const pkg=JSON.parse(await readFile(new URL('../package.json',import.meta.url),'utf8'));
  assert.equal(manifest.version,pkg.version);
  assert.equal(manifest.manifest_version,3);
  assert.deepEqual(manifest.permissions,['storage']);
  assert.equal(manifest.host_permissions.length,2);
  assert(!JSON.stringify(manifest).includes('<all_urls>'));
  assert(!JSON.stringify(manifest).includes('unsafe-eval'));
  assert.deepEqual(manifest.web_accessible_resources[0].resources,['capture.mjs']);
});
