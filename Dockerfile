# Zabiss - Monolith (1 container = frontend + API) pour VPS 1GB Oracle
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql mysqli && a2enmod rewrite headers
RUN echo "upload_max_filesize=20M\npost_max_size=20M" > /usr/local/etc/php/conf.d/zabiss.ini

WORKDIR /var/www/html

# Backend -> /var/www/html/backend
COPY backend/ /var/www/html/backend/
# Frontend -> /var/www/html (index.html, assets)
COPY --from=frontend /app/dist/zabiss/browser/ /var/www/html/

# Apache vhost monolith
COPY deploy/apache-monolith.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
# init script
COPY backend/init-db.php /var/www/html/backend/init-db.php

EXPOSE 80
CMD ["/entrypoint.sh"]
