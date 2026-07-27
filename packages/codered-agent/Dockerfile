FROM node:24-alpine AS build
WORKDIR /app
COPY packages/codered-agent/package*.json ./
RUN npm ci
COPY packages/codered-agent ./
RUN npm run build && npm prune --omit=dev

FROM node:24-alpine
WORKDIR /app
ENV NODE_ENV=production
COPY --from=build /app/package*.json ./
COPY --from=build /app/node_modules ./node_modules
COPY --from=build /app/dist ./dist
RUN addgroup -S codered && adduser -S codered -G codered && mkdir -p /data && chown -R codered:codered /data /app
USER codered
EXPOSE 5680
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD node -e "fetch('http://127.0.0.1:5680/v1/health').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"
CMD ["node","dist/src/index.js"]
