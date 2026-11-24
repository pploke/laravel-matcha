#!/bin/sh
set -e

# Pastikan Apache (www-data) bisa menulis log dan cache saat bind-mount dipakai.
# Dijalankan sebagai root oleh image; service dengan user non-root melewati langkah ini.
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
fi

exec "$@"
