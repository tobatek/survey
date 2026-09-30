<?php

namespace App\Domains\Analytics\Support;

// FSD §21.2 "CSV formula injection dicegah dengan escaping nilai
// berbahaya" -- mitigasi standar OWASP CSV injection. Beda dari escaping
// RFC4180 biasa (kutip/koma, itu urusan fputcsv sendiri, sudah benar
// otomatis) -- ini soal karakter yang ditafsir SEBAGAI FORMULA oleh
// Excel/Sheets saat file dibuka (=, +, -, @, tab, carriage return di awal
// sel), bukan soal delimiter. Reusable untuk dataset CSV apa pun nanti
// (Response Summary/Choice Distribution/Raw Data), bukan cuma Question
// Results (slice ini).
final class CsvFormulaInjectionGuard
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public static function escape(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        foreach (self::DANGEROUS_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return "'".$value;
            }
        }

        return $value;
    }
}
