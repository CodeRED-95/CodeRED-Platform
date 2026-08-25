'use strict';

const express = require('express');
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright-chromium');

const app = express();
const port = Number(process.env.PORT || 3000);
const maxMb = Number(process.env.SHALOM_EXTRACTOR_MAX_FILE_MB || 10);
const debugEnabled = String(process.env.SHALOM_EXTRACTOR_DEBUG || 'false').toLowerCase() === 'true';
const logFullRecords = String(process.env.SHALOM_EXTRACTOR_LOG_FULL_RECORDS || 'false').toLowerCase() === 'true';
const sampleSize = Math.max(0, Number.parseInt(process.env.SHALOM_EXTRACTOR_LOG_SAMPLE_SIZE || '3', 10) || 3);
const diagnosticsDir = '/app/logs/shalom';

app.disable('x-powered-by');
app.use(express.json({ limit: `${maxMb}mb` }));

let lastExtractionDiagnostics = null;

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

const isPlainObject = (value) => Boolean(value) && typeof value === 'object' && !Array.isArray(value);

function parseMaybeJson(value) {
  if (typeof value !== 'string') return value;
  const trimmed = value.trim();
  if (!trimmed || !['[', '{'].includes(trimmed[0])) return value;
  try { return JSON.parse(trimmed); } catch (_) { return value; }
}

function looksLikeAgency(row) {
  if (!row || typeof row !== 'object' || Array.isArray(row)) return false;
  const keys = Object.keys(row).map((key) => key.toLowerCase());
  const hasId = keys.some((key) => ['external_id', 'id', 'id_agencia', 'agencia_id', 'idagencia', 'idagencias', 'ter_id'].includes(key));
  const hasName = keys.some((key) => ['name', 'nombre', 'agencia', 'nombre_agencia', 'nombreagencia', 'lugar_over'].includes(key));
  const hasLocation = keys.some((key) => ['direccion', 'address', 'departamento', 'department', 'latitud', 'latitude'].includes(key));
  return (hasId && hasName) || (hasName && hasLocation);
}

function findAgencyArrays(value, pathName = 'root', depth = 0, found = []) {
  if (depth > 10 || value === null || value === undefined) return found;
  value = parseMaybeJson(value);

  if (Array.isArray(value)) {
    const objects = value.filter((item) => item && typeof item === 'object' && !Array.isArray(item));
    if (objects.length > 0 && objects.some(looksLikeAgency)) found.push({ path: pathName, rows: objects });
    value.forEach((item, index) => findAgencyArrays(item, `${pathName}[${index}]`, depth + 1, found));
    return found;
  }

  if (typeof value === 'object') {
    for (const [key, child] of Object.entries(value)) {
      findAgencyArrays(child, `${pathName}.${key}`, depth + 1, found);
    }
  }
  return found;
}

function hasOwnPath(object, pathName) {
  const parts = pathName.split('.');
  let current = object;

  for (const part of parts) {
    if (current === null || typeof current !== 'object' || !Object.prototype.hasOwnProperty.call(current, part)) {
      return false;
    }
    current = current[part];
  }

  return true;
}

function getPath(object, pathName) {
  return pathName.split('.').reduce(
    (current, part) => (current !== null && typeof current === 'object' ? current[part] : undefined),
    object
  );
}

function isEmptyValue(value) {
  if (value === null || value === undefined) return true;
  if (typeof value === 'string') return value.trim() === '';
  if (Array.isArray(value)) return value.length === 0;
  return false;
}

function collectKeys(value, target = new Set()) {
  const parsed = parseMaybeJson(value);
  if (!parsed || typeof parsed !== 'object') return target;
  for (const key of Object.keys(parsed)) {
    target.add(key);
  }
  return target;
}

function sanitizeRecord(record) {
  if (!isPlainObject(record)) return record;
  const clone = JSON.parse(JSON.stringify(record));
  for (const key of ['authorization', 'Authorization', 'cookie', 'Cookie', 'tokens', 'token', 'session', 'password']) {
    if (Object.prototype.hasOwnProperty.call(clone, key)) {
      clone[key] = '[redacted]';
    }
  }
  return clone;
}

