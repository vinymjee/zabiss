#!/bin/bash
set -e

# Attendre MySQL si dispo
if [ -n "$DB_HOST" ]; then
  echo "Waiting for MySQL $DB_HOST:$DB_PORT..."
  for i in {1..30}; do
    if php -r "try{\$p=new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USER'), getenv('DB_PASS')); exit(0);}catch(Exception \$e){exit(1);}"; then
      echo "MySQL ready"
      break
    fi
    echo "retry $i..."
    sleep 2
  done

  echo "Initializing DB if needed..."
  if [ -f "/var/www/html/backend/init-db.php" ]; then php /var/www/html/backend/init-db.php || echo "init-db warn"; fi
  if [ -f "/var/www/html/init-db.php" ]; then php /var/www/html/init-db.php || true; fi
fi

# Fix permissions
chown -R www-data:www-data /var/www/html

exec apache2-foreground
