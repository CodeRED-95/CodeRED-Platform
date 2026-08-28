import { mkdir, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { execFile } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { promisify } from 'node:util';
import { chromium } from 'playwright';
import { redactBody, redactHeaders, safeJson, sanitizeUrl } from './redaction.js';

const execFileAsync = promisify(execFile);
const INTERESTING_PATTERN = /(\/ajax\/|\/c1\.php|\/c4\.php|\/player\/|\/embed\/|\.m3u8?|\.mp4|iframe|servers|desu|magi)/i;
const TEXTUAL_CONTENT_PATTERN = /(text\/|json|javascript|xml|html|x-www-form-urlencoded)/i;

function nowIso() {
  return new Date().toISOString();
}

function sleep(ms) {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}
async function withTimeout(promise, ms, label) {
  let timeout;

  const timer = new Promise((_, reject) => {
    timeout = setTimeout(() => reject(new Error(`${label} timed out after ${ms}ms`)), ms);
  });

  try {
    return await Promise.race([promise, timer]);
  } finally {
    clearTimeout(timeout);
  }
}

function classify(url, resourceType = '') {
  const haystack = `${url} ${resourceType}`;

  return {
    isInteresting: INTERESTING_PATTERN.test(haystack),
    isAjax: /\/ajax\//i.test(url),
    isPlayer: /\/player\/|\/embed\/|\/c1\.php|\/c4\.php/i.test(url),
    isMedia: /\.m3u8?|\.mp4/i.test(url),
  };
}

async function responseBody(response, contentType, maxBodyBytes) {
  if (!TEXTUAL_CONTENT_PATTERN.test(contentType || '')) {
    return null;
  }

  try {
    return redactBody(await withTimeout(response.text(), 5000, 'response body read'), maxBodyBytes);
  } catch (error) {
    return {
      error: error instanceof Error ? error.message : String(error),
    };
  }
}

async function collectScripts(page, outputDir) {
  const scriptsDir = path.join(outputDir, 'scripts');
  await mkdir(scriptsDir, { recursive: true });

  const scripts = await page.locator('script[src]').evaluateAll((nodes) => nodes.map((node) => node.src));
  const inlineScripts = await page.locator('script:not([src])').evaluateAll((nodes) => nodes.map((node) => node.textContent || '').filter(Boolean));

  await writeFile(path.join(scriptsDir, 'external.json'), JSON.stringify(scripts, null, 2));
  await writeFile(path.join(scriptsDir, 'inline.json'), JSON.stringify(inlineScripts.map((script) => script.slice(0, 20000)), null, 2));

  return { external: scripts, inlineCount: inlineScripts.length };
}

async function collectHtmlEvidence(url, outputDir, maxBodyBytes) {
  try {
    const tempDir = await mkdtemp(path.join(os.tmpdir(), 'codered-jkanime-'));
    const htmlPath = path.join(tempDir, 'page.html');

    await execFileAsync('timeout', [
      '25s',
      'curl',
      '--silent',
      '--show-error',
      '--location',
      '--http1.1',
      '--no-keepalive',
      '--connect-timeout',
      '10',
      '--max-time',
      '20',
      '--range',
      `0-${(maxBodyBytes * 8) - 1}`,
      '--user-agent',
      'CodeRED-Anime-Inspector/1.0 (+https://platform.codered.lat)',
      '--header',
      'Accept: text/html,application/xhtml+xml',
      '--output',
      htmlPath,
      url,
    ], {
      maxBuffer: maxBodyBytes,
      timeout: 30000,
    });

    const html = await readFile(htmlPath, 'utf8');
    await rm(tempDir, { recursive: true, force: true });

    return await writeHtmlEvidence(outputDir, html, { status: null, headers: new Map() }, maxBodyBytes);
  } catch (error) {
    throw error;
  }
}

async function writeHtmlEvidence(outputDir, html, response, maxBodyBytes) {
  const scriptsDir = path.join(outputDir, 'scripts');
  await mkdir(scriptsDir, { recursive: true });
  const redactedHtml = redactBody(html, maxBodyBytes * 4)?.text || '';
  const externalScripts = [...redactedHtml.matchAll(/<script[^>]+src=["']([^"']+)["'][^>]*>/gi)].map((match) => match[1]);
  const inlineScripts = [...redactedHtml.matchAll(/<script\b(?![^>]+src=)[^>]*>([\s\S]*?)<\/script>/gi)].map((match) => match[1].trim()).filter(Boolean);
  const frames = [...redactedHtml.matchAll(/<iframe[^>]+src=["']([^"']+)["'][^>]*>/gi)].map((match) => ({
    name: null,
    url: sanitizeUrl(match[1]),
  }));
  const players = [...redactedHtml.matchAll(/<iframe[^>]+class=["'][^"']*player[^"']*["'][^>]*src=["']([^"']+)["'][^>]*>/gi)].map((match) => ({
    selector: 'iframe[class*=player]',
    tag: 'iframe',
    id: null,
    className: null,
    text: '',
    src: sanitizeUrl(match[1]),
    href: null,
    dataServer: null,
    dataPlayer: null,
    dataset: {},
  }));
  const ajax = [...new Set([...redactedHtml.matchAll(/["'](\/ajax\/[^"']+)["']/gi)].map((match) => match[1]))];

  await writeFile(path.join(scriptsDir, 'external.json'), JSON.stringify(externalScripts, null, 2));
  await writeFile(path.join(scriptsDir, 'inline.json'), JSON.stringify(inlineScripts.map((script) => script.slice(0, 20000)), null, 2));

  return {
    status: response.status,
    contentType: response.headers.get('content-type'),
    externalScripts,
    inlineCount: inlineScripts.length,
    frames,
    players,
    ajax,
  };
}

