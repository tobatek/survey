<?php

// Konfigurasi integrasi ke Portal SSO existing.
// myAMETA Survey berperan sebagai "App Luar" — lihat docs/15_SSO_PORTAL_VALIDASI_SEPARATION_PLAN.md
// untuk kontrak lengkap /sso/authorize, /sso/verify, dan /sso/session-status.

return [
    'portal_base_url' => env('SSO_PORTAL_BASE_URL'),

    // Halaman /sso/authorize adalah halaman SPA Portal (browser), bisa beda host
    // dari API Portal di atas. Kosong = pakai portal_base_url.
    'portal_frontend_url' => env('SSO_PORTAL_FRONTEND_URL'),

    'client_id' => env('SSO_APP_CLIENT_ID'),

    // Token aplikasi yang didapat sekali dari halaman admin Portal
    // "Integrasi Aplikasi". Kunci AES diturunkan dari token ini via HKDF
    // (info="sso-verify-v1"), bukan dipakai langsung sebagai key.
    'app_token' => env('SSO_APP_TOKEN'),

    'redirect_uri' => env('SSO_REDIRECT_URI'),

    // Interval polling session-status (menit) — FSD dokumen 15 merekomendasikan 1-5 menit.
    'session_status_poll_minutes' => env('SSO_SESSION_STATUS_POLL_MINUTES', 5),
];
