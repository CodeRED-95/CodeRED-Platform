// Empaqueta dist/ en release/shalom-recordar-extension-<version>.zip.
//
// El ZIP se construye en Node (scripts/zip.mjs): antes se llamaba al binario
// `zip`, que no existe en Windows, de modo que `npm run package` fallaba con
// exit 1 sin explicar por que.

import { mkdir, writeFile, readFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildZip } from './zip.mjs';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const DIST = join(ROOT, 'dist');

if (!existsSync(join(DIST, 'manifest.json'))) {
    throw new Error('No existe dist/manifest.json. Ejecuta primero: npm run build');
}

const packageJson = JSON.parse(await readFile(join(ROOT, 'package.json'), 'utf8'));
const zipName = `shalom-recordar-extension-${packageJson.version}.zip`;

await mkdir(join(ROOT, 'release'), { recursive: true });
await writeFile(join(ROOT, 'release', zipName), buildZip(DIST));

console.log(`Paquete generado: release/${zipName}`);
