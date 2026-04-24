FROM node:20-alpine

RUN apk add --no-cache wget

WORKDIR /app

COPY package*.json ./
RUN npm install --omit=dev

COPY src ./src

ENV PORT=3333 \
    HOST=0.0.0.0 \
    MCP_DATA_DIR=/data

RUN mkdir -p /data

EXPOSE 3333

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
  CMD wget --no-verbose --tries=1 --spider http://localhost:3333/health || exit 1

CMD ["node", "src/server.js"]
