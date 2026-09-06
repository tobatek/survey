# Plan Pengerjaan Fullstack — myAMETA Survey (Laravel API + Vue SPA)

## Context

Repo `C:\Siakad Project` saat ini hanya berisi 4 dokumen spesifikasi (belum ada kode):
`PRD-myAMETA-Survey.md`, `BRD-myAMETA-Survey.md`, `FSD-myAMETA-Survey-Laravel.md`, dan
`15_SSO_PORTAL_VALIDASI_SEPARATION_PLAN.md`. Ketiga dokumen pertama mendefinisikan produk
"myAMETA Survey" — aplikasi survei terpusat kampus (5 menu admin: Kuesioner, Data Mahasiswa,
Data Pegawai, Hasil Analisa Kuesioner, Pengaturan/Import Template) dengan SSO, sinkronisasi
data master, builder kuesioner, targeting/campaign, pengisian responden, analitik, dan ekspor.
Dokumen ke-4 adalah rencana arsitektur SSO+Master Data pada ekosistem Portal yang sudah berjalan
di repo lain (`apps_validasi_data_pddikti`), dipakai di sini sebagai **kontrak integrasi** yang
harus diikuti myAMETA Survey sebagai "App Luar".

Tujuan dokumen ini: menyusun **rencana kerja fullstack Laravel** (backend API + SPA terpisah)
untuk myAMETA Survey, dipecah menjadi fase-fase yang bisa dieksekusi berurutan, dengan acuan
gaya UI dashboard seperti **karirlink.id** (bersih, berbasis card, filter sidebar, badge status,
sederhana tapi profesional), dan wajib kompatibel mobile + desktop. **Tidak ada eksekusi/kode
pada tahap ini** — dokumen ini murni rencana.

Keputusan yang sudah dikonfirmasi user untuk plan ini:
1. **Frontend: SPA terpisah** (Laravel jadi backend API murni + SPA), bukan Blade/Livewire.
2. **Cakupan fase mengikuti urutan Release PRD**: Release 1 (Core MVP) → Release 1.1 → Release 2.
3. **SSO terintegrasi ke Portal existing** (dokumen 15) sebagai App Luar — pakai kontrak
   `/sso/authorize` (browser leg) + `/sso/verify` (server-to-server) + `/sso/session-status`
   + Master Data API Portal (`/api/master/students`, `/api/master/employees`, dst.) untuk sinkron
   data mahasiswa/pegawai, bukan membangun SSO/OIDC sendiri.

Asumsi teknis tambahan (default yang direkomendasikan, dikonfirmasi ulang sebelum kickoff sesuai
"Open Technical Decisions" FSD §32):
- **SPA framework: Vue 3 + Vite + Pinia + Vue Router + Tailwind CSS**, mengikuti pola monorepo
  `apps/api` (Laravel) + `apps/web` (SPA) seperti repo Portal/Validasi pada dokumen 15, supaya
  tooling (Docker/CI) konsisten lintas proyek. React adalah alternatif setara bila tim lebih
  familiar — tidak mengubah struktur fase, hanya pilihan library.
- **Charting**: ApexCharts atau Chart.js (mendukung bar/donut/stacked/heatmap + export PNG).
- **Database**: PostgreSQL, session/cache/queue: Redis, sesuai FSD §3.3.
- **Single-tenant** untuk Release 1 (kolom `tenant_id` tetap disiapkan di skema agar migrasi ke
  multi-tenant tidak butuh rombak besar — sesuai FSD §16 yang sudah mendesain `tenant_id` di
  semua tabel).
- Deployment mengikuti arsitektur satu server tanpa load balancer (FSD §3–4).

Poin-poin di atas yang masih berstatus "Open Decision" pada PRD §22 / BRD §20 / FSD §32 (mis.
nama resmi aplikasi, threshold default, format XLSX final, retensi, server produksi) **tidak
diblokir** oleh plan ini — akan diselesaikan sebagai bagian dari Fase 0 (kickoff) di bawah.

