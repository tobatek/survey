# Deployment ke Dokploy (staging/preview)

Status: Release 1 BELUM lolos gerbang Fase 6 penuh (performance test §26 dan
UAT §27.5 di `docs/16_PLAN_FULLSTACK_LARAVEL_MYAMETA_SURVEY.md` belum
dikerjakan — keduanya butuh infra production-equivalent/tanda tangan manusia,
lihat memory `fase6-progress`). Panduan ini untuk deploy sebagai lingkungan
**staging/preview** di Dokploy (dipakai untuk UAT, bukan rilis final ke
pengguna sungguhan), sampai gerbang itu benar-benar lolos.

File yang disiapkan:
- `infra/php/Dockerfile.prod` — image production API (Nginx + PHP-FPM dalam
  satu container lewat supervisord, self-contained).
- `infra/php/entrypoint.prod.sh`, `infra/php/supervisord.conf` — dipakai
  Dockerfile di atas.
- `infra/nginx/api.prod.conf` — config Nginx untuk API.
- `infra/web/Dockerfile.prod` — image production SPA (build Vite lalu serve
  statis lewat Nginx).
- `infra/web/nginx.prod.conf` — config Nginx untuk SPA (fallback ke
  `index.html` untuk client-side route).
- `infra/docker-compose.prod.yml` — compose untuk didaftarkan di Dokploy
  (service `api`, `web`, `queue`, `scheduler`; Postgres/Redis SENGAJA tidak
  ikut di sini, pakai fitur Database bawaan Dokploy).
- `apps/api/.env.production.example`, `apps/web/.env.production.example` —
  daftar env var yang perlu diisi (bukan file `.env` sungguhan, jangan
  commit yang asli).

## Langkah di Dokploy

1. **Buat resource Database (Postgres)** di Dokploy. Catat host/port/user/
   password/nama database yang dibuatkan — ini isi `DB_HOST`, `DB_PORT`,
   `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE` nanti.
2. **Buat resource Database (Redis)** di Dokploy. Catat host/port/password —
   isi `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`.
3. **Buat Compose Application baru**, hubungkan ke repo git ini, pilih file
   compose `infra/docker-compose.prod.yml`. Pastikan build context di
   Dokploy mengarah ke **root repo** (bukan `apps/api`), sesuai komentar di
   tiap `Dockerfile.prod`.
4. **Isi environment variable** di panel Environment Dokploy untuk compose
   ini, berdasarkan `apps/api/.env.production.example`:
   - `APP_KEY` — generate SEKALI dari lokal: `php artisan key:generate --show`
     di `apps/api`, tempel hasilnya (`base64:...`). Jangan generate ulang di
     server tiap deploy.
   - `APP_URL`, `SPA_URL`, `SANCTUM_STATEFUL_DOMAINS` — domain publik yang
     dipakai (bisa subdomain Dokploy dulu untuk staging, mis.
     `api-staging.xxx.dokploy.app` dan `app-staging.xxx.dokploy.app`).
   - `DB_*`, `REDIS_*` — dari langkah 1-2.
   - `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=strict` — WAJIB karena
     domain Dokploy selalu HTTPS.
   - `SSO_*`, `MAIL_*` — isi kalau staging ini memang perlu SSO/email
     sungguhan berfungsi; kalau cuma mau tes modul lain dulu, boleh kosong/
     log driver (`MAIL_MAILER=log`) sementara.
   - `VITE_API_ORIGIN`, `VITE_API_BASE_URL` (build-arg service `web`) — isi
     ke domain publik API yang dipakai di langkah ini. **Build ulang service
     `web` kalau domain API berubah** (nilainya dibakar ke bundle JS saat
     build, tidak baca ulang env saat container jalan).
5. **Set domain publik** di Dokploy untuk service `api` (port container 80)
   dan service `web` (port container 80) masing-masing — HTTPS otomatis
   lewat Dokploy (Let's Encrypt).
6. **Deploy.** Dokploy build image dari compose, start semua service.
7. **Migrasi database (manual, sekali per deploy yang bawa migration baru)**
   — buka terminal/one-off command Dokploy untuk container `api`, jalankan:
   ```
   php artisan migrate --force
   ```
   Sengaja TIDAK otomatis jalan saat container start (lihat komentar di
   `entrypoint.prod.sh`) supaya tidak ada race 3 service start bersamaan
   dan supaya migration destruktif selalu melalui langkah sadar, bukan diam-
   diam jalan tiap deploy.
8. **Cek health**: buka `https://<domain-api>/up` (health check bawaan
   Laravel) dan `https://<domain-api>/api/health/deep` (role
   `technical_admin`, Fase 6 paket E) untuk pastikan DB/Redis/queue
   tersambung.
9. **Login dev-mode TIDAK akan berfungsi di staging** — dev-login cuma untuk
   `localhost` (lihat memory `sso-devlogin-localhost`). Staging butuh SSO
   Portal sungguhan terhubung (`SSO_*` di atas), atau endpoint dev-login
   perlu dibuka sementara khusus untuk UAT internal (putuskan dulu sebelum
   dipakai tim UAT, ini mengubah threat model).

## Yang masih perlu diputuskan sebelum staging ini dianggap "siap UAT"

- Apakah dev-login dibuka untuk staging (poin 9) atau SSO Portal sungguhan
  memang sudah bisa diajak terhubung dari staging.
- Channel alerting dasar (lihat diskusi Fase 6) — opsional untuk staging,
  tapi disarankan sebelum staging dipakai UAT beneran supaya ada yang tahu
  kalau service down di tengah sesi UAT.
- Setelah staging ini dipakai UAT dan performance test di server
  production-equivalent (bisa server Dokploy yang sama kalau spek-nya memang
  sudah representatif), baru statusnya naik jadi kandidat production
  sungguhan — bukan otomatis dari staging ini jalan mulus.
