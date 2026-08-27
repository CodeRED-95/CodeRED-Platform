// Empaqueta la extension para subirla a la Chrome Web Store.
//
// La Store rechaza el manifiesto si trae el campo `key` ("No se admite el campo
// key en el archivo de manifiesto") y gestiona ella misma las actualizaciones,
// asi que tampoco debe llevar `update_url`. Ambos campos SOLO hacen falta para
// la instalacion autoalojada (unpacked / .crx con id estable), que se sigue
// generando aparte con `npm run pack:crx`.
//
// Este script parte de `dist/`, copia a un dir temporal, limpia el manifiesto y
// comprime. No toca `dist/` ni el flujo autoalojado.
//
// Uso:  npm run package:store   (ejecuta el build antes)

import { cp, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildZip } from './zip.mjs';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const DIST = join(ROOT, 'dist');
const STORE_DIST = join(ROOT, 'dist-store');

if (!existsSync(join(DIST, 'manifest.json'))) {
    throw new Error('No existe dist/manifest.json. Ejecuta primero: npm run build');
}

const packageJson = JSON.parse(await readFile(join(ROOT, 'package.json'), 'utf8'));

// Copia dist a un dir temporal y limpia el manifiesto para la Web Store.
await rm(STORE_DIST, { recursive: true, force: true });
await cp(DIST, STORE_DIST, { recursive: true });

const manifestPath = join(STORE_DIST, 'manifest.json');
const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
const removed = [];
if ('key' in manifest) { delete manifest.key; removed.push('key'); }
if ('update_url' in manifest) { delete manifest.update_url; removed.push('update_url'); }
await writeFile(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`);

await mkdir(join(ROOT, 'release'), { recursive: true });
const zipName = `shalom-recordar-store-${packageJson.version}.zip`;
await writeFile(join(ROOT, 'release', zipName), buildZip(STORE_DIST));
await rm(STORE_DIST, { recursive: true, force: true });

console.log(`Paquete para Chrome Web Store: release/${zipName}`);
console.log(`Campos quitados del manifiesto: ${removed.length ? removed.join(', ') : '(ninguno)'}`);
