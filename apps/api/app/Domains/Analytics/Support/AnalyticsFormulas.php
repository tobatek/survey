<?php

namespace App\Domains\Analytics\Support;

// FSD §13.4 -- kumpulan rumus analitik sebagai fungsi murni (tidak
// menyentuh DB/Eloquent sama sekali). Query nyata yang menghasilkan
// numerator/denominator/array skor itu tanggung jawab layer di atasnya
// (Fase 5 slice 2+), termasuk pengecualian not_applicable/skipped-by-
// branch/blank -- fungsi di sini menerima data yang SUDAH difilter.
//
// Semua rumus persentase punya bentuk matematis sama (numerator/denominator
// x 100), tapi sengaja diberi method bernama eksplisit per FSD (bukan satu
// fungsi generik) supaya pemanggil tidak salah pasang numerator/denominator
// antar rumus yang basis datanya berbeda (mis. response rate dari
// Eligibility, completion rate dari ResponseSession).
final class AnalyticsFormulas
{
    /**
     * @return array{numerator: int, denominator: int, value: float|null}
     */
    public static function responseRate(int $completedEligibility, int $eligibleInvited): array
    {
        return self::percentage($completedEligibility, $eligibleInvited);
    }

    /**
     * @return array{numerator: int, denominator: int, value: float|null}
     */
    public static function completionRate(int $completedSessions, int $startedSessions): array
    {
        return self::percentage($completedSessions, $startedSessions);
    }

    /**
     * @return array{numerator: int, denominator: int, value: float|null}
     */
    public static function singleChoicePercentage(int $optionCount, int $validAnswerCount): array
    {
        return self::percentage($optionCount, $validAnswerCount);
    }

    /**
     * Boleh >100% total lintas opsi karena satu responden bisa memilih
     * lebih dari satu opsi -- denominator tetap jumlah responden yang
     * punya jawaban valid, bukan jumlah total pilihan.
     *
     * @return array{numerator: int, denominator: int, value: float|null}
     */
    public static function multipleChoicePercentage(int $respondentsSelectingOption, int $respondentsWithValidAnswer): array
    {
        return self::percentage($respondentsSelectingOption, $respondentsWithValidAnswer);
    }

    /**
     * @return array{numerator: int, denominator: int, value: float|null}
     */
    public static function positiveRate(int $positiveValidAnswers, int $allScoredValidAnswers): array
    {
        return self::percentage($positiveValidAnswers, $allScoredValidAnswers);
    }

    /**
     * @param  array<int, float|int>  $scores  sudah difilter (tanpa not_applicable/skipped/blank)
     */
    public static function mean(array $scores): ?float
    {
        if ($scores === []) {
            return null;
        }

        return array_sum($scores) / count($scores);
    }

    /**
     * @param  array<int, float|int>  $scores  sudah difilter (tanpa not_applicable/skipped/blank)
     */
    public static function median(array $scores): ?float
    {
        if ($scores === []) {
            return null;
        }

        sort($scores);
        $count = count($scores);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $scores[$middle];
        }

        return ($scores[$middle - 1] + $scores[$middle]) / 2;
    }

    /**
     * @return array{numerator: int, denominator: int, value: float|null}
     */
    private static function percentage(int $numerator, int $denominator): array
    {
        return [
            'numerator' => $numerator,
            'denominator' => $denominator,
            'value' => $denominator > 0 ? ($numerator / $denominator) * 100 : null,
        ];
    }
}
