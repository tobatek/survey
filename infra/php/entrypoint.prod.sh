#!/bin/sh
# Entrypoint production apps/api. Config/route/view cache WAJIB dibuat di
# runtime (bukan saat build image) karena nilainya bergantung pada
# environment variable yang baru tersedia saat container jalan (Dokploy
# inject env saat start, bukan saat build).
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link --force || true

# Migrasi database SENGAJA TIDAK dijalankan otomatis di sini. Service ini
# (image yang sama dipakai untuk api/queue/scheduler) bisa start bersamaan
# -- auto-migrate di entrypoint akan membuat 2-3 proses coba migrate
# bersamaan (race). Jalankan migrate manual satu kali per deploy lewat
# fitur "terminal"/one-off command Dokploy di container api:
#   php artisan migrate --force
# (lihat docs/25_DEPLOYMENT_DOKPLOY.md)

exec "$@"