async function collectPlayers(page) {
  return page.evaluate(() => {
    const selectors = ['iframe', '[data-server]', '[data-player]', '.server', '.servers', '.player', '#player'];

    return selectors.flatMap((selector) => Array.from(document.querySelectorAll(selector)).map((element) => ({
      selector,
      tag: element.tagName.toLowerCase(),
      id: element.id || null,
      className: element.className || null,
      text: (element.textContent || '').trim().slice(0, 500),
      src: element.getAttribute('src'),
      href: element.getAttribute('href'),
      dataServer: element.getAttribute('data-server'),
      dataPlayer: element.getAttribute('data-player'),
      dataset: { ...element.dataset },
    })));
  });
}

export async function inspectJkAnime(options) {
  const {
    slug,
    episode,
    baseUrl = 'https://jkanime.net',
    outputDir = path.resolve('output'),
    headless = true,
    timeoutMs = 45000,
    maxBodyBytes = 32768,
    includeMediaUrls = false,
    captureBodies = false,
    collectDom = false,
    collectStatic = false,
    staticOnly = false,
  } = options;

  const targetUrl = `${baseUrl.replace(/\/$/, '')}/${encodeURIComponent(slug)}/${Number(episode)}`;
  const startedAt = nowIso();
  const requests = [];
  const responses = [];
  const redirects = [];

  await mkdir(outputDir, { recursive: true });
  const emptyHtmlEvidence = {
    externalScripts: [],
    inlineCount: 0,
    frames: [],
    players: [],
    ajax: [],
  };
  const htmlEvidence = collectStatic ? await collectHtmlEvidence(targetUrl, outputDir, maxBodyBytes).catch((error) => ({
    error: error instanceof Error ? error.message : String(error),
    ...emptyHtmlEvidence,
  })) : emptyHtmlEvidence;

  if (collectStatic) {
    console.error('Static HTML evidence collected');
  }

  if (staticOnly) {
    const report = {
      startedAt,
      finishedAt: nowIso(),
      target: {
        slug,
        episode: Number(episode),
        requestedUrl: sanitizeUrl(targetUrl, { includeMediaUrls }),
        finalUrl: sanitizeUrl(targetUrl, { includeMediaUrls }),
        status: htmlEvidence.status,
        title: null,
      },
      totals: {
        requests: 0,
        responses: 0,
        redirects: 0,
        frames: htmlEvidence.frames.length,
        players: htmlEvidence.players.length,
        scripts: htmlEvidence.externalScripts.length + htmlEvidence.inlineCount,
        interestingRequests: 0,
        interestingResponses: 0,
      },
      observed: {
        ajax: [],
        players: [],
        mediaCandidates: [],
        staticAjax: htmlEvidence.ajax,
        frames: htmlEvidence.frames.map((frame) => frame.url),
      },
      staticEvidenceError: htmlEvidence.error || null,
      notes: [
        'Static-only mode skipped Playwright browser navigation.',
        'Headers and textual bodies are redacted before being written.',
      ],
    };

    await writeFile(path.join(outputDir, 'requests.json'), JSON.stringify([], null, 2));
    await writeFile(path.join(outputDir, 'responses.json'), JSON.stringify([], null, 2));
    await writeFile(path.join(outputDir, 'redirects.json'), JSON.stringify([], null, 2));
    await writeFile(path.join(outputDir, 'frames.json'), JSON.stringify(safeJson(htmlEvidence.frames), null, 2));
    await writeFile(path.join(outputDir, 'players.json'), JSON.stringify(safeJson(htmlEvidence.players), null, 2));
    await writeFile(path.join(outputDir, 'report.json'), JSON.stringify(safeJson(report), null, 2));

    return report;
  }

  console.error(`Inspecting ${targetUrl}`);
  const browser = await chromium.launch({ headless });

  try {
    const context = await browser.newContext({
      ignoreHTTPSErrors: false,
      javaScriptEnabled: true,
      userAgent: 'CodeRED-Anime-Inspector/1.0 (+https://platform.codered.lat)',
    });

    context.setDefaultTimeout(timeoutMs);
    const page = await context.newPage();

    page.on('request', (request) => {
      const url = request.url();
      const classification = classify(url, request.resourceType());
      const redirectedFrom = request.redirectedFrom();

      if (redirectedFrom) {
        redirects.push({
          from: sanitizeUrl(redirectedFrom.url(), { includeMediaUrls }),
          to: sanitizeUrl(url, { includeMediaUrls }),
        });
      }

      requests.push({
        at: nowIso(),
        url: sanitizeUrl(url, { includeMediaUrls }),
        method: request.method(),
        resourceType: request.resourceType(),
        headers: redactHeaders(request.headers()),
        postData: redactBody(request.postData(), maxBodyBytes),
        ...classification,
      });
    });

    page.on('response', (response) => {
      const request = response.request();
      const url = response.url();
      const headers = redactHeaders(response.headers());
      const contentType = headers['content-type'] || headers['Content-Type'] || '';

      const responseRecord = {
        at: nowIso(),
        url: sanitizeUrl(url, { includeMediaUrls }),
        method: request.method(),
        status: response.status(),
        statusText: response.statusText(),
        contentType,
        resourceType: request.resourceType(),
        headers,
        body: null,
        bodyCaptured: false,
        ...classify(url, request.resourceType()),
      };

      responses.push(responseRecord);

      if (captureBodies && responseRecord.isInteresting && TEXTUAL_CONTENT_PATTERN.test(contentType || '')) {
        void responseBody(response, contentType, maxBodyBytes).then((body) => {
          responseRecord.body = body;
          responseRecord.bodyCaptured = true;
        });
      }
    });

    const navigation = await withTimeout(
      page.goto(targetUrl, { waitUntil: 'commit', timeout: timeoutMs }),
      timeoutMs + 1000,
      'navigation',
    );
    console.error(`Navigation status: ${navigation?.status() ?? 'unknown'}`);
    if (collectDom) {
      await sleep(5000);
      console.error('Collecting DOM evidence');
    }

    const title = collectDom ? await page.title().catch(() => null) : null;
    const finalUrl = collectDom ? page.url() : targetUrl;
    const browserFrames = collectDom ? page.frames().map((frame) => ({
      name: frame.name() || null,
      url: sanitizeUrl(frame.url(), { includeMediaUrls }),
    })) : [{ name: null, url: sanitizeUrl(targetUrl, { includeMediaUrls }) }];
    const frames = browserFrames.concat(htmlEvidence.frames);
    const players = collectDom
      ? await withTimeout(collectPlayers(page), 10000, 'player collection').catch(() => [])
      : htmlEvidence.players;
    const scripts = collectDom
      ? await withTimeout(collectScripts(page, outputDir), 10000, 'script collection').catch(() => ({ external: [], inlineCount: 0 }))
      : { external: htmlEvidence.externalScripts, inlineCount: htmlEvidence.inlineCount };

    const interestingRequests = requests.filter((item) => item.isInteresting);
    const interestingResponses = responses.filter((item) => item.isInteresting);
    const report = {
      startedAt,
      finishedAt: nowIso(),
      target: {
        slug,
        episode: Number(episode),
        requestedUrl: sanitizeUrl(targetUrl, { includeMediaUrls }),
        finalUrl: sanitizeUrl(finalUrl, { includeMediaUrls }),
        status: navigation?.status() ?? null,
        title,
      },
      totals: {
        requests: requests.length,
        responses: responses.length,
        redirects: redirects.length,
        frames: frames.length,
        players: players.length,
        scripts: scripts.external.length + scripts.inlineCount,
        interestingRequests: interestingRequests.length,
        interestingResponses: interestingResponses.length,
      },
      observed: {
        ajax: [...new Set(interestingRequests.filter((item) => item.isAjax).map((item) => `${item.method} ${item.url}`))],
        players: [...new Set(interestingRequests.filter((item) => item.isPlayer).map((item) => `${item.method} ${item.url}`))],
        mediaCandidates: [...new Set(interestingRequests.filter((item) => item.isMedia).map((item) => `${item.method} ${item.url}`))],
        staticAjax: htmlEvidence.ajax,
        frames: frames.map((frame) => frame.url),
      },
      staticEvidenceError: htmlEvidence.error || null,
      notes: [
        'Headers and textual bodies are redacted before being written.',
        'Direct media URLs are redacted by default; rerun with --include-media-urls only when you have a lawful debugging need.',
        collectDom ? 'DOM evidence was collected.' : 'DOM evidence was skipped; rerun with --collect-dom for scripts and player selectors.',
      ],
    };

    await writeFile(path.join(outputDir, 'requests.json'), JSON.stringify(safeJson(requests), null, 2));
    await writeFile(path.join(outputDir, 'responses.json'), JSON.stringify(safeJson(responses), null, 2));
    await writeFile(path.join(outputDir, 'redirects.json'), JSON.stringify(safeJson(redirects), null, 2));
    await writeFile(path.join(outputDir, 'frames.json'), JSON.stringify(safeJson(frames), null, 2));
    await writeFile(path.join(outputDir, 'players.json'), JSON.stringify(safeJson(players), null, 2));
    await writeFile(path.join(outputDir, 'report.json'), JSON.stringify(safeJson(report), null, 2));

    console.error(`Report written to ${path.join(outputDir, 'report.json')}`);

    return report;
  } finally {
    void browser.close().catch(() => null);
  }
}
