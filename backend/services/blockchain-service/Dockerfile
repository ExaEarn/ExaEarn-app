FROM node:22-alpine AS dependencies
WORKDIR /workspace
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
COPY backend/services/blockchain-service/package.json backend/services/blockchain-service/package.json
RUN corepack enable \
    && corepack prepare pnpm@10.0.0 --activate \
    && pnpm install --filter exaearn-blockchain-service --prod --frozen-lockfile

FROM node:22-alpine
RUN apk upgrade --no-cache \
    && rm -rf /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/corepack \
    && rm -f /usr/local/bin/npm /usr/local/bin/npx /usr/local/bin/corepack /usr/local/bin/pnpm /usr/local/bin/pnpx \
    && addgroup -S exaearn \
    && adduser -S exaearn -G exaearn
WORKDIR /workspace
COPY --from=dependencies /workspace/node_modules ./node_modules
COPY --from=dependencies /workspace/backend/services/blockchain-service/node_modules backend/services/blockchain-service/node_modules
COPY backend/services/blockchain-service/src backend/services/blockchain-service/src
WORKDIR /workspace/backend/services/blockchain-service
USER exaearn
ENV NODE_ENV=production PORT=4000
EXPOSE 4000
STOPSIGNAL SIGTERM
CMD ["node", "src/index.js"]
