#!/bin/sh
set -eu
umask 077
mkdir -p "$APACHE_RUN_DIR" "$APACHE_LOCK_DIR"
if [ "$1" = "apache2-foreground" ]; then php /var/www/html/scripts/security-check.php; fi
exec docker-php-entrypoint "$@"
