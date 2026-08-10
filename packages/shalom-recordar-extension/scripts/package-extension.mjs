import { mkdir, rm } from 'node:fs/promises';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';

const root = process.cwd();
const packageJson = JSON.parse(await readFile(join(root, 'package.json'), 'utf8'));
const releaseDir = join(root, 'dist');
const zipName = `shalom-recordar-extension-${packageJson.version}.zip`;

await mkdir(join(root, 'release'), { recursive: true });
await rm(join(root, 'release', zipName), { force: true });

const result = spawnSync('zip', ['-r', `../release/${zipName}`, '.'], {
  cwd: releaseDir,
  stdio: 'inherit',
});

if (result.status !== 0) {
  process.exit(result.status ?? 1);
}

console.log(`Paquete generado: release/${zipName}`);
