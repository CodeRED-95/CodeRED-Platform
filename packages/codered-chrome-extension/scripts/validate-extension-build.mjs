import { access, readFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';

const root = process.cwd();
const dist = join(root, 'dist');
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

async function readDist(relativePath) {
  return readFile(join(dist, relativePath), 'utf8');
}

function collectHtmlReferences(html) {
  const refs = [];
  const pattern = /(?:src|href)="([^"]+)"/g;
  let match;
  while ((match = pattern.exec(html)) !== null) {
    const value = match[1];
    if (value.startsWith('data:') || value.startsWith('http:') || value.startsWith('https:') || value.startsWith('#')) continue;
    refs.push(value.replace(/^\.\//, ''));
  }
  return refs;
}

function checkNodeSyntax(relativePath) {
  const result = spawnSync(process.execPath, ['--check', join(dist, relativePath)], { encoding: 'utf8' });
  if (result.status !== 0) errors.push(`node --check dist/${relativePath} fallo: ${result.stderr || result.stdout}`.trim());
}

if (await exists('manifest.json')) {
  const manifest = JSON.parse(await readDist('manifest.json'));

  const critical = ['content.js', 'background.js', 'popup.html', 'options.html'];
  for (const file of critical) await exists(file);

  if (manifest.background?.service_worker !== 'background.js') errors.push('manifest.background.service_worker debe ser background.js');
  if (manifest.background?.type !== 'module') errors.push('manifest.background.type debe ser module para el service worker actual');

  const contentScripts = manifest.content_scripts ?? [];
  for (const script of contentScripts) {
    for (const js of script.js ?? []) {
      await exists(js);
      if (js !== 'content.js') errors.push(`content_scripts.js debe apuntar a content.js, no a ${js}`);
    }
  }

  if (manifest.action?.default_popup) await exists(manifest.action.default_popup);
  if (manifest.options_page) await exists(manifest.options_page);
  for (const icon of Object.values(manifest.icons ?? {})) await exists(icon);

  if (await exists('content.js')) {
    const content = await readDist('content.js');
    const forbidden = [
      ['import/export', /^[ \t]*(import|export)[ \t]/m],
      ['dynamic import', /\bimport\s*\(/],
      ['require', /\brequire\s*\(/],
    ];
    for (const [label, pattern] of forbidden) {
      if (pattern.test(content)) errors.push(`dist/content.js contiene ${label}`);
    }
    checkNodeSyntax('content.js');
  }

  if (await exists('background.js')) checkNodeSyntax('background.js');

  for (const htmlFile of ['popup.html', 'options.html']) {
    if (!(await exists(htmlFile))) continue;
    const html = await readDist(htmlFile);
    if (/href="\/assets|src="\/assets/.test(html)) errors.push(`dist/${htmlFile} contiene rutas absolutas /assets`);
    if (/modulepreload-polyfill/.test(html)) errors.push(`dist/${htmlFile} precarga modulepreload-polyfill`);
    for (const reference of collectHtmlReferences(html)) {
      await exists(join(dirname(htmlFile), reference).replaceAll('\\', '/'));
    }
  }
}

if (errors.length > 0) {
  console.error('Build de extension invalido:');
  for (const error of errors) console.error(`- ${error}`);
  process.exit(1);
}

console.log('Build de extension valido.');
