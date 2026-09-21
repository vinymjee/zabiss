#!/bin/bash
set -e
if [ -n "$DB_HOST" ]; then
  echo "Waiting for MySQL $DB_HOST:$DB_PORT..."
  for i in {1..30}; do
    if php -r "try{\$p=new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_NAME'), getenv('DB_USER'), getenv('DB_PASS')); exit(0);}catch(Exception \$e){file_put_contents('php://stderr', \$e->getMessage().PHP_EOL); exit(1);}"; then
      echo "MySQL ready"
      break
    fi
    sleep 2
  done
  if [ -f "/var/www/html/init-db.php" ]; then
    php /var/www/html/init-db.php || echo "init-db warn"
  fi
fi
chown -R www-data:www-data /var/www/html
exec apache2-foreground
