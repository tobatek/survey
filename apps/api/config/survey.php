<?php

// Konfigurasi bisnis myAMETA Survey yang tidak masuk sebagai menu UI
// (FSD §29 Configuration) — mengelompokkan nilai default yang dipakai
// lintas domain (Analytics, Exports, Responses).

return [
    // Ambang minimum jumlah respons per segmen sebelum hasil ditampilkan
    // (FSD §12.2, RULE-A06). Default 5 sesuai baseline dokumen.
    'minimum_segment_size' => (int) env('SURVEY_MINIMUM_SEGMENT_SIZE', 5),

    // Umur signed URL unduhan ekspor sebelum kedaluwarsa (FSD §14.2).
    'export_expiry_hours' => (int) env('SURVEY_EXPORT_EXPIRY_HOURS', 48),

    // Draft response kedaluwarsa setelah tidak diakses sekian jam
    // (dipakai scheduler cleanup — FSD §18.3).
    'draft_expiry_hours' => (int) env('SURVEY_DRAFT_EXPIRY_HOURS', 72),
];
