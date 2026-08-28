#!/usr/bin/env node
import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';

const outputDir = path.resolve(process.argv[2] || 'output');
const sensitivePatterns = [
  /Bearer\s+[A-Za-z0-9._~+/=-]+/i,
  /XSRF-TOKEN=/i,
  /jkanime-session=/i,
  /csrf-token[^>]+content=["'][A-Za-z0-9]/i,
  /https?:\/\/[^\s"'<>]+\.(?:m3u8?|mp4)(?:[?#"'\s]|$)/i,
  /[?&](?:e|u|t|op)=[A-Za-z0-9_-]{10,}/i,
];

async function files(dir) {
  const entries = await readdir(dir, { withFileTypes: true });
  const nested = await Promise.all(entries.map(async (entry) => {
    const current = path.join(dir, entry.name);

    return entry.isDirectory() ? files(current) : [current];
  }));

  return nested.flat();
}

const findings = [];

for (const file of await files(outputDir)) {
  const text = await readFile(file, 'utf8');

  for (const pattern of sensitivePatterns) {
    if (pattern.test(text)) {
      findings.push({ file, pattern: String(pattern) });
    }
  }
}

if (findings.length > 0) {
  console.error(JSON.stringify(findings, null, 2));
  process.exitCode = 1;
} else {
  console.log(`Output audit passed for ${outputDir}`);
}
