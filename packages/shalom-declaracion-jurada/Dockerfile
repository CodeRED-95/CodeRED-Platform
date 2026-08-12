# syntax=docker/dockerfile:1

# ---- Etapa 1: build del frontend (Vite/React) ----
FROM node:22-alpine AS builder
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# ---- Etapa 2: runtime de producción ----
# server/app-backend.js y server/production.js usan sobre todo módulos
# nativos de Node (incluido el built-in node:sqlite), salvo "pg" — el
# cliente PostgreSQL usado para la interconectividad de solo lectura con la
# base de CodeRED Platform (ver CODERED_DB_* en server/app-backend.js). Por
# eso la imagen final no copia node_modules del builder (que incluye
# React/Vite, innecesarios en runtime) y en su lugar instala únicamente
# "pg" y sus dependencias.
FROM node:22-alpine AS runtime
ENV NODE_ENV=production
WORKDIR /app

COPY --from=builder /app/dist ./dist
COPY server ./server
RUN npm install pg@^8.13.1 --omit=dev --no-save --no-audit --no-fund

RUN addgroup -S app \
 && adduser -S app -G app \
 && mkdir -p /app/.data \
 && chown -R app:app /app

USER app
EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
  CMD node -e "fetch('http://127.0.0.1:'+(process.env.PORT||3000)+'/').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"

CMD ["node", "server/production.js"]