---

## Referensi Desain UI (karirlink.id → diadaptasi)

karirlink.id (portal karir SEVIMA) memakai gaya *functional minimalism* yang relevan untuk kedua
sisi myAMETA Survey (admin & responden):

| Elemen karirlink.id | Adaptasi untuk myAMETA Survey |
|---|---|
| Top navbar horizontal + CTA menonjol ("Gabung Sekarang") | Topbar admin: nama institusi, role aktif, notifikasi job (export/import), logout. CTA utama "Buat Kuesioner". |
| Sidebar filter kiri (job type, lokasi, gaji, dll., collapsible) | Sidebar admin 5 menu (Kuesioner, Data Mahasiswa, Data Pegawai, Hasil Analisa, Pengaturan) — collapsible/hamburger di mobile. Filter kompleks (prodi/angkatan/unit/status) dipakai di Data Mahasiswa/Pegawai & Hasil Analisa memakai pola panel filter yang sama. |
| Card listing (logo, judul, badge tipe, lokasi, tanggal) | Card kuesioner di daftar Kuesioner (kode, judul, badge status: Draft/Siap/Aktif/Dijeda/Ditutup, response rate, tombol aksi kontekstual) dan card survei di "My Surveys" responden (judul, kategori, estimasi waktu, badge status, tombol Mulai/Lanjutkan). |
| Palet netral (abu-abu) + aksen warna untuk CTA | Palet netral + 1 warna aksen brand institusi untuk tombol primer & badge status (mapping warna konsisten: Draft=abu, Siap=biru, Aktif=hijau, Dijeda=kuning, Ditutup=merah muda/abu gelap). |
| Tombol "Terapkan Filter" / "Cari" | Pola form filter identik dipakai lintas menu (search + filter + apply), bukan filter otomatis on-change agar hemat request pada beban tinggi. |
| Pagination "Menampilkan 1–12 dari Total 12 data" | Pagination server-side identik dipakai di semua tabel/daftar (Kuesioner, Mahasiswa, Pegawai, Hasil Analisa). |
| Tipografi sans-serif bersih, hierarki jelas | Satu font family (mis. Inter/Plus Jakarta Sans) dengan skala heading konsisten; teks Bahasa Indonesia sederhana sesuai FSD §25. |

**Komponen desain sistem yang dibangun sekali dan dipakai ulang** (Fase 0):
`AppShell` (sidebar+topbar admin, layout terpisah untuk responden tanpa sidebar admin),
`DataTable` (search+filter+sort+pagination server-side), `FilterPanel` (collapsible, dipakai
Mahasiswa/Pegawai/Analytics), `StatusBadge`, `Card` (kuesioner & survei responden), `StatCard`
(untuk dashboard funnel), `ChartCard` (wrapper chart + judul + `n` + filter + export PNG),
`FormWizard`/`Stepper` (builder section & form pengisian responden), `EmptyState`,
`ToastNotification` (job selesai: export/import/sync). Semua komponen dibangun **mobile-first**
dengan breakpoint Tailwind (`sm/md/lg/xl`): sidebar → bottom sheet/hamburger di mobile, tabel →
stacked card di mobile, form pengisian survei → satu kolom penuh di mobile (pola karirlink saat
apply job dari HP).

---

## Struktur Repo (diusulkan)

Mengikuti pola monorepo dokumen 15 agar tooling konsisten dan integrasi SSO lebih mudah direplikasi:

```
myameta-survey/
├── apps/
│   ├── api/          # Laravel (modular monolith, domain-based — FSD §3.2)
│   │   └── app/Domains/{Identity,Students,Employees,Questionnaires,Templates,
│   │                     Campaigns,Responses,Analytics,Exports,Integrations,Audit}/
│   └── web/          # Vue 3 SPA (admin + responden dalam satu SPA, route-guarded)
│       └── src/features/{questionnaires,students,employees,analytics,settings,
│                          respondent,auth,shared}/
├── docs/             # PRD/BRD/FSD (dipindahkan/disalin ke sini saat setup)
└── infra/            # Docker, deployment, docker-compose (single-server)
```

