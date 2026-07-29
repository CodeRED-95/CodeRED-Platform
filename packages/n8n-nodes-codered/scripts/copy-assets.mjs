import { copyFile, mkdir } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const source = join(root, 'nodes', 'CodeRED', 'codered.svg');
const target = join(root, 'dist', 'nodes', 'CodeRED', 'codered.svg');

await mkdir(dirname(target), { recursive: true });
await copyFile(source, target);
