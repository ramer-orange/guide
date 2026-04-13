FROM node:20-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js postcss.config.js tailwind.config.js ./

RUN npm run build

FROM nginx:1.27-alpine

WORKDIR /var/www/html

COPY public ./public
COPY --from=assets /app/public/build ./public/build
COPY docker/production/nginx/default.conf /etc/nginx/conf.d/default.conf

RUN mkdir -p /var/www/html/storage/app/public \
    && ln -snf ../storage/app/public /var/www/html/public/storage
