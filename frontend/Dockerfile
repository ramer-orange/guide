FROM node:22.23.4-alpine3.23 AS dependencies
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
COPY scripts/check-node-runtime.mjs ./scripts/check-node-runtime.mjs
RUN npm ci

FROM node:22.23.4-alpine3.23 AS builder
WORKDIR /app
ENV NEXT_TELEMETRY_DISABLED=1
ARG NEXT_PUBLIC_SITE_URL=http://localhost:8081
ENV NEXT_PUBLIC_SITE_URL=${NEXT_PUBLIC_SITE_URL}
COPY --from=dependencies /app/node_modules ./node_modules
COPY . .
RUN npm run build

FROM node:22.23.4-alpine3.23 AS runner
WORKDIR /app
ENV NODE_ENV=production \
    NEXT_TELEMETRY_DISABLED=1 \
    NEXT_PUBLIC_SITE_URL=http://localhost:8081 \
    PORT=3000 \
    HOSTNAME=0.0.0.0
COPY --from=builder --chown=node:node /app/.next/standalone ./
COPY --from=builder --chown=node:node /app/.next/static ./.next/static
COPY --from=builder --chown=node:node /app/public ./public
COPY scripts/check-node-runtime.mjs ./scripts/check-node-runtime.mjs
RUN node scripts/check-node-runtime.mjs
USER node
EXPOSE 3000
CMD ["sh", "-c", "node scripts/check-node-runtime.mjs && exec node server.js"]
