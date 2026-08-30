FROM node:22-alpine
RUN addgroup -S exaearn && adduser -S exaearn -G exaearn
WORKDIR /workspace
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
COPY backend/services/blockchain-service/package.json backend/services/blockchain-service/package.json
RUN corepack enable && corepack prepare pnpm@10.0.0 --activate && pnpm install --filter exaearn-blockchain-service --prod --frozen-lockfile
COPY backend/services/blockchain-service/src backend/services/blockchain-service/src
WORKDIR /workspace/backend/services/blockchain-service
USER exaearn
ENV NODE_ENV=production PORT=4000
EXPOSE 4000
STOPSIGNAL SIGTERM
CMD ["node", "src/index.js"]