---

## FASE 0 — Kickoff, Setup Proyek, dan Design System (± 1–2 minggu)

**Tujuan:** menutup open decisions blocking, menyiapkan pondasi teknis dan komponen UI.

1. Kickoff decisions (PRD §22, BRD §20, FSD §32): nama aplikasi/domain, single-tenant konfirmasi,
   detail klaim SSO (pakai kontrak dokumen 15 apa adanya), threshold default (`minimum_segment_size=5`
   sesuai FSD §12.2 sebagai default), mode identitas default per template, spesifikasi server awal
   (acuan sizing FSD §4.4: 8 vCPU/16GB RAM untuk baseline produksi).
2. Setup monorepo: Laravel (`apps/api`) + Vue 3/Vite (`apps/web`), Docker Compose lokal
   (PHP-FPM, Nginx, PostgreSQL, Redis), lockfile, CI dasar (lint, test, build) — FSD §28.
3. Skeleton domain folders sesuai FSD §3.2 (`app/Domains/...`) — controller tipis, business logic
   di Actions/Services per domain.
4. Design system: Tailwind config (token warna/spacing/typografi terinspirasi karirlink.id di
   atas), bangun komponen inti (`AppShell`, `DataTable`, `FilterPanel`, `StatusBadge`, `Card`,
   `StatCard`, `ChartCard`, `FormWizard`, `EmptyState`) sebagai Storybook/halaman contoh, uji
   responsif mobile (≤640px), tablet, desktop.
5. Observability dasar: health endpoint (liveness/readiness — FSD §23.2), error tracking, logging
   konvensi (tidak boleh log token/jawaban — FSD §21.1).

**Exit criteria:** repo jalan lokal (`docker compose up`), CI hijau, komponen desain sistem
ter-render benar di 3 breakpoint, keputusan kickoff terdokumentasi.

---

## FASE 1 — Identity: SSO Integration, RBAC, Master Data Sync (± 2–3 minggu)

Referensi: FSD §5 (Auth), §8–9 (Data Mahasiswa/Pegawai), §16.1–16.2 (skema), §17.2/17.5 (API),
dan dokumen 15 (kontrak integrasi Portal).

**Backend (`apps/api`):**
- Klien SSO ke Portal: redirect browser ke `Portal /sso/authorize?client_id=...&redirect_uri=...`,
  handle callback dengan `code`, tukar via `POST /api/sso/verify` (server-to-server, amplop
  AES-256-GCM, kunci HKDF dari `app_token` — port logic dari `SsoCryptoService.php` referensi).
  **myAMETA Survey tidak boleh punya form login sendiri** (aturan eksplisit dokumen 15).
- Session lokal: buat/JIT-update `users` dari identitas SSO (atribut minimum saja — role/scope
  ditentukan lokal per FSD §5.2–5.3, independen dari role Portal).
- (Opsional, direkomendasikan) polling `POST /api/sso/session-status` untuk ikut idle-timeout
  Portal.
- RBAC: roles `lpm_admin`, `survey_operator`, `executive_viewer`, `technical_admin`,
  `student_respondent`, `employee_respondent`, `raw_data_analyst` — Laravel Policies/Gates
  memeriksa role **dan** scope organisasi (FSD §5.3, §16.1 `user_scopes`).
- Master Data: konsumsi read-only `GET /api/master/students` & `.../employees` dari Portal
  (bukan sinkron independen dari SIAKAD/HRIS langsung) — cache lokal (tabel `students`/`employees`
  sebagai mirror, skema FSD §16.2) via scheduled sync + webhook + manual resync fallback (pola
  Tahap 0 dokumen 15). Kunci upsert `(tenant_id, source_system, source_id)`.
