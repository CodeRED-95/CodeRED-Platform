#!/usr/bin/env node
import path from 'node:path';
import { inspectJkAnime } from './inspector.js';

function parseArgs(argv) {
  const positional = [];
  const flags = {
    baseUrl: process.env.JKANIME_BASE_URL || 'https://jkanime.net',
    outputDir: path.resolve('output'),
    headless: true,
    includeMediaUrls: false,
    captureBodies: false,
    collectDom: false,
    collectStatic: false,
    staticOnly: false,
  };

  for (let index = 0; index < argv.length; index += 1) {
    const arg = argv[index];

    if (arg === '--base-url') {
      flags.baseUrl = argv[++index];
    } else if (arg === '--output') {
      flags.outputDir = path.resolve(argv[++index]);
    } else if (arg === '--headed') {
      flags.headless = false;
    } else if (arg === '--include-media-urls') {
      flags.includeMediaUrls = true;
    } else if (arg === '--capture-bodies') {
      flags.captureBodies = true;
    } else if (arg === '--collect-dom') {
      flags.collectDom = true;
    } else if (arg === '--collect-static') {
      flags.collectStatic = true;
    } else if (arg === '--static-only') {
      flags.collectStatic = true;
      flags.staticOnly = true;
    } else {
      positional.push(arg);
    }
  }

  const [slug, episode] = positional;

  if (!slug || !episode || !Number.isInteger(Number(episode))) {
    throw new Error('Uso: npm run inspect -- <anime-slug> <episode> [--base-url https://jkanime.net] [--output output]');
  }

  return { ...flags, slug, episode: Number(episode) };
}

try {
  const options = parseArgs(process.argv.slice(2));
  const globalTimeout = setTimeout(() => {
    console.error('Inspection timed out after 90000ms.');
    process.exit(124);
  }, 90000);

  const report = await inspectJkAnime(options);
  clearTimeout(globalTimeout);
  console.log(JSON.stringify(report, null, 2));
} catch (error) {
  console.error(error instanceof Error ? error.message : String(error));
  process.exitCode = 1;
}
