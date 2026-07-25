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

function parseMaybeJson(value) {
  if (typeof value !== 'string') return value;
  const trimmed = value.trim();
  if (!trimmed || !['[', '{'].includes(trimmed[0])) return value;
  try { return JSON.parse(trimmed); } catch (_) { return value; }
}

function looksLikeAgency(row) {
  if (!row || typeof row !== 'object' || Array.isArray(row)) return false;
  const keys = Object.keys(row).map((key) => key.toLowerCase());
  const hasId = keys.some((key) => ['external_id', 'id', 'id_agencia', 'agencia_id', 'idagencia', 'idagencias'].includes(key));
  const hasName = keys.some((key) => ['name', 'nombre', 'agencia', 'nombre_agencia', 'nombreagencia'].includes(key));
  const hasLocation = keys.some((key) => ['direccion', 'address', 'departamento', 'department', 'latitud', 'latitude'].includes(key));
  return (hasId && hasName) || (hasName && hasLocation);
}

function findAgencyArrays(value, path = 'root', depth = 0, found = []) {
  if (depth > 10 || value === null || value === undefined) return found;
  value = parseMaybeJson(value);

  if (Array.isArray(value)) {
    const objects = value.filter((item) => item && typeof item === 'object' && !Array.isArray(item));
    if (objects.length > 0 && objects.some(looksLikeAgency)) found.push({ path, rows: objects });
    value.forEach((item, index) => findAgencyArrays(item, `${path}[${index}]`, depth + 1, found));
    return found;
  }

  if (typeof value === 'object') {
    for (const [key, child] of Object.entries(value)) {
      findAgencyArrays(child, `${path}.${key}`, depth + 1, found);
    }
  }
  return found;
}

function normalizeAgency(row) {
  const schedule = row.schedule || row.horario || row.horarios || {};
  const classification = row.classification || row.clasificacion || row.clasificación || {};
  return {
    external_id: Number(first(row, ['external_id', 'id', 'id_agencia', 'agencia_id', 'idAgencia', 'idagencia'])) || null,
    code: clean(first(row, ['code', 'codigo', 'código', 'cod_agencia', 'codAgencia'])),
    name: clean(first(row, ['name', 'nombre', 'agencia', 'nombre_agencia', 'nombreAgencia'])),
    place: clean(first(row, ['place', 'lugar', 'ubicacion', 'ubicación'])),
    zone: clean(first(row, ['zone', 'zona'])),
    department: clean(first(row, ['department', 'departamento', 'depa'])),
    province: clean(first(row, ['province', 'provincia', 'prov'])),
    district: clean(first(row, ['district', 'distrito', 'dist'])),
    address: clean(first(row, ['address', 'direccion', 'dirección', 'domicilio'])),
    latitude: first(row, ['latitude', 'latitud', 'lat', 'coordenada_latitud']),
    longitude: first(row, ['longitude', 'longitud', 'lng', 'lon', 'coordenada_longitud']),
    schedule: {
      general: clean(first(schedule, ['general', 'principal']) || first(row, ['schedule_general', 'horario_general', 'horario', 'horario_atencion'])),
      sunday: clean(first(schedule, ['sunday', 'domingo']) || first(row, ['schedule_sunday', 'horario_domingo', 'horario_domingos'])),
    },
    classification: {
      category: clean(first(classification, ['category', 'categoria']) || first(row, ['classification_category', 'categoria', 'tipo_agencia'])),
      sends_category: clean(first(classification, ['sends_category', 'envios']) || first(row, ['classification_sends_category', 'categoria_envio'])),
      receives_category: clean(first(classification, ['receives_category', 'recepciones']) || first(row, ['classification_receives_category', 'categoria_recepcion'])),
    },
  };
}

app.get('/health', (_req, res) => res.json({ ok: true, service: 'shalom-extractor' }));

app.post('/extract', async (req, res) => {
  if (typeof req.body?.chosenFileContent !== 'string' || !req.body.chosenFileContent.trim()) {
    return res.status(422).json({ error: 'chosenFileContent is required' });
  }

  let browser;
  try {
    console.log(`[extractor] Starting extraction at ${new Date().toISOString()}`);
    browser = await chromium.launch({ headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    const context = await browser.newContext({ locale: 'es-PE' });
    const page = await context.newPage();
    page.setDefaultTimeout(Number(process.env.SHALOM_PAGE_TIMEOUT_MS || 120000));

    const networkPayloads = [];
    page.on('response', async (response) => {
      const url = response.url().toLowerCase();
      if (!url.includes('agencias/listar')) return;
      try { networkPayloads.push(await response.json()); } catch (_) {}
    });

    await page.goto('https://shalom.com.pe/agencias/', { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.Service && typeof window.Service.sendPost === 'function');

    const result = await page.evaluate(async () => {
      let version = null;
      try { version = await window.Service.sendPost('agencias/version', {}); } catch (_) {}
      let agencies = null;
      try { agencies = await window.Service.sendPost('agencias/listar', {}); }
      catch (firstError) {
        try { agencies = await window.Service.sendPost('agencias/listar'); }
        catch (secondError) { throw secondError || firstError; }
      }
      return { version, agencies };
    });

    const candidates = [
      ...findAgencyArrays(result.agencies, 'service.agencies'),
      ...networkPayloads.flatMap((payload, index) => findAgencyArrays(payload, `network[${index}]`)),
    ].sort((a, b) => b.rows.length - a.rows.length);

    const selected = candidates[0] || { path: null, rows: [] };
    const agencies = selected.rows
      .map(normalizeAgency)
      .filter((row) => row.name && row.external_id)
      .filter((row, index, all) => all.findIndex((candidate) => candidate.external_id === row.external_id) === index);

    console.log(`[extractor] Finished: ${agencies.length} agencies; source=${selected.path || 'none'}`);
    return res.json({
      version: result.version,
      total: agencies.length,
      agencies,
      diagnostics: {
        source_path: selected.path,
        candidate_arrays: candidates.length,
        candidate_sizes: candidates.slice(0, 5).map((candidate) => ({ path: candidate.path, total: candidate.rows.length })),
        service_response_type: Array.isArray(result.agencies) ? 'array' : typeof result.agencies,
      },
    });
  } catch (error) {
    console.error(`[extractor] ${error.name}: ${error.message}`);
    return res.status(502).json({ error: 'Failed to extract agencies', detail: error.message });
  } finally {
    if (browser) await browser.close().catch(() => {});
  }
});

app.listen(port, '0.0.0.0', () => console.log(`Shalom extractor listening on ${port}`));