- Endpoint internal: `GET/POST /api/students`, `/api/employees`, `POST .../sync`,
  `GET /api/sync-runs/{id}` (FSD §17.5).
- Audit log untuk login, sync trigger/result, role/scope change (FSD §22).

**Frontend (`apps/web`):**
- Auth flow: halaman redirect ke Portal, handle callback route, guard router (admin vs responden
  vs unauthenticated).
- Menu **Data Mahasiswa** & **Data Pegawai**: `DataTable` + `FilterPanel` (prodi/jenjang/angkatan/
  status; jenis pegawai/unit/jabatan), kolom `last_synced_at` + `source`, tombol "Sinkron Sekarang"
  (role-gated), badge jumlah berhasil/gagal/bermasalah, ekspor sesuai kewenangan.

**Exit criteria:** login SSO end-to-end (staff & mahasiswa) berhasil di lingkungan staging Portal,
role menentukan menu yang terlihat, sync master data berjalan terjadwal + manual trigger, gagal
sync tidak menghapus data massal (RULE-D02), UI Data Mahasiswa/Pegawai responsif mobile.

---

## FASE 2 — Modul Kuesioner: Builder, Template, Lifecycle (± 3–4 minggu)

Referensi: FSD §7 (builder), §15 (import template), §16.3 (skema), §17.3/17.7 (API).

**Backend:**
- Skema: `survey_templates`, `questionnaires`, `questionnaire_sections`, `questions`,
  `question_options`, `matrix_rows`, `questionnaire_snapshots` (immutable, checksum).
- Tipe pertanyaan MVP: `short_text, long_text, single_choice, multiple_choice, dropdown, likert,
  matrix, number, date` (FSD §7.3).
- State machine `draft→ready→active→paused→closed` dengan transaction + optimistic locking
  (`lock_version`) — FSD §7.6, §20.
- Branching engine (section-level), validasi cycle/reachability (FSD §7.4).
- Validasi finalisasi (FSD §7.5): metadata kosong, section/pertanyaan kosong, kode duplikat,
  opsi kosong/duplikat, branch invalid, unreachable section.
- Import Template (menu **Pengaturan**): upload XLSX/JSON → staging → validasi bertahap (FSD
  §15.3, 10 tahap) → preview → commit transaksional all-or-nothing (RULE-T01/T02) → histori
  (`template_imports`, `template_import_errors`).
- API: CRUD questionnaire/section/question (`If-Match`/`lock_version` wajib pada mutasi builder),
  `duplicate`, `validate`, `preview`, `finalize`, `return-to-draft`; endpoint template import
  (`sample.xlsx`, upload, validate, commit, list, activate/deactivate, export).

**Frontend:**
- Menu **Kuesioner**: daftar dengan tab status (Draft/Siap/Aktif/Dijeda/Ditutup) ala karirlink
  card+filter, search kode/judul.
- Builder UI: sidebar section (drag-reorder), canvas pertanyaan per tipe, panel properti
  (required, allow N/A, opsi, branch target), toolbar Preview/Validasi/Simpan/Finalisasi,
  autosave dengan debounce + version-conflict warning (HTTP 409 → tawarkan reload).
- Preview mode: desktop width, mobile width, branch simulator, test response (terpisah dari
  respons produksi — RULE-R07).
- Menu **Pengaturan/Import Template**: wizard upload → preview error per baris/kolom → konfirmasi
  commit → histori impor → aktif/nonaktif template.

**Exit criteria:** admin dapat membuat kuesioner dari nol/template, semua tipe pertanyaan +
branching berfungsi, finalisasi mengunci struktur (tidak bisa diedit setelah `ready`), import
template XLSX & JSON valid/invalid teruji (all-or-nothing), builder & preview responsif mobile.

---

## FASE 3 — Campaign: Target, Snapshot, Distribusi (± 2 minggu)

Referensi: FSD §10 (campaign), §16.4 (skema), §17.4 (API), §18.3 (scheduler).