function buildCompletenessSummary(records) {
  const fields = [
    'external_id',
    'code',
    'name',
    'place',
    'zone',
    'department',
    'province',
    'address',
    'latitude',
    'longitude',
    'schedule.general',
    'schedule.sunday',
    'classification.category',
    'classification.sends_category',
    'classification.receives_category',
    'geographic_ids.ubigeo_id',
    'source_record.ubi_id',
  ];

  const summary = { total: records.length, fields: {} };
  for (const field of fields) {
    let present = 0;
    let empty = 0;
    let missing = 0;

    for (const record of records) {
      if (!hasOwnPath(record, field)) {
        missing++;
        continue;
      }
      const value = getPath(record, field);
      if (isEmptyValue(value)) {
        empty++;
      } else {
        present++;
      }
    }

    summary.fields[field] = {
      present,
      empty,
      missing,
      coverage: `${records.length > 0 ? ((present / records.length) * 100).toFixed(2) : '0.00'}%`,
    };
  }

  return summary;
}

function buildTransformLoss(rawRecords, normalizedRecords) {
  const fields = [
    'external_id',
    'code',
    'name',
    'place',
    'zone',
    'department',
    'province',
    'address',
    'latitude',
    'longitude',
    'schedule.general',
    'schedule.sunday',
    'classification.category',
    'classification.sends_category',
    'classification.receives_category',
    'geographic_ids.ubigeo_id',
    'source_record.ubi_id',
  ];

  const result = {};
  for (const field of fields) {
    let rawPresent = 0;
    let normalizedPresent = 0;
    const total = Math.max(rawRecords.length, normalizedRecords.length);

    for (let i = 0; i < total; i += 1) {
      if (i < rawRecords.length && hasOwnPath(rawRecords[i], field) && !isEmptyValue(getPath(rawRecords[i], field))) {
        rawPresent++;
      }
      if (i < normalizedRecords.length && hasOwnPath(normalizedRecords[i], field) && !isEmptyValue(getPath(normalizedRecords[i], field))) {
        normalizedPresent++;
      }
    }

    result[field] = {
      rawPresent,
      normalizedPresent,
      lostDuringTransform: Math.max(0, rawPresent - normalizedPresent),
    };
  }
  return result;
}

function ensureDiagnosticsDir() {
  try {
    fs.mkdirSync(diagnosticsDir, { recursive: true });
  } catch (_) {}
}

function writeJsonAtomic(fileName, data) {
  ensureDiagnosticsDir();
  const finalPath = path.join(diagnosticsDir, fileName);
  const tempPath = `${finalPath}.tmp`;
  fs.writeFileSync(tempPath, JSON.stringify(data, null, 2));
  fs.renameSync(tempPath, finalPath);
}

function logJson(tag, payload) {
  console.log(tag, JSON.stringify(payload, null, 2));
}

function redactIfNeeded(record) {
  return sanitizeRecord(record);
}

function summarizeKeys(records) {
  const rootRecordKeys = new Set();
  const nestedKeys = {
    schedule: new Set(),
    classification: new Set(),
    geographic_ids: new Set(),
    source_record: new Set(),
  };

  for (const record of records) {
    if (!isPlainObject(record)) continue;
    for (const key of Object.keys(record)) rootRecordKeys.add(key);
    if (isPlainObject(record.schedule)) collectKeys(record.schedule, nestedKeys.schedule);
    if (isPlainObject(record.classification)) collectKeys(record.classification, nestedKeys.classification);
    if (isPlainObject(record.geographic_ids)) collectKeys(record.geographic_ids, nestedKeys.geographic_ids);
    if (isPlainObject(record.source_record)) collectKeys(record.source_record, nestedKeys.source_record);
  }

  return {
    rootRecordKeys: Array.from(rootRecordKeys).sort(),
    nestedKeys: {
      schedule: Array.from(nestedKeys.schedule).sort(),
      classification: Array.from(nestedKeys.classification).sort(),
      geographic_ids: Array.from(nestedKeys.geographic_ids).sort(),
      source_record: Array.from(nestedKeys.source_record).sort(),
    },
  };
}

