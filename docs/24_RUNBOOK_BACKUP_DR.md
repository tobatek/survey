# Runbook Backup, Restore & Disaster Recovery — myAMETA Survey

Referensi: FSD §24 (Backup, Restore, dan Disaster Recovery). Dibuat Fase 6 paket G.

## 1. Backup

- Dijalankan otomatis tiap hari jam 01:30 (`routes/console.php`,
  `Schedule::command('backup:database')->dailyAt('01:30')->withoutOverlapping()`),
  sebelum jadwal sync roster 02:00/02:15 supaya tidak tumpang tindih I/O berat.
- Perintah: `php artisan backup:database` (`App\Console\Commands\BackupDatabase`).
- Metode: `pg_dump --format=custom` (bukan plain SQL) — mendukung restore
  parsial/paralel lewat `pg_restore`, dan bisa dikompresi otomatis oleh
  format custom-nya sendiri.
- Disimpan ke disk privat yang sama dipakai export (`local`,
  `storage/app/private/backups/`), nama file `db-{YmdHis}.dump`.
- **Retensi 7 hari** — backup lebih lama dari itu dihapus otomatis tiap kali
  command ini jalan (bagian dari command yang sama, bukan job terpisah).
- **RPO (Recovery Point Objective) saat ini: ±24 jam** (backup harian,
  bukan kontinu/PITR). Kalau kebutuhan RPO lebih ketat muncul di kemudian
  hari, upgrade ke WAL archiving/PITR PostgreSQL (FSD §24 menyebut ini
  sebagai opsi "bila tersedia") — di luar cakupan Fase 6 paket G, dicatat
  sebagai follow-up kalau dibutuhkan.

## 2. Restore drill — hasil terukur (dijalankan nyata, bukan estimasi)

Dijalankan 2026-09-30/10-01 terhadap Postgres dev (`myameta_survey`, port 5434,
skala data dev: 4 users, 12 campaigns, 5 responses, 16 answers, 2 export_jobs):

| Langkah | Waktu terukur |
|---|---|
| `pg_dump --format=custom` (backup penuh) | **1.13 detik** |
| `pg_restore` ke database scratch terpisah | **0.62 detik** |
| **Total (RTO pada skala data ini)** | **~1.75 detik** |

Verifikasi setelah restore: jumlah baris `users`/`campaigns`/`responses`/
`answers`/`export_jobs` di database hasil restore **cocok persis** dengan
sumber (lihat tabel di atas — tidak ada selisih). Foreign key constraint
tidak ada yang gagal saat restore (kalau data korup/tidak lengkap, restore
akan error di titik itu).

**Catatan skala**: angka di atas dari dataset dev yang sangat kecil.
`RTO` produksi (100.000+ respons proyeksi FSD §26.1) akan jauh lebih besar
— runbook ini WAJIB diperbarui dengan angka dari drill di data
production-equivalent sebelum Release 1 (bagian dari Performance Test
§26, di luar cakupan paket G yang cuma memvalidasi MEKANISMENYA benar).

### Cara mengulang drill (kapan pun perlu, bukan cuma sekali)

```bash
# 1. Backup
php artisan backup:database

# 2. Buat database scratch (JANGAN PERNAH restore langsung ke database
#    production -- FSD §24 "jangan melakukan backup test langsung ke
#    production database")
psql -h <host> -p <port> -U postgres -d postgres -c "CREATE DATABASE <nama>_restore_drill OWNER <user>;"

# 3. Restore ke situ
pg_restore -h <host> -p <port> -U <user> -d <nama>_restore_drill --no-owner --no-privileges storage/app/private/backups/db-<timestamp>.dump

# 4. Verifikasi jumlah baris tabel kunci cocok dengan sumber, lalu hapus
#    database scratch.
psql -h <host> -p <port> -U postgres -d postgres -c "DROP DATABASE <nama>_restore_drill;"
```

## 3. Runbook insiden

