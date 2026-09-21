#!/bin/bash
set -e

PORT=${PORT:-80}
echo "Zabiss starting on PORT=$PORT DB_DRIVER=${DB_DRIVER:-sqlite}"

# Adapter Apache au PORT de Render/Koyeb
if [ "$PORT" != "80" ]; then
  sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf || true
  sed -i "s/:80>/:$PORT>/" /etc/apache2/sites-available/000-default.conf || true
  sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf || true
fi

# Pas de MySQL en mode sqlite (Render free) -> init sqlite via PHP
if [ "${DB_DRIVER}" = "sqlite" ]; then
  echo "Mode SQLite - init DB..."
  mkdir -p /var/www/html/backend
  chown -R www-data:www-data /var/www/html
  # Le fichier zabiss.sqlite sera créé au premier hit via api/index.php auto-migrate
else
  # Mode MySQL (si tu ajoutes une BDD externe)
  echo "Waiting MySQL $DB_HOST:$DB_PORT..."
  for i in {1..20}; do
    if php -r "try{new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USER'), getenv('DB_PASS')); exit(0);}catch(Exception \$e){exit(1);}"; then echo "MySQL ready"; break; fi; sleep 2; done
  if [ -f "/var/www/html/backend/init-db.php" ]; then php /var/www/html/backend/init-db.php || true; fi
fi

chown -R www-data:www-data /var/www/html
exec apache2-foreground
