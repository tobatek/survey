# myAMETA Survey

Aplikasi survei terpusat kampus — Laravel API (`apps/api`) + Vue 3 SPA (`apps/web`).

Rencana kerja lengkap ada di [`docs/16_PLAN_FULLSTACK_LARAVEL_MYAMETA_SURVEY.md`](docs/16_PLAN_FULLSTACK_LARAVEL_MYAMETA_SURVEY.md).
Spesifikasi produk/bisnis/teknis: `docs/PRD-myAMETA-Survey.md`, `docs/BRD-myAMETA-Survey.md`,
`docs/FSD-myAMETA-Survey-Laravel.md`. Kontrak integrasi SSO ke Portal existing:
`docs/15_SSO_PORTAL_VALIDASI_SEPARATION_PLAN.md`.

## Struktur

```
apps/api/    Laravel 13 (PHP 8.3) — backend API, modular monolith per domain (app/Domains/*)
apps/web/    Vue 3 + Vite + TypeScript + Tailwind — SPA admin & responden
infra/       Docker Compose, Nginx, PHP-FPM untuk deployment satu server
docs/        Dokumen spesifikasi & rencana
```

## Menjalankan secara lokal

### Opsi A — Docker (direkomendasikan untuk paritas dengan produksi)

Butuh Docker Desktop terpasang.

```sh
cd infra
docker compose up -d
docker compose exec app php artisan migrate
```

- API: http://localhost:8000
- SPA: http://localhost:5173

### Opsi B — Tanpa Docker (PHP/Composer/Node lokal)

Butuh PHP >=8.3, Composer, PostgreSQL, Redis, dan Node >=18 terpasang.

```sh
# Backend
cd apps/api
composer install
cp .env.example .env   # sudah ada di repo, sesuaikan kredensial DB/Redis
php artisan key:generate
php artisan migrate

# Jalankan API, queue worker, dan scheduler di terminal terpisah
php artisan serve
php artisan queue:work
php artisan schedule:work
```

```sh
# Frontend (terminal terpisah)
cd apps/web
npm install
npm run dev
```

Halaman referensi komponen desain sistem (design system): http://localhost:5173/_style-guide

**Catatan port PostgreSQL (Opsi B/lokal Windows):** kalau di mesin Anda sudah ada instance
PostgreSQL lain yang memakai port 5432 default, install instance terpisah untuk proyek ini di
port lain (mis. 5434) dan sesuaikan `DB_PORT` di `.env` — jangan menimpa/memakai database milik
proyek lain. Buat role & database secara manual sekali:

```sh
psql -U postgres -h 127.0.0.1 -p <port> -c "CREATE ROLE myameta WITH LOGIN PASSWORD 'secret';"
psql -U postgres -h 127.0.0.1 -p <port> -c "CREATE DATABASE myameta_survey OWNER myameta;"
```

## Status

Fase 0 (setup + design system) dan Fase 1 (SSO Portal, RBAC, sinkronisasi data mahasiswa/pegawai)
— lihat rencana lengkap di `docs/16_PLAN_FULLSTACK_LARAVEL_MYAMETA_SURVEY.md`.

Fase 1 sudah diverifikasi end-to-end secara lokal (PostgreSQL + Redis sungguhan, queue worker
nyata, dan Portal SSO tiruan yang mengikuti kontrak `docs/15`): login → JIT provisioning user →
RBAC menegakkan akses per role → sinkronisasi data mahasiswa berjalan async lewat queue. Portal
SSO sungguhan (`SSO_PORTAL_BASE_URL`, `SSO_APP_CLIENT_ID`, `SSO_APP_TOKEN` di `.env`) belum
dikonfigurasi — nilai kunci HKDF di `SsoCryptoService` juga masih perlu diverifikasi byte-for-byte
terhadap implementasi Portal sungguhan sebelum go-live (lihat komentar di file tsb).
