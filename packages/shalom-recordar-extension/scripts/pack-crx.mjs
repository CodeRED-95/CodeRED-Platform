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
import { readFileSync, writeFileSync, copyFileSync, mkdirSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildZip } from './zip.mjs';

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

const updateUrl = `${BASE_URL}/updates.xml`;
const forceValue = `${extensionId};${updateUrl}`;

// Instalador interactivo: detecta los navegadores instalados y deja elegir.
// Es lo que el usuario ejecuta (doble clic en el .cmd); no hace falta tocar
// el registro a mano.
const templatesDir = join(HERE, 'templates');
const installerPs1 = readFileSync(join(templatesDir, 'instalar.ps1'), 'utf8')
    .replaceAll('__EXTENSION_ID__', extensionId)
    .replaceAll('__UPDATE_URL__', updateUrl);
writeFileSync(join(releaseDir, 'instalar.ps1'), installerPs1);
copyFileSync(join(templatesDir, 'Instalar-Shalom-Recordar.cmd'), join(releaseDir, 'Instalar-Shalom-Recordar.cmd'));

// Respaldo manual: un .reg por navegador, por si se prefiere aplicar la
// politica sin el instalador. El .crx y el id son identicos en todos los
// navegadores Chromium; solo cambia la rama del registro.
const REG_TARGETS = [
    { name: 'chrome', label: 'Google Chrome', keyPath: 'Google\\\\Chrome' },
    { name: 'edge', label: 'Microsoft Edge', keyPath: 'Microsoft\\\\Edge' },
    { name: 'brave', label: 'Brave', keyPath: 'BraveSoftware\\\\Brave' },
    { name: 'opera', label: 'Opera', keyPath: 'Opera Software\\\\Opera' },
    { name: 'vivaldi', label: 'Vivaldi', keyPath: 'Vivaldi' },
];
for (const target of REG_TARGETS) {
    const reg = `Windows Registry Editor Version 5.00

; Fuerza la instalacion de "Registro de Actividad Shalom" en ${target.label}.
; Se ejecuta como administrador. La extension queda instalada, activa y fija.
; No requiere modo desarrollador ni tienda de extensiones.

[HKEY_LOCAL_MACHINE\\SOFTWARE\\Policies\\${target.keyPath}\\ExtensionInstallForcelist]
"1"="${forceValue}"
`;
    writeFileSync(join(releaseDir, `force-install-${target.name}.reg`), reg);
}

// --- Instalacion sin empaquetar + auto-actualizacion (equipos no gestionados) -
// El .crx firmado NO se instala en equipos sin gestionar (Chrome/Edge lo
// ignoran por seguridad), asi que ahi se carga "sin empaquetar" desde una
// carpeta fija y una Tarea Programada la mantiene al dia.
const unpackedZipName = `shalom-recordar-unpacked-${version}.zip`;
writeFileSync(join(releaseDir, unpackedZipName), zip); // `zip` ya es dist comprimido

const latestJson = `${JSON.stringify({ version, zip: `${BASE_URL}/${unpackedZipName}` }, null, 2)}\n`;
writeFileSync(join(releaseDir, 'latest.json'), latestJson);

// Ruta relativa bajo %LOCALAPPDATA%. El script la resuelve con Join-Path, asi
// se expande de verdad (una cadena '...' en PowerShell no expandiria $env:...).
const UNPACKED_FOLDER_REL = 'CodeRED\\shalom-recordar';
const latestUrl = `${BASE_URL}/latest.json`;

const renderPs = (name) => readFileSync(join(templatesDir, name), 'utf8')
    .replaceAll('__FOLDER_REL__', UNPACKED_FOLDER_REL)
    .replaceAll('__LATEST_URL__', latestUrl);
writeFileSync(join(releaseDir, 'actualizar.ps1'), renderPs('actualizar.ps1'));
writeFileSync(join(releaseDir, 'instalar-desempaquetada.ps1'), renderPs('instalar-desempaquetada.ps1'));
copyFileSync(join(templatesDir, 'Instalar-Desempaquetada.cmd'), join(releaseDir, 'Instalar-Desempaquetada.cmd'));

// --- 6. Publicar en public/ext de la Plataforma (si el repo está al lado) -----
const platformPublic = join(ROOT, '..', '..', 'public', 'ext', 'shalom-recordar');
if (existsSync(join(ROOT, '..', '..', 'public'))) {
    mkdirSync(platformPublic, { recursive: true });
    writeFileSync(join(platformPublic, crxName), crx);
    writeFileSync(join(platformPublic, 'updates.xml'), updatesXml);
    writeFileSync(join(platformPublic, unpackedZipName), zip);
    writeFileSync(join(platformPublic, 'latest.json'), latestJson);
    console.log('Publicado en public/ext/shalom-recordar/ (' + crxName + ', updates.xml, ' + unpackedZipName + ', latest.json)');
}

console.log('');
console.log('Extension id : ' + extensionId);
console.log('CRX          : release/' + crxName);
console.log('Update manif : release/updates.xml');
console.log('Instalador   : release/Instalar-Shalom-Recordar.cmd (+ instalar.ps1)');
console.log('Regs manuales: release/force-install-{chrome,edge,brave,opera,vivaldi}.reg');
console.log('Base URL      : ' + BASE_URL);
console.log('');
console.log('Sube a ' + BASE_URL + '/ estos dos archivos: ' + crxName + ' y updates.xml');
console.log('Reparte a cada PC: Instalar-Shalom-Recordar.cmd + instalar.ps1 (juntos).');
