<?php

namespace App\Domains\Analytics\Support;

// FSD §13.2/§13.3 (Fase 5 slice 3) — gate biner "tampilkan atau sembunyikan
// seluruh angka", dipakai saat hasil sudah difilter ke segmen tertentu.
// SENGAJA bukan hasil ekstraksi pola "gabung kelompok kecil ke lainnya" dari
// CampaignMonitoringAction::breakdown() (Fase 3) -- itu pola grouping banyak
// kelompok sekaligus, ini cuma keputusan ya/tidak untuk satu angka. Dua
// kebutuhan berbeda, jangan dipaksa satu abstraksi.
final class SegmentSizeGate
{
    public static function isSuppressed(int $n, int $minimumSegmentSize): bool
    {
        return $n < $minimumSegmentSize;
    }
}