**Backend:**
- Skema: `campaigns`, `audience_members`, `eligibilities`, `delivery_attempts`.
- Target builder query (prodi/fakultas/jenjang/angkatan/status untuk mahasiswa; jenis/unit/status/
  jabatan-terbatas untuk pegawai) + preview count sebelum aktivasi (RULE-C01: target 0 → aktivasi
  ditolak).
- Aktivasi: acquire lock → materialize `audience_members` per chunk → satu `eligibility` unik per
  anggota → snapshot atribut kategorikal (bukan identitas langsung) → simpan denominator →
  set status `active` → dispatch notifikasi **setelah commit** (`afterCommit`).
- Scheduler: aktivasi/penutupan due campaign tiap menit (`withoutOverlapping` + Redis lock),
  reminder job (hanya eligibility belum selesai, quiet hours, batas interval — FSD §10.5).
- Queue: `notifications` untuk email/portal task center (opsional MVP sesuai keputusan kickoff).

**Frontend:**
- Alur dari Builder: "Target Responden" → filter + preview jumlah eligible → "Jadwal & Kanal"
  (tanggal mulai/selesai + timezone institusi) → "Aktifkan".
- Dashboard monitoring campaign: funnel (eligible→invited→opened→started→completed), response
  rate, breakdown prodi/angkatan/unit (dengan threshold), tombol jeda/tutup/reminder.

**Exit criteria:** aktivasi campaign membuat snapshot & eligibility yang idempotent terhadap
retry, scheduler menutup/mengaktifkan campaign due tanpa duplikasi eksekusi, reminder hanya
menjangkau yang belum selesai, dashboard monitoring akurat & responsif mobile.

---

## FASE 4 — Respondent Experience: Pengisian Survei (± 3 minggu)

Referensi: FSD §11–12 (form & verified anonymous), §16.5 (response vault), §17.2 (API).

**Backend:**
- `response_sessions`, `responses`, `answers`, `answer_choices`, `idempotency_keys`.
- **Separasi skema untuk verified anonymous** (FSD §12.1): schema `response_vault.*` tanpa FK
  `student_id/employee_id/user_id/eligibility_id`; token invitation ditukar nonce sekali pakai;
  hanya `segment_snapshot_json` (atribut kategorikal allowlist) yang dibawa ke response vault.
- Submit flow idempotent (FSD §11.4, 11 langkah): `Idempotency-Key`, lock per session, validasi
  eligibility+ownership+belum-completed, ambil snapshot immutable, hitung reachable sections,
  validasi jawaban, transaction insert response+answers, event completion async ke campaign.
- Error codes standar: `SURVEY_NOT_STARTED/PAUSED/CLOSED, NOT_ELIGIBLE, ALREADY_COMPLETED,
  SESSION_EXPIRED, VALIDATION_FAILED, STALE_DRAFT, SUBMISSION_IN_PROGRESS,
  SERVICE_TEMPORARILY_UNAVAILABLE`.
- API: `GET /api/me/surveys`, `POST /api/my-surveys/{eligibility}/start`,
  `GET/PUT /api/responses/{session}` (draft), `POST /api/responses/{session}/submit`.

**Frontend (mobile-first — ini yang paling wajib mobile-friendly, dipakai mahasiswa dari HP):**
- **My Surveys**: card list (judul, kategori, periode, estimasi waktu, badge status, tombol
  Mulai/Lanjutkan/Lihat Status) — pola card karirlink.
- **Survey landing**: tujuan, pengelola, periode, durasi, mode identitas, privacy notice,
  tombol Mulai.
- **Form pengisian**: satu section per langkah (stepper), progress indicator, validasi inline +
  ringkasan, autosave (debounce) bila diizinkan, keyboard-accessible, branching real-time,
  halaman review sebelum submit, konfirmasi selesai.
- Pesan state khusus (belum mulai/dijeda/berakhir/tidak eligible) ditampilkan human-readable +
  correlation ID.

