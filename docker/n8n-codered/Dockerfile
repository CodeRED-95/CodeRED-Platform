FROM node:24-alpine AS extension-build
WORKDIR /src
COPY packages/n8n-nodes-codered/package*.json ./
RUN npm ci
COPY packages/n8n-nodes-codered ./
RUN npm run build
RUN npm test
RUN npm prune --omit=dev

FROM docker.n8n.io/n8nio/n8n:2.31.4
USER root
COPY --from=extension-build /src /opt/n8n-nodes-codered
RUN chown -R node:node /opt/n8n-nodes-codered
USER node
