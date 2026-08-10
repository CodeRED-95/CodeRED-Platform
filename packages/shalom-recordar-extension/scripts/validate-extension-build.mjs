import { access, readFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import { join } from 'node:path';

const root = process.cwd();
const dist = join(root, 'dist');
const packageJson = JSON.parse(await readFile(join(root, 'package.json'), 'utf8'));
const errors = [];

async function exists(relativePath) {
  try {
    await access(join(dist, relativePath));
    return true;
  } catch {
    errors.push(`No existe dist/${relativePath}`);
    return false;
  }
}

function nodeCheck(relativePath) {
  const result = spawnSync(process.execPath, ['--check', join(dist, relativePath)], { encoding: 'utf8' });
  if (result.status !== 0) {
    errors.push(`node --check dist/${relativePath} falló: ${result.stderr || result.stdout}`.trim());
  }
}

if (await exists('manifest.json')) {
  const manifest = JSON.parse(await readFile(join(dist, 'manifest.json'), 'utf8'));

  if (manifest.version !== packageJson.version) {
    errors.push(`La versión del manifest (${manifest.version}) no coincide con package.json (${packageJson.version})`);
  }

  for (const file of ['background.js', 'content.js', 'crypto.js', 'db.js', 'popup.html', 'popup.js', 'sync.js', 'icon128.png']) {
    await exists(file);
  }

  for (const file of ['background.js', 'content.js', 'crypto.js', 'db.js', 'popup.js', 'sync.js']) {
    if (await exists(file)) nodeCheck(file);
  }

  const html = await readFile(join(dist, 'popup.html'), 'utf8');
  if (/node_modules|\.env|package-lock\.json|\.zip/i.test(html)) {
    errors.push('popup.html contiene referencias indebidas');
  }
}

if (errors.length > 0) {
  console.error('Build de extensión inválido:');
  for (const error of errors) console.error(`- ${error}`);
  process.exit(1);
}

console.log('Build de extensión válido.');