function normalizeAgency(row) {
  const schedule = row.schedule || row.horario || row.horarios || {};
  const classification = row.classification || row.clasificacion || row.clasificación || {};
  const geographicIds = row.geographic_ids || row.geographicIds || {};
  const sourceRecord = row.source_record || row.sourceRecord || {};
  return {
    external_id: Number(first(row, ['external_id', 'id', 'id_agencia', 'agencia_id', 'idAgencia', 'idagencia', 'ter_id'])) || null,
    code: clean(first(row, ['code', 'codigo', 'código', 'cod_agencia', 'codAgencia', 'ter_abrebiatura', 'ter_abreviatura'])),
    name: clean(first(row, ['name', 'lugar_over', 'nombre', 'agencia', 'nombre_agencia', 'nombreAgencia'])),
    place: clean(first(row, ['place', 'nombre', 'lugar_over'])),
    zone: clean(first(row, ['zone', 'zona', 'distrito'])),
    department: clean(first(row, ['department', 'departamento', 'depa'])),
    province: clean(first(row, ['province', 'provincia', 'prov'])),
    address: clean(first(row, ['address', 'direccion', 'dirección', 'domicilio'])),
    latitude: first(row, ['latitude', 'latitud', 'lat', 'coordenada_latitud']),
    longitude: first(row, ['longitude', 'longitud', 'lng', 'lon', 'coordenada_longitud']),
    schedule: {
      general: clean(first(schedule, ['general', 'principal']) || first(row, ['schedule_general', 'horario_general', 'horario', 'horario_atencion', 'hora_atencion'])),
      sunday: clean(first(schedule, ['sunday', 'domingo']) || first(row, ['schedule_sunday', 'horario_domingo', 'horario_domingos', 'hora_domingo'])),
    },
    classification: {
      category: clean(first(classification, ['category', 'categoria']) || first(row, ['classification_category', 'categoria', 'tipo_agencia', 'ter_categoria'])),
      sends_category: clean(first(classification, ['sends_category', 'envios']) || first(row, ['classification_sends_category', 'categoria_envio', 'ter_categoria_envia'])),
      receives_category: clean(first(classification, ['receives_category', 'recepciones']) || first(row, ['classification_receives_category', 'categoria_recepcion', 'ter_categoria_recibe'])),
    },
    geographic_ids: {
      department_id: first(geographicIds, ['department_id', 'departamento_id']),
      province_id: first(geographicIds, ['province_id', 'provincia_id']),
      district_id: first(geographicIds, ['district_id', 'distrito_id']),
      ubigeo_id: first(geographicIds, ['ubigeo_id']) || first(row, ['ubigeo_id']),
    },
    source_record: {
      zona: first(sourceRecord, ['zona']),
      latitud: first(sourceRecord, ['latitud']),
      longitud: first(sourceRecord, ['longitud']),
      hora_atencion: first(sourceRecord, ['hora_atencion']),
      hora_domingo: first(sourceRecord, ['hora_domingo']),
      ter_categoria: first(sourceRecord, ['ter_categoria']),
      ter_categoria_envia: first(sourceRecord, ['ter_categoria_envia']),
      ter_categoria_recibe: first(sourceRecord, ['ter_categoria_recibe']),
      ubi_id: first(sourceRecord, ['ubi_id']),
    },
  };
}

