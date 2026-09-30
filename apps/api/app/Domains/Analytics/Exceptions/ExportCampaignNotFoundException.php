<?php

namespace App\Domains\Analytics\Exceptions;

use RuntimeException;

// Dilempar GenerateAnalyticsExportJob ketika campaign sudah hilang/pindah
// tenant di antara job di-enqueue dan dieksekusi -- kegagalan PERMANEN
// (tidak retry-eligible), beda dari Throwable generik lain di job yang
// sama (disk penuh, koneksi DB, dsb) yang dianggap transient dan boleh
// diulang. Tipe khusus ini dipakai supaya failed() bisa membedakan
// keduanya tanpa menebak dari pesan/kelas exception generik.
class ExportCampaignNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Campaign untuk export job ini tidak ditemukan lagi.');
    }
}
