import { mkdir, rm, readFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import { join } from 'node:path';

const root = process.cwd();
const packageJson = JSON.parse(await readFile(join(root, 'package.json'), 'utf8'));
const releaseDir = join(root, 'release');
const zipName = `codered-chrome-extension-${packageJson.version}.zip`;
const zipPath = join(releaseDir, zipName);

await mkdir(releaseDir, { recursive: true });
await rm(zipPath, { force: true });

const result = spawnSync('zip', ['-r', zipPath, '.'], {
  cwd: join(root, 'dist'),
  stdio: 'inherit',
});

if (result.status !== 0) {
  process.exit(result.status ?? 1);
}

console.log(`Paquete generado: release/${zipName}`);