function logDiagnostics({ rawPayload, selectedPath, rawRecords, transformedRecords }) {
  const coverage = buildCompletenessSummary(rawRecords);
  const transformLoss = buildTransformLoss(rawRecords, transformedRecords);
  const discoveredKeys = summarizeKeys(rawRecords);
  const sampleRecords = rawRecords.slice(0, sampleSize).map(redactIfNeeded);
  const normalizedSamples = transformedRecords.slice(0, sampleSize).map(redactIfNeeded);
  const missingByField = {
    missingUbigeo: [],
    missingZone: [],
    missingLatitude: [],
    missingLongitude: [],
    missingGeneralSchedule: [],
    missingSundaySchedule: [],
    missingCategory: [],
    missingSendsCategory: [],
    missingReceivesCategory: [],
  };

  rawRecords.forEach((record, index) => {
    const base = {
      index,
      external_id: getPath(record, 'external_id') ?? getPath(record, 'source_record.ter_id') ?? null,
      code: getPath(record, 'code') ?? getPath(record, 'source_record.ter_abrebiatura') ?? null,
      name: getPath(record, 'name') ?? getPath(record, 'source_record.lugar_over') ?? null,
      geographic_ids: getPath(record, 'geographic_ids') ?? null,
      source_ubi_id: getPath(record, 'source_record.ubi_id') ?? null,
    };

    if (isEmptyValue(getPath(record, 'geographic_ids.ubigeo_id')) && isEmptyValue(getPath(record, 'source_record.ubi_id')) && isEmptyValue(getPath(record, 'ubigeo_id'))) {
      missingByField.missingUbigeo.push(base);
    }
    if (isEmptyValue(getPath(record, 'zone')) && isEmptyValue(getPath(record, 'source_record.zona'))) {
      missingByField.missingZone.push(base);
    }
    if (isEmptyValue(getPath(record, 'latitude')) && isEmptyValue(getPath(record, 'source_record.latitud'))) {
      missingByField.missingLatitude.push(base);
    }
    if (isEmptyValue(getPath(record, 'longitude')) && isEmptyValue(getPath(record, 'source_record.longitud'))) {
      missingByField.missingLongitude.push(base);
    }
    if (isEmptyValue(getPath(record, 'schedule.general')) && isEmptyValue(getPath(record, 'source_record.hora_atencion'))) {
      missingByField.missingGeneralSchedule.push(base);
    }
    if (isEmptyValue(getPath(record, 'schedule.sunday')) && isEmptyValue(getPath(record, 'source_record.hora_domingo'))) {
      missingByField.missingSundaySchedule.push(base);
    }
    if (isEmptyValue(getPath(record, 'classification.category')) && isEmptyValue(getPath(record, 'source_record.ter_categoria'))) {
      missingByField.missingCategory.push(base);
    }
    if (isEmptyValue(getPath(record, 'classification.sends_category')) && isEmptyValue(getPath(record, 'source_record.ter_categoria_envia'))) {
      missingByField.missingSendsCategory.push(base);
    }
    if (isEmptyValue(getPath(record, 'classification.receives_category')) && isEmptyValue(getPath(record, 'source_record.ter_categoria_recibe'))) {
      missingByField.missingReceivesCategory.push(base);
    }
  });

  const summary = {
    timestamp: new Date().toISOString(),
    total: rawRecords.length,
    selectedPath,
    rawSummary: {
      rootType: Array.isArray(rawPayload) ? 'array' : typeof rawPayload,
      rootKeys: isPlainObject(rawPayload) ? Object.keys(rawPayload) : [],
      totalRecords: rawRecords.length,
      firstRecordKeys: rawRecords[0] && typeof rawRecords[0] === 'object' && !Array.isArray(rawRecords[0])
        ? Object.keys(rawRecords[0])
        : [],
      firstSourceRecordKeys: getPath(rawRecords[0], 'source_record') && typeof getPath(rawRecords[0], 'source_record') === 'object'
        ? Object.keys(getPath(rawRecords[0], 'source_record'))
        : [],
      approximatePayloadBytes: Buffer.byteLength(JSON.stringify(rawPayload || {}), 'utf8'),
    },
    discoveredKeys,
    coverage,
    transformLoss,
    rawSamples: sampleRecords,
    normalizedSamples,
    missingSamples: {
      missingUbigeo: missingByField.missingUbigeo.slice(0, sampleSize),
      missingZone: missingByField.missingZone.slice(0, sampleSize),
      missingLatitude: missingByField.missingLatitude.slice(0, sampleSize),
      missingLongitude: missingByField.missingLongitude.slice(0, sampleSize),
      missingGeneralSchedule: missingByField.missingGeneralSchedule.slice(0, sampleSize),
      missingSundaySchedule: missingByField.missingSundaySchedule.slice(0, sampleSize),
      missingCategory: missingByField.missingCategory.slice(0, sampleSize),
      missingSendsCategory: missingByField.missingSendsCategory.slice(0, sampleSize),
      missingReceivesCategory: missingByField.missingReceivesCategory.slice(0, sampleSize),
    },
  };

  console.log('[extractor][raw-summary]', JSON.stringify(summary.rawSummary, null, 2));
  console.log('[extractor][discovered-keys]', JSON.stringify(summary.discoveredKeys, null, 2));
  console.log('[extractor][coverage]', JSON.stringify(summary.coverage, null, 2));
  console.log('[extractor][transform-loss]', JSON.stringify(summary.transformLoss, null, 2));
  if (debugEnabled) {
    console.log('[extractor][raw-samples]', JSON.stringify(summary.rawSamples, null, 2));
    console.log('[extractor][normalized-samples]', JSON.stringify(summary.normalizedSamples, null, 2));
    console.log('[extractor][missing-fields-sample]', JSON.stringify(summary.missingSamples, null, 2));
  }

  writeJsonAtomic('coverage.json', summary.coverage);
  writeJsonAtomic('transform-loss.json', summary.transformLoss);
  writeJsonAtomic('missing-fields-sample.json', summary.missingSamples);
  if (debugEnabled) {
    writeJsonAtomic('raw-sample.json', summary.rawSamples);
    writeJsonAtomic('normalized-sample.json', summary.normalizedSamples);
    if (logFullRecords) {
      writeJsonAtomic('raw-full.json', rawRecords.map(redactIfNeeded));
      writeJsonAtomic('normalized-full.json', transformedRecords.map(redactIfNeeded));
    }
  }

  lastExtractionDiagnostics = summary;
  return summary;
}