**Exit criteria:** submit tidak pernah menghasilkan respons ganda pada uji concurrency, mode
verified anonymous terbukti tidak punya jalur query balik ke identitas, form berfungsi mulus di
viewport mobile umum (360–414px) dan desktop, autosave tidak kehilangan draft saat refresh.

---

## FASE 5 — Hasil Analisa Kuesioner & Export (± 3 minggu)

Referensi: FSD §13 (analitik/rumus/chart), §14 (export), §16.6 (skema), §17.6 (API).

**Backend:**
- `analysis_aggregates`, `export_jobs`.
- Implementasi rumus (FSD §13.4) sebagai unit-tested pure functions: response rate, completion
  rate, persentase single/multiple choice, mean/median likert/matrix, persentase positif —
  **wajib golden dataset test** (FSD §27.3) sebelum dipakai UI.
- Strategi agregasi: query langsung untuk dataset kecil, materialized/aggregate table + event-
  driven refresh (debounced) untuk campaign besar; cache key mencakup snapshot checksum + scope +
  filter hash.
- Threshold suppression (`minimum_segment_size`) diterapkan server-side pada semua endpoint
  filter, termasuk endpoint detail (tidak bisa dibypass dari client).
- Export async: `export_jobs` queue → file XLSX/CSV/PDF/PNG → signed URL berumur pendek →
  cleanup scheduler (FSD §14.2). CSV formula-injection escaping wajib.

**Frontend:**
- Menu **Hasil Analisa Kuesioner**: daftar campaign berespons (ringkasan funnel + response rate),
  halaman hasil dengan `FilterPanel` (jenis responden, prodi/angkatan, unit, periode) +
  `ChartCard` per pertanyaan sesuai mapping tipe→chart (FSD §13.5) + tabel fallback aksesibel +
  komentar teks (pagination+search, role-gated) + tombol export (XLSX/CSV/PDF/PNG) dengan status
  job (queued/processing/selesai — toast notification).

**Exit criteria:** seluruh rumus lolos golden dataset, filter tidak pernah membocorkan segmen di
bawah threshold, export besar tidak menahan request web (queue), chart+tabel dapat diakses via
keyboard/screen reader, tampilan analytics tetap terbaca di mobile (chart scroll horizontal bila
perlu, bukan overflow halaman).

---

## FASE 6 — Hardening, Security, Observability, Performance Test (± 2–3 minggu)

Referensi: FSD §4 (resiliensi 1 server), §18–19 (queue/cache), §21–24 (security/audit/observability/
backup), §26 (performance test plan) — **ini gerbang wajib sebelum Release 1 dianggap selesai**.

1. Security hardening: CSRF, output encoding, rate limiting per user/IP/token, secure headers/CSP,
   file upload validation (allowlist ext/MIME/size), secrets via env/secret manager, log tidak
   memuat token/jawaban (FSD §21).
2. Caching & concurrency: Redis session/cache, PHP OPcache, PgBouncer/connection pooling bila
   sesuai, `withoutOverlapping` pada semua scheduled job, review N+1 query.
3. Observability: metrics (P50/P95/P99, PHP-FPM saturation, queue depth, Redis, DB), health
   endpoints (liveness/readiness), alerting dasar.
4. Backup/DR: backup harian PostgreSQL (+PITR bila memungkinkan), restore drill, dokumentasi
   RPO/RTO, runbook (DB/Redis/SSO/disk/queue stuck).
5. **Performance test** sesuai skenario FSD §26.2 pada server production-equivalent: login burst
   300/60dtk, 500 sesi aktif, 200 pembukaan form bersamaan, 100 submit spike, 50 admin analytics
   concurrent, soak test 1–2 jam, queue stress export, dependency degradation (SMTP/Portal API
   lambat). Tuning order: query/index → cache/aggregate → PHP-FPM/OPcache → Redis/queue → DB
   pooling → vertical scaling (bukan load balancer — FSD §26.4).
