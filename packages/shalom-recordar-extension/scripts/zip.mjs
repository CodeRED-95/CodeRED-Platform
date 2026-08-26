// Construccion de ZIP en Node puro, sin dependencias ni binarios externos.
//
// El binario `zip` no existe en Windows, que es donde se empaqueta: cualquier
// script que dependa de el falla. Esta implementacion la comparten
// package-extension.mjs y pack-crx.mjs para que el paquete salga identico por
// las dos vias.

import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { deflateRawSync, crc32 } from 'node:zlib';

export function listFiles(dir) {
    const out = [];
    for (const entry of readdirSync(dir)) {
        const full = join(dir, entry);
        if (statSync(full).isDirectory()) out.push(...listFiles(full));
        else out.push(full);
    }
    return out;
}

export function buildZip(dir) {
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
