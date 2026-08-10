import { cp, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { join } from 'node:path';

const root = process.cwd();
const dist = join(root, 'dist');
const packageJson = JSON.parse(await readFile(join(root, 'package.json'), 'utf8'));
const manifest = JSON.parse(await readFile(join(root, 'manifest.json'), 'utf8'));

manifest.version = packageJson.version;

await rm(dist, { recursive: true, force: true });
await mkdir(dist, { recursive: true });

const files = ['background.js', 'content.js', 'crypto.js', 'db.js', 'manifest.json', 'popup.html', 'popup.js', 'sync.js', 'icon128.png'];
for (const file of files) {
  if (file === 'manifest.json') {
    await writeFile(join(dist, file), `${JSON.stringify(manifest, null, 2)}\n`);
    continue;
  }

  await cp(join(root, file), join(dist, file));
}
