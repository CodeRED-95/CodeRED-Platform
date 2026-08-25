// Empaqueta la extension como .crx v3 firmado y genera el updates.xml y la
// política de registro para forzar su instalación en Chrome SIN pasar por la
// Chrome Web Store y SIN modo desarrollador (cargar sin empaquetar).
//
// La firma se hace con una clave RSA propia y ESTABLE (packaging/shalom-recordar.pem):
// el id de la extensión se deriva de esa clave, así que mientras no cambie la
// clave, el id no cambia y las actualizaciones se reconocen como la misma
// extensión. NO subas esa clave a git (packaging/ está en .gitignore).
//
// Uso:  npm run build && node scripts/pack-crx.mjs
//   o:  npm run pack:crx   (ejecuta el build antes)

import { createHash, createSign, generateKeyPairSync, createPublicKey } from 'node:crypto';
import { readFileSync, writeFileSync, mkdirSync, existsSync, readdirSync, statSync } from 'node:fs';
import { join, relative, sep, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { deflateRawSync, crc32 } from 'node:zlib';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = join(HERE, '..');
const DIST = join(ROOT, 'dist');
const PACKAGING = join(ROOT, 'packaging');
const KEY_PATH = join(PACKAGING, 'shalom-recordar.pem');

// Dónde se servirán los archivos. La extensión se descarga y actualiza desde aquí.
const BASE_URL = process.env.CRX_BASE_URL || 'https://platform.codered.lat/ext/shalom-recordar';

const packageJson = JSON.parse(readFileSync(join(ROOT, 'package.json'), 'utf8'));
const version = packageJson.version;

if (!existsSync(join(DIST, 'manifest.json'))) {
    throw new Error('No existe dist/manifest.json. Ejecuta primero: npm run build');
}

// --- 1. Clave privada estable ------------------------------------------------
mkdirSync(PACKAGING, { recursive: true });

let privateKey;
if (existsSync(KEY_PATH)) {
    privateKey = readFileSync(KEY_PATH, 'utf8');
} else {
    const generated = generateKeyPairSync('rsa', { modulusLength: 2048 });
    privateKey = generated.privateKey.export({ type: 'pkcs8', format: 'pem' });
    writeFileSync(KEY_PATH, privateKey, { mode: 0o600 });
    console.log('Clave nueva generada en packaging/shalom-recordar.pem — guárdala; de ella depende el id de la extensión.');
}

const publicDer = createPublicKey(privateKey).export({ type: 'spki', format: 'der' });

// id de la extensión = primeros 16 bytes del sha256 de la clave pública,
// cada nibble mapeado de 0-15 a 'a'-'p' (formato "mpdecimal" de Chrome).
const idDigest = createHash('sha256').update(publicDer).digest();
const extensionId = [...idDigest.subarray(0, 16)]
    .map((byte) => String.fromCharCode(97 + (byte >> 4)) + String.fromCharCode(97 + (byte & 0x0f)))
    .join('');
const crxId = idDigest.subarray(0, 16);

// --- 2. Fijar update_url en el manifest de dist ------------------------------
const manifestPath = join(DIST, 'manifest.json');
const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
manifest.update_url = `${BASE_URL}/updates.xml`;
writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`);

// --- 3. Comprimir dist en un ZIP (sin dependencias externas) -----------------
function listFiles(dir) {
    const out = [];
    for (const entry of readdirSync(dir)) {
        const full = join(dir, entry);
        if (statSync(full).isDirectory()) out.push(...listFiles(full));
        else out.push(full);
    }
    return out;
}

function buildZip(dir) {
    const files = listFiles(dir).sort();
    const locals = [];
    const centrals = [];
    let offset = 0;

    for (const file of files) {
        const name = relative(dir, file).split(sep).join('/');
        const data = readFileSync(file);
        const nameBuf = Buffer.from(name, 'utf8');
        const crc = crc32(data) >>> 0;
        const compressed = deflateRawSync(data);
        const useDeflate = compressed.length < data.length;
        const body = useDeflate ? compressed : data;
        const method = useDeflate ? 8 : 0;

        const local = Buffer.alloc(30);
        local.writeUInt32LE(0x04034b50, 0);
        local.writeUInt16LE(20, 4);
        local.writeUInt16LE(0, 6);
        local.writeUInt16LE(method, 8);
        local.writeUInt16LE(0, 10); // hora
        local.writeUInt16LE(0x21, 12); // fecha fija (1980-01-01) para builds reproducibles
        local.writeUInt32LE(crc, 14);
        local.writeUInt32LE(body.length, 18);
        local.writeUInt32LE(data.length, 22);
        local.writeUInt16LE(nameBuf.length, 26);
        local.writeUInt16LE(0, 28);
        locals.push(local, nameBuf, body);

        const central = Buffer.alloc(46);
        central.writeUInt32LE(0x02014b50, 0);
        central.writeUInt16LE(20, 4);
        central.writeUInt16LE(20, 6);
        central.writeUInt16LE(0, 8);
        central.writeUInt16LE(method, 10);
        central.writeUInt16LE(0, 12);
        central.writeUInt16LE(0x21, 14);
        central.writeUInt32LE(crc, 16);
        central.writeUInt32LE(body.length, 20);
        central.writeUInt32LE(data.length, 24);
        central.writeUInt16LE(nameBuf.length, 28);
        central.writeUInt32LE(offset, 42);
        centrals.push(central, nameBuf);

        offset += local.length + nameBuf.length + body.length;
    }

    const centralBuf = Buffer.concat(centrals);
    const localBuf = Buffer.concat(locals);
    const end = Buffer.alloc(22);
    end.writeUInt32LE(0x06054b50, 0);
    end.writeUInt16LE(files.length, 8);
    end.writeUInt16LE(files.length, 10);
    end.writeUInt32LE(centralBuf.length, 12);
    end.writeUInt32LE(localBuf.length, 16);

    return Buffer.concat([localBuf, centralBuf, end]);
}

const zip = buildZip(DIST);

// --- 4. Cabecera CRX3 firmada ------------------------------------------------
// protobuf minimal: solo campos length-delimited (wire type 2).
function varint(value) {
    const bytes = [];
    let v = value;
    while (v > 0x7f) {
        bytes.push((v & 0x7f) | 0x80);
        v >>>= 7;
    }
    bytes.push(v);
    return Buffer.from(bytes);
}

function field(fieldNumber, payload) {
    const tag = varint((fieldNumber << 3) | 2);
    return Buffer.concat([tag, varint(payload.length), payload]);
}

// SignedData { crx_id (field 1) = 16 bytes }
const signedHeaderData = field(1, crxId);

// Firma sobre: "CRX3 SignedData\0" + len(signedHeaderData) LE + signedHeaderData + zip
const context = Buffer.from('CRX3 SignedData\x00', 'binary');
const lenLE = Buffer.alloc(4);
lenLE.writeUInt32LE(signedHeaderData.length, 0);
const signer = createSign('RSA-SHA256');
signer.update(Buffer.concat([context, lenLE, signedHeaderData, zip]));
const signature = signer.sign(privateKey);

// AsymmetricKeyProof { public_key (1), signature (2) }
const proof = Buffer.concat([field(1, publicDer), field(2, signature)]);
// CrxFileHeader { sha256_with_rsa (2) = proof, signed_header_data (10000) }
const header = Buffer.concat([field(2, proof), field(10000, signedHeaderData)]);

const magic = Buffer.from('Cr24', 'binary');
const versionBuf = Buffer.alloc(4);
versionBuf.writeUInt32LE(3, 0);
const headerLenBuf = Buffer.alloc(4);
headerLenBuf.writeUInt32LE(header.length, 0);

const crx = Buffer.concat([magic, versionBuf, headerLenBuf, header, zip]);

// --- 5. Escribir .crx, updates.xml y la política de registro -----------------
const releaseDir = join(ROOT, 'release');
mkdirSync(releaseDir, { recursive: true });
const crxName = `shalom-recordar-extension-${version}.crx`;
writeFileSync(join(releaseDir, crxName), crx);

const updatesXml = `<?xml version="1.0" encoding="UTF-8"?>
<gupdate xmlns="http://www.google.com/update2/response" protocol="2.0">
  <app appid="${extensionId}">
    <updatecheck codebase="${BASE_URL}/${crxName}" version="${version}" />
  </app>
</gupdate>
`;
writeFileSync(join(releaseDir, 'updates.xml'), updatesXml);

const reg = `Windows Registry Editor Version 5.00

; Fuerza la instalacion de "Registro de Actividad Shalom" en Chrome.
; Se ejecuta UNA VEZ por equipo, como administrador. La extension queda
; instalada, activa y fija: el usuario no puede desactivarla ni quitarla.
; No requiere modo desarrollador ni la Chrome Web Store.

[HKEY_LOCAL_MACHINE\\SOFTWARE\\Policies\\Google\\Chrome\\ExtensionInstallForcelist]
"1"="${extensionId};${BASE_URL}/updates.xml"
`;
writeFileSync(join(releaseDir, 'force-install-shalom-recordar.reg'), reg);

// --- 6. Publicar en public/ext de la Plataforma (si el repo está al lado) -----
const platformPublic = join(ROOT, '..', '..', 'public', 'ext', 'shalom-recordar');
if (existsSync(join(ROOT, '..', '..', 'public'))) {
    mkdirSync(platformPublic, { recursive: true });
    writeFileSync(join(platformPublic, crxName), crx);
    writeFileSync(join(platformPublic, 'updates.xml'), updatesXml);
    console.log('Publicado en public/ext/shalom-recordar/ (' + crxName + ' + updates.xml)');
}

console.log('');
console.log('Extension id : ' + extensionId);
console.log('CRX          : release/' + crxName);
console.log('Update manif : release/updates.xml');
console.log('Registro     : release/force-install-shalom-recordar.reg');
console.log('Base URL      : ' + BASE_URL);
console.log('');
console.log('Sube a ' + BASE_URL + '/ estos dos archivos: ' + crxName + ' y updates.xml');
