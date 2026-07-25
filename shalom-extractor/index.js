'use strict';

const express = require('express');
const { chromium } = require('playwright-chromium');

const app = express();
const port = Number(process.env.PORT || 3000);
const maxMb = Number(process.env.SHALOM_EXTRACTOR_MAX_FILE_MB || 10);

app.disable('x-powered-by');
app.use(express.json({ limit: `${maxMb}mb` }));

const clean = (value) => {
  if (value === null || value === undefined) return null;
  const text = String(value)
    .replace(/&nbsp;|\u00a0/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&quot;/gi, '"')
    .replace(/&#39;|&apos;/gi, "'")
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
  return text || null;
};

const first = (row, keys) => {
  for (const key of keys) {
    if (row && row[key] !== undefined && row[key] !== null && row[key] !== '') return row[key];
  }
  return null;
};

function normalizeAgency(row) {
  const schedule = row.schedule || row.horario || {};
  const classification = row.classification || row.clasificacion || {};
  return {
    external_id: Number(first(row, ['external_id', 'id', 'id_agencia', 'agencia_id'])) || null,
    code: clean(first(row, ['code', 'codigo', 'cod_agencia'])),
    name: clean(first(row, ['name', 'nombre', 'agencia', 'nombre_agencia'])),
    place: clean(first(row, ['place', 'lugar'])),
    zone: clean(first(row, ['zone', 'zona'])),
    department: clean(first(row, ['department', 'departamento'])),
    province: clean(first(row, ['province', 'provincia'])),
    district: clean(first(row, ['district', 'distrito'])),
    address: clean(first(row, ['address', 'direccion'])),
    latitude: first(row, ['latitude', 'latitud', 'lat']),
    longitude: first(row, ['longitude', 'longitud', 'lng', 'lon']),
    schedule: {
      general: clean(first(schedule, ['general']) || first(row, ['schedule_general', 'horario_general', 'horario'])),
      sunday: clean(first(schedule, ['sunday', 'domingo']) || first(row, ['schedule_sunday', 'horario_domingo'])),
    },
    classification: {
      category: clean(first(classification, ['category', 'categoria']) || first(row, ['classification_category', 'categoria'])),
      sends_category: clean(first(classification, ['sends_category', 'envios']) || first(row, ['classification_sends_category'])),
      receives_category: clean(first(classification, ['receives_category', 'recepciones']) || first(row, ['classification_receives_category'])),
    },
  };
}

app.get('/health', (_req, res) => res.json({ ok: true }));

app.post('/extract', async (req, res) => {
  if (typeof req.body?.chosenFileContent !== 'string' || !req.body.chosenFileContent.trim()) {
    return res.status(422).json({ error: 'chosenFileContent is required' });
  }

  let browser;
  try {
    browser = await chromium.launch({ headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    const context = await browser.newContext({ locale: 'es-PE' });
    const page = await context.newPage();
    page.setDefaultTimeout(Number(process.env.SHALOM_PAGE_TIMEOUT_MS || 120000));

    await page.goto('https://shalom.com.pe/agencias/', { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.Service && typeof window.Service.sendPost === 'function');

    const result = await page.evaluate(async () => {
      let version = null;
      try { version = await window.Service.sendPost('agencias/version'); } catch (_) {}
      const agencies = await window.Service.sendPost('agencias/listar');
      return { version, agencies };
    });

    const raw = Array.isArray(result.agencies)
      ? result.agencies
      : (result.agencies?.data || result.agencies?.resultado || result.agencies?.agencias || []);

    const agencies = raw.map(normalizeAgency).filter((row) => row.name && row.external_id);
    return res.json({ version: result.version, total: agencies.length, agencies });
  } catch (error) {
    console.error(`[extractor] ${error.name}: ${error.message}`);
    return res.status(502).json({ error: 'Failed to extract agencies', detail: error.message });
  } finally {
    if (browser) await browser.close().catch(() => {});
  }
});

app.listen(port, '0.0.0.0', () => console.log(`Shalom extractor listening on ${port}`));
