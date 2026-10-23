import http from 'node:http';
import {readFile} from 'node:fs/promises';
import {fileURLToPath} from 'node:url';
import path from 'node:path';
const root = fileURLToPath(new URL('../dist/', import.meta.url));
const types = {'.html':'text/html; charset=utf-8','.css':'text/css','.js':'text/javascript','.mjs':'text/javascript','.png':'image/png','.woff2':'font/woff2'};
http.createServer(async (req, res) => {
  const file = path.resolve(root, '.' + decodeURIComponent((req.url === '/' ? '/dashboard.html' : req.url).split('?')[0]));
  if (!file.startsWith(root)) {res.writeHead(403); res.end(); return;}
  try {const content = await readFile(file); res.writeHead(200, {'Content-Type':types[path.extname(file)] ?? 'application/octet-stream'}); res.end(content);}
  catch {res.writeHead(404); res.end();}
}).listen(4175, '127.0.0.1', () => console.log('Vista previa sin acceso a cuentas: http://127.0.0.1:4175'));
