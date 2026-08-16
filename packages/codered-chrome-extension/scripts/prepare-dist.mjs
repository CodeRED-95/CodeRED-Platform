import { cp, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { join } from 'node:path';

const root = process.cwd();
const dist = join(root, 'dist');
const packageJson = JSON.parse(await readFile(join(root, 'package.json'), 'utf8'));
const manifest = JSON.parse(await readFile(join(root, 'manifest.json'), 'utf8'));

manifest.version = packageJson.version;

await writeFile(join(dist, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`);
await mkdir(join(dist, 'icons'), { recursive: true });
await cp(join(root, 'icons'), join(dist, 'icons'), { recursive: true });

for (const name of ['popup', 'options']) {
  const candidates = [
    join(dist, 'src', name, `${name}.html`),
    join(dist, `${name}.html`),
  ];
  let from = null;
  for (const candidate of candidates) {
    try {
      await readFile(candidate, 'utf8');
      from = candidate;
      break;
    } catch {
      continue;
    }
  }
  if (!from) {
    throw new Error(`No se encontró el HTML compilado para ${name}.`);
  }
  const to = join(dist, `${name}.html`);
  let html = await readFile(from, 'utf8');
  html = html
    .replace(/\n\s*<link rel="modulepreload"[^>]*>/g, '')
    .replaceAll(' crossorigin', '')
    .replaceAll('href="/assets/', 'href="./assets/')
    .replaceAll('src="/assets/', 'src="./assets/')
    .replaceAll('href="../../assets/', 'href="./assets/')
    .replaceAll('src="../../assets/', 'src="./assets/')
    .replaceAll(`/${name}.ts`, `./assets/${name}.js`)
    .replaceAll(`./${name}.ts`, `./assets/${name}.js`);
  await writeFile(to, html);
}

await rm(join(dist, 'src'), { recursive: true, force: true });
