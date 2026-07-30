import { createWriteStream } from 'node:fs';
import { mkdir, rm } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';

await mkdir('release', { recursive: true });
const zipPath = 'release/buscador-shalom-control-1.0.0.zip';
await rm(zipPath, { force: true });
const result = spawnSync('zip', ['-r', `../${zipPath}`, '.'], { cwd: 'dist', stdio: 'inherit' });
if (result.status !== 0) {
  const fallback = createWriteStream(zipPath);
  fallback.end('zip command unavailable; run npm run build and zip dist contents manually.');
  process.exitCode = 1;
}
