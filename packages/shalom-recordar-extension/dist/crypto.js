// Utilidades de cifrado (AES-256-GCM + PBKDF2). Compartido por background.js y popup.js.

const PBKDF2_ITERATIONS = 250000;
const CHECK_PLAINTEXT = 'shalom-check-v1';

function bytesToBase64(bytes) {
    let binary = '';
    bytes.forEach((b) => { binary += String.fromCharCode(b); });
    return btoa(binary);
}

function base64ToBytes(b64) {
    const binary = atob(b64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return bytes;
}

function generateSaltB64() {
    return bytesToBase64(crypto.getRandomValues(new Uint8Array(16)));
}

// key extractable=true: necesitamos poder exportarla a chrome.storage.session
// para que sobreviva a los reinicios del service worker dentro de la misma sesión del navegador.
async function deriveKey(passphrase, saltB64) {
    const enc = new TextEncoder();
    const salt = base64ToBytes(saltB64);
    const baseKey = await crypto.subtle.importKey(
        'raw', enc.encode(passphrase), 'PBKDF2', false, ['deriveKey']
    );
    return crypto.subtle.deriveKey(
        { name: 'PBKDF2', salt, iterations: PBKDF2_ITERATIONS, hash: 'SHA-256' },
        baseKey,
        { name: 'AES-GCM', length: 256 },
        true,
        ['encrypt', 'decrypt']
    );
}

async function exportKeyB64(key) {
    const raw = await crypto.subtle.exportKey('raw', key);
    return bytesToBase64(new Uint8Array(raw));
}

async function importKeyB64(keyB64) {
    return crypto.subtle.importKey('raw', base64ToBytes(keyB64), 'AES-GCM', true, ['encrypt', 'decrypt']);
}

async function encryptText(key, plaintext) {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const enc = new TextEncoder();
    const cipherBuf = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, enc.encode(plaintext));
    return { iv: bytesToBase64(iv), data: bytesToBase64(new Uint8Array(cipherBuf)) };
}

async function decryptText(key, payload) {
    const iv = base64ToBytes(payload.iv);
    const data = base64ToBytes(payload.data);
    const plainBuf = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, key, data);
    return new TextDecoder().decode(plainBuf);
}

async function makeCheckValue(key) {
    return encryptText(key, CHECK_PLAINTEXT);
}

async function verifyCheckValue(key, checkValue) {
    try {
        const plain = await decryptText(key, checkValue);
        return plain === CHECK_PLAINTEXT;
    } catch (e) {
        return false; // fallo de autenticación GCM = passphrase incorrecta
    }
}
