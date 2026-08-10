import { readFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import { join } from 'node:path';

const root = process.cwd();
const packageJson = JSON.parse(await readFile(join(root, 'package.json'), 'utf8'));
const manifest = JSON.parse(await readFile(join(root, 'manifest.json'), 'utf8'));
const errors = [];

if (manifest.version !== packageJson.version) {
  errors.push(`manifest.json (${manifest.version}) y package.json (${packageJson.version}) no están sincronizados`);
}

for (const file of ['background.js', 'content.js', 'crypto.js', 'db.js', 'popup.js', 'sync.js', 'tests/capture.test.cjs', 'tests/session.test.cjs']) {
  const result = spawnSync(process.execPath, ['--check', join(root, file)], { encoding: 'utf8' });
  if (result.status !== 0) {
    errors.push(`node --check ${file} falló: ${result.stderr || result.stdout}`.trim());
  }
}

if (errors.length > 0) {
  console.error('Typecheck inválido:');
  for (const error of errors) console.error(`- ${error}`);
  process.exit(1);
}

console.log('Typecheck válido.');
