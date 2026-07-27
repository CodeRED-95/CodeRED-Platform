FROM docker.n8n.io/n8nio/n8n:2.31.4

USER root
COPY ./packages/n8n-nodes-codered /opt/n8n-nodes-codered
RUN cd /opt/n8n-nodes-codered \
    && npm ci \
    && npm run build \
    && npm link

USER node
RUN mkdir -p /home/node/.n8n/custom \
    && cd /home/node/.n8n/custom \
    && npm link @n8n-nodes-codered/codered