### DB failure (PostgreSQL tidak bisa diakses)
1. Cek `GET /api/health/deep` (role `technical_admin`) — field `database.connected`.
2. Cek proses PostgreSQL masih hidup di server (`pg_ctl status` / systemd/service manager).
3. Cek log PostgreSQL untuk penyebab (disk penuh, crash, config salah).
4. Kalau data korup/hilang: restore dari backup terbaru (§2 di atas) ke
   instance baru, **jangan timpa langsung** — validasi dulu di database
   scratch, baru pindahkan setelah yakin.
5. Setelah pulih: jalankan `php artisan migrate:status` untuk pastikan
   skema konsisten sebelum menerima traffic lagi.

### Redis failure
1. Cek `GET /api/health/deep` — field `redis.connected`.
2. Redis di sini dipakai session/cache/queue (FSD §19 "Redis dianggap
   cache/session/queue, bukan sumber tunggal data final") — TIDAK ada data
   final yang hilang permanen kalau Redis restart bersih, tapi:
   - Semua sesi login aktif akan ter-logout (SESSION_DRIVER=redis).
   - Job yang masih di queue Redis (belum diproses) akan hilang kalau
     Redis di-restart tanpa persistence (RDB/AOF) aktif.
3. Nyalakan ulang Redis, verifikasi `redis-cli ping`.
4. Cek job yang mungkin hilang: bandingkan `export_jobs`/`sync_runs` dengan
   status `queued`/`processing` yang tidak pernah maju — re-trigger manual
   kalau perlu (export: user tinggal klik ulang; sync: jadwal harian akan
   menyusul otomatis, atau trigger manual via endpoint sync).

### SSO failure (Portal tidak bisa dihubungi)
1. Cek log `SsoController` (`Log::warning('SSO callback gagal', ...)`)
   untuk pola error (timeout, 5xx dari Portal, token tidak valid).
2. User yang SUDAH login (sesi aktif) TIDAK terdampak — cuma proses login
   BARU yang gagal.
3. `/auth/dev-login` (environment local SAJA) bisa dipakai sebagai jalur
   darurat login untuk investigasi di lingkungan non-production kalau SSO
   Portal sedang down saat development/staging.
4. Di production: tidak ada bypass SSO (sesuai desain keamanan) — insiden
   ini murni menunggu Portal pulih, komunikasikan ke pengguna via kanal lain.

### Disk full
1. Cek `GET /api/health/deep` — field `disk_free_bytes`.
2. Penyebab paling mungkin di aplikasi ini: file export (`storage/app/private/exports/`)
   menumpuk (harusnya dibersihkan otomatis oleh `exports:prune-expired`
   tiap jam — cek scheduler jalan dengan benar), atau backup database
   (§1, retensi 7 hari, harusnya juga otomatis terbatas).
3. Langkah darurat: jalankan manual `php artisan exports:prune-expired`
   dan periksa `storage/app/private/backups/` untuk file lebih tua dari
   retensi yang belum terhapus (jalankan `php artisan backup:database`
   sekali untuk memicu prune-nya, atau hapus manual file terlalu lama).
4. Jangan hapus file backup TERBARU meski disk penuh — pastikan minimal
   satu backup valid tersisa sebelum membersihkan apa pun.

### Queue stuck (job menumpuk tidak diproses)
1. Cek `GET /api/health/deep` — field `queues.{exports,notifications,default}`
   (panjang antrean per nama queue) dan `failed_jobs` (jumlah job yang
   sudah gagal permanen).
2. Pastikan proses `php artisan queue:work` benar-benar berjalan di server
   (bisa mati tanpa proses supervisor yang me-restart otomatis -- di
   production WAJIB dijalankan di bawah supervisor semacam systemd/Supervisor,
   bukan dijalankan manual di terminal).
3. Kalau worker hidup tapi antrean tidak berkurang: cek log worker untuk
   exception berulang (kemungkinan satu job "racun" yang terus gagal dan
   memblokir job setelahnya tergantung strategi retry).
4. `php artisan queue:failed` untuk lihat daftar job gagal permanen,
   `php artisan queue:retry all` untuk coba ulang semua, atau
   `php artisan queue:forget <id>` untuk job tertentu yang memang tidak
   valid lagi (mis. campaign yang jadi target sudah dihapus).
