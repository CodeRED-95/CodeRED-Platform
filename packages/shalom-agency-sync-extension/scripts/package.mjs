import {mkdir, readFile} from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import path from 'node:path';
const root = fileURLToPath(new URL('../', import.meta.url));
const pkg = JSON.parse(await readFile(path.join(root, 'package.json'), 'utf8'));
const name = `codered-shalom-agency-sync-${pkg.version}.zip`;
await mkdir(path.join(root, 'releases'), {recursive: true});
const target = path.join(root, 'releases', name);
if (process.platform === 'win32') {
  const quote = (value) => `'${value.replaceAll("'", "''")}'`;
  execFileSync('pwsh', ['-NoProfile', '-Command', `Add-Type -AssemblyName System.IO.Compression.FileSystem; [System.IO.File]::Delete(${quote(target)}); [System.IO.Compression.ZipFile]::CreateFromDirectory(${quote(path.join(root, 'dist'))}, ${quote(target)})`]);
} else execFileSync('zip', ['-r', target, '.'], {cwd: path.join(root, 'dist')});
console.log(target);
