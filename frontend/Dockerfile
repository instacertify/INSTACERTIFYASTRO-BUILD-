# Build context: repository root (the site reads ../content as a fallback).
FROM node:22-alpine AS source
WORKDIR /repo/frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY content/ /repo/content/
COPY frontend/ ./

# API_URL is optional at build time; without it pages are built from /repo/content.
ARG API_URL=""
ARG PUBLIC_API_URL
ARG PUBLIC_MATOMO_URL=""
ARG PUBLIC_MATOMO_SITE_ID=1
ENV API_URL=$API_URL PUBLIC_API_URL=$PUBLIC_API_URL \
    PUBLIC_MATOMO_URL=$PUBLIC_MATOMO_URL PUBLIC_MATOMO_SITE_ID=$PUBLIC_MATOMO_SITE_ID

# Target "builder": publishes the site from the live API into the shared "site" volume (scripts/publish-server.mjs).
FROM source AS builder
ENV NODE_ENV=production SITE_DIR=/site PORT=8787
EXPOSE 8787
CMD ["node", "scripts/publish-server.mjs"]

# Admin previews (target "preview"): API_URL must reach Laravel from inside the network, e.g. http://app:80.
FROM source AS preview-build
RUN npm run build:preview && npm prune --omit=dev

FROM node:22-alpine AS preview
WORKDIR /app
ENV NODE_ENV=production HOST=0.0.0.0 PORT=4321
COPY --from=preview-build /repo/frontend/package.json ./
COPY --from=preview-build /repo/frontend/node_modules ./node_modules
COPY --from=preview-build /repo/frontend/dist-preview ./dist-preview
USER node
EXPOSE 4321
CMD ["node", "dist-preview/server/entry.mjs"]

# Serves the site the builder publishes to the "site" volume (/srv/site/current), and reloads when it changes.
FROM caddy:2-alpine
COPY infra/caddy/Caddyfile /etc/caddy/Caddyfile
COPY infra/caddy/run.sh /usr/local/bin/run-caddy
RUN sed -i 's/\r$//' /usr/local/bin/run-caddy && chmod +x /usr/local/bin/run-caddy
CMD ["run-caddy"]