6. Testing menyeluruh (FSD §27): unit (state machine, branching, rumus, threshold, idempotency),
   feature/integration (SSO mock, sync, CRUD, submit, export/import), security test (broken
   access control, IDOR, replay, session), E2E lintas role & device (mobile+desktop).
7. UAT (BRD §18): seluruh skenario minimum ditandatangani per kelompok penguji.

**Exit criteria = Acceptance Criteria Produk PRD §21 (lengkap 15 poin)**: 5 menu final,
lifecycle kuesioner benar, SSO+eligibility benar, sync tanpa penghapusan massal, seluruh tipe
pertanyaan+branching, struktur aktif terkunci, no duplicate response, verified anonymous tanpa
relasi identitas langsung, rumus/denominator/`n` benar, threshold tidak bocor, export berjalan
via queue, import template all-or-nothing, kapasitas lulus tanpa load balancer, audit log ada,
UAT seluruh role ditandatangani.

---

## FASE 7 — Release 1.1 (± 3–4 minggu, setelah Release 1 live)

Referensi: PRD §18 Release 1.1.

- Autosave responden & builder lebih matang (conflict resolution UX lebih halus).
- Email reminder penuh (bila belum di Release 1) melalui queue `notifications`.
- Perbandingan hasil antarperiode (RULE-A07: hanya bila item/skala kompatibel).
- Ekspor template (backup/reuse).
- Peningkatan chart (heatmap matrix lebih matang) dan kualitas PDF (branding institusi).

---

## FASE 8 — Release 2 (backlog, di-scope ulang saat kickoff-nya sendiri)

Referensi: PRD §18 Release 2 — **tidak dikerjakan pada scope plan ini**, hanya dicatat sebagai
roadmap: public QR survey, EDOM/kelas, tracer study lebih lengkap, integrasi tambahan, statistik
lanjutan sesuai kebutuhan terverifikasi. PPEPP tetap produk terpisah (bukan otomatis masuk sini).

---

## Ringkasan Timeline (indikatif, bisa overlap antar tim frontend/backend)

| Fase | Fokus | Estimasi |
|---|---|---|
| 0 | Setup + design system | 1–2 minggu |
| 1 | SSO + RBAC + Master Data sync | 2–3 minggu |
| 2 | Kuesioner builder + template import | 3–4 minggu |
| 3 | Campaign/target/distribusi | 2 minggu |
| 4 | Pengisian responden | 3 minggu |
| 5 | Hasil analisa + export | 3 minggu |
| 6 | Hardening + performance + UAT (gerbang Release 1) | 2–3 minggu |
| 7 | Release 1.1 | 3–4 minggu |
| 8 | Release 2 | backlog terpisah |

**Total Release 1 (Fase 0–6): ± 16–20 minggu**, tergantung ukuran tim dan seberapa cepat
ketergantungan Portal SSO (dokumen 15 Tahap 1) siap diuji end-to-end (saat ini status Google
OAuth credentials di Portal masih kosong per catatan dokumen tsb — ini **risiko jadwal eksternal**
yang perlu dikonfirmasi ke tim Portal sebelum Fase 1 dimulai).

---

## Verifikasi Rencana (bukan eksekusi)

Karena tahap ini adalah plan-only, "verifikasi" berarti memastikan traceability, bukan
menjalankan kode:
- Setiap fase di atas dapat ditelusuri balik ke ID requirement PRD/BRD (`PRD-Q*/D*/C*/R*/A*/T*`,
  `BR-*`) via tabel Traceability FSD §30 — tidak ada requirement Must yang terlewat dari Fase 0–6.
- Exit criteria tiap fase mengacu langsung ke Definition of Done FSD §31 dan Acceptance Criteria
  PRD §21.
- Ketergantungan eksternal (Portal SSO, Master Data API) ditandai eksplisit di Fase 1 dan pada
  baris risiko jadwal di atas.
