import { createServer } from 'node:http'
import { readFile, stat } from 'node:fs/promises'
import process, { loadEnvFile } from 'node:process'
import { extname, resolve, sep } from 'node:path'
import { createAppBackend } from './app-backend.js'

try {
  loadEnvFile('.env.local')
} catch (error) {
  if (error.code !== 'ENOENT') throw error
}

const port = Number(process.env.PORT) || 3000
const distDirectory = resolve('dist')
const backend = createAppBackend(process.env)
const contentTypes = {
  '.css': 'text/css; charset=utf-8',
  '.html': 'text/html; charset=utf-8',
  '.ico': 'image/x-icon',
  '.jpeg': 'image/jpeg',
  '.jpg': 'image/jpeg',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.svg': 'image/svg+xml',
  '.webp': 'image/webp'
}

const serveFile = async (request, response) => {
  const pathname = decodeURIComponent(new URL(request.url, 'http://localhost').pathname)
  const requestedPath = resolve(distDirectory, `.${pathname}`)
  if (requestedPath !== distDirectory && !requestedPath.startsWith(`${distDirectory}${sep}`)) {
    response.writeHead(403).end('Forbidden')
    return
  }

  let filePath = requestedPath
  try {
    const fileStat = await stat(filePath)
    if (fileStat.isDirectory()) filePath = resolve(filePath, 'index.html')
  } catch {
    filePath = resolve(distDirectory, 'index.html')
  }

  try {
    const content = await readFile(filePath)
    const extension = extname(filePath).toLowerCase()
    const immutable = filePath.startsWith(resolve(distDirectory, 'assets'))
    response.writeHead(200, {
      'Content-Type': contentTypes[extension] || 'application/octet-stream',
      'Cache-Control': immutable ? 'public, max-age=31536000, immutable' : 'no-cache'
    })
    response.end(request.method === 'HEAD' ? undefined : content)
  } catch {
    response.writeHead(404).end('Not found')
  }
}

createServer((request, response) => {
  Promise.resolve(backend(request, response, () => serveFile(request, response)))
    .catch(error => {
      console.error(error)
      if (!response.headersSent) response.writeHead(500)
      response.end('Internal server error')
    })
}).listen(port, '0.0.0.0', () => {
  console.log(`Servidor iniciado en el puerto ${port}`)
})
