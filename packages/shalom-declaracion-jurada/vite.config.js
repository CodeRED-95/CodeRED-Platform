import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import { createAppBackend } from './server/app-backend.js'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, '.', '')
  let backend
  const getBackend = () => {
    backend ??= createAppBackend(env)
    return backend
  }
  const backendPlugin = {
    name: 'declaracion-jurada-backend',
    configureServer(server) {
      server.middlewares.use(getBackend())
    },
    configurePreviewServer(server) {
      server.middlewares.use(getBackend())
    }
  }

  return {
    plugins: [react(), backendPlugin]
  }
})