app.get('/health', (_req, res) => res.json({ ok: true, service: 'shalom-extractor' }));

if (debugEnabled) {
  app.get('/debug/last-extraction', (_req, res) => {
    if (!lastExtractionDiagnostics) return res.status(404).json({ error: 'No extraction diagnostics available yet' });
    return res.json(lastExtractionDiagnostics);
  });
}

app.post('/extract', async (req, res) => {
  // `chosenFileContent` es opcional y, de hecho, no se usa: las agencias se
  // obtienen navegando shalom.com.pe. Se acepta por compatibilidad con quien
  // lo siga enviando, pero exigirlo obligaba a subir un archivo intrascendente
  // para poder sincronizar.

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

    const directRows = result.agencies?.success === true && Array.isArray(result.agencies?.data)
      ? [{ path: 'service.agencies.data', rows: result.agencies.data }]
      : [];

    const candidates = [
      ...directRows,
      ...findAgencyArrays(result.agencies, 'service.agencies'),
      ...networkPayloads.flatMap((payload, index) => findAgencyArrays(payload, `network[${index}]`)),
    ].sort((a, b) => b.rows.length - a.rows.length);

    const selected = candidates[0] || { path: null, rows: [] };
    const rawRecords = selected.rows.filter((row) => row && typeof row === 'object' && !Array.isArray(row));
    const agencies = rawRecords
      .map(normalizeAgency)
      .filter((row) => row.name && row.external_id)
      .filter((row, index, all) => all.findIndex((candidate) => candidate.external_id === row.external_id) === index);

    const diagnostics = logDiagnostics({
      rawPayload: result.agencies,
      selectedPath: selected.path || 'none',
      rawRecords,
      transformedRecords: agencies,
    });

    console.log(`[extractor] Finished: ${agencies.length} agencies`);
    console.log('[extractor] Finished', JSON.stringify({
      total: agencies.length,
      source: selected.path || 'none',
      withUbigeo: diagnostics.coverage.fields['geographic_ids.ubigeo_id'].present,
      withoutUbigeo: diagnostics.coverage.fields['geographic_ids.ubigeo_id'].missing + diagnostics.coverage.fields['geographic_ids.ubigeo_id'].empty,
      withDistrictSource: rawRecords.filter((record) => !isEmptyValue(getPath(record, 'zone')) || !isEmptyValue(getPath(record, 'source_record.zona'))).length,
      withoutDistrictSource: rawRecords.filter((record) => isEmptyValue(getPath(record, 'zone')) && isEmptyValue(getPath(record, 'source_record.zona'))).length,
      withCoordinates: rawRecords.filter((record) => !isEmptyValue(getPath(record, 'latitude')) || !isEmptyValue(getPath(record, 'source_record.latitud'))).length,
      withoutCoordinates: rawRecords.filter((record) => isEmptyValue(getPath(record, 'latitude')) && isEmptyValue(getPath(record, 'source_record.latitud'))).length,
      withGeneralSchedule: rawRecords.filter((record) => !isEmptyValue(getPath(record, 'schedule.general')) || !isEmptyValue(getPath(record, 'source_record.hora_atencion'))).length,
      withSundaySchedule: rawRecords.filter((record) => !isEmptyValue(getPath(record, 'schedule.sunday')) || !isEmptyValue(getPath(record, 'source_record.hora_domingo'))).length,
      withCategory: rawRecords.filter((record) => !isEmptyValue(getPath(record, 'classification.category')) || !isEmptyValue(getPath(record, 'source_record.ter_categoria'))).length,
      withSendsCategory: rawRecords.filter((record) => !isEmptyValue(getPath(record, 'classification.sends_category')) || !isEmptyValue(getPath(record, 'source_record.ter_categoria_envia'))).length,
      withReceivesCategory: rawRecords.filter((record) => !isEmptyValue(getPath(record, 'classification.receives_category')) || !isEmptyValue(getPath(record, 'source_record.ter_categoria_recibe'))).length,
    }));

    return res.json({
      version: result.version,
      total: agencies.length,
      agencies,
      diagnostics: {
        source_path: selected.path,
        candidate_arrays: candidates.length,
        candidate_sizes: candidates.slice(0, 5).map((candidate) => ({ path: candidate.path, total: candidate.rows.length })),
        service_response_type: Array.isArray(result.agencies) ? 'array' : typeof result.agencies,
        debug: debugEnabled,
        log_full_records: logFullRecords,
        coverage: diagnostics.coverage,
        transform_loss: diagnostics.transformLoss,
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
