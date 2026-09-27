<?php

namespace Tests\Unit\Analytics;

use App\Domains\Analytics\Support\AnalyticsFormulas;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// FSD §27.3 -- golden dataset: angka di sini dihitung manual dulu sebelum
// jadi assertion, bukan disalin dari output kode. Skenario "not applicable
// exclusion", "blank/skipped-by-branch", "filter segmentation", dan
// "threshold suppression" SENGAJA tidak diuji di sini -- itu tanggung
// jawab layer query (Fase 5 slice 2/3) yang memfilter data SEBELUM
// dikirim ke fungsi murni ini, bukan tanggung jawab AnalyticsFormulas
// sendiri.
class AnalyticsFormulasTest extends TestCase
{
    #[Test]
    public function response_rate_divides_completed_eligibility_by_eligible_invited(): void
    {
        // 15 dari 20 undangan menyelesaikan survei = 75%.
        $result = AnalyticsFormulas::responseRate(15, 20);

        $this->assertSame(15, $result['numerator']);
        $this->assertSame(20, $result['denominator']);
        $this->assertSame(75.0, $result['value']);
    }

    #[Test]
    public function completion_rate_divides_completed_sessions_by_started_sessions(): void
    {
        // Basis datanya beda dari response rate (ResponseSession, bukan
        // Eligibility) walau rumus matematisnya sama -- 8 dari 10 sesi yang
        // dimulai benar-benar selesai = 80%.
        $result = AnalyticsFormulas::completionRate(8, 10);

        $this->assertSame(80.0, $result['value']);
    }

    #[Test]
    public function single_choice_percentage_divides_option_count_by_valid_answers(): void
    {
        // 6 dari 24 jawaban valid memilih opsi ini = 25%.
        $result = AnalyticsFormulas::singleChoicePercentage(6, 24);

        $this->assertSame(25.0, $result['value']);
    }

    #[Test]
    public function multiple_choice_percentages_can_sum_above_100_percent(): void
    {
        // 4 responden punya jawaban valid; opsi A dipilih 3 orang, B oleh 2
        // orang, C oleh 1 orang (boleh pilih lebih dari satu opsi) --
        // total lintas opsi 150%, BUKAN error, sesuai FSD §13.4.
        $a = AnalyticsFormulas::multipleChoicePercentage(3, 4);
        $b = AnalyticsFormulas::multipleChoicePercentage(2, 4);
        $c = AnalyticsFormulas::multipleChoicePercentage(1, 4);

        $this->assertSame(75.0, $a['value']);
        $this->assertSame(50.0, $b['value']);
        $this->assertSame(25.0, $c['value']);
        $this->assertGreaterThan(100.0, $a['value'] + $b['value'] + $c['value']);
    }

    #[Test]
    public function positive_rate_divides_positive_answers_by_all_scored_answers(): void
    {
        // 7 dari 10 jawaban berskala tergolong opsi "positif" di snapshot = 70%.
        $result = AnalyticsFormulas::positiveRate(7, 10);

        $this->assertSame(70.0, $result['value']);
    }

    #[Test]
    public function percentage_value_is_null_when_denominator_is_zero(): void
    {
        // null (bukan 0.0) -- beda jelas dari "0% beneran".
        $result = AnalyticsFormulas::responseRate(0, 0);

        $this->assertNull($result['value']);
        $this->assertSame(0, $result['numerator']);
        $this->assertSame(0, $result['denominator']);
    }

    #[Test]
    public function percentage_value_keeps_full_precision_without_hidden_rounding(): void
    {
        // 1/3 x 100 -- sengaja angka yang tidak bulat, membuktikan fungsi
        // tidak diam-diam membulatkan (FSD §13.6: pembulatan itu urusan
        // tampilan, bukan fungsi rumus).
        $result = AnalyticsFormulas::singleChoicePercentage(1, 3);

        $this->assertSame((1 / 3) * 100, $result['value']);
        $this->assertNotEquals(round($result['value'], 2), $result['value']);
    }

    #[Test]
    public function mean_averages_a_list_of_scores(): void
    {
        $this->assertSame(3.0, AnalyticsFormulas::mean([1, 2, 3, 4, 5]));
    }

    #[Test]
    public function mean_is_null_for_an_empty_list(): void
    {
        $this->assertNull(AnalyticsFormulas::mean([]));
    }

    #[Test]
    public function median_of_an_odd_count_is_the_middle_value(): void
    {
        // Sengaja tidak urut -- median harus mengurutkan dulu.
        $this->assertSame(3.0, AnalyticsFormulas::median([5, 1, 3, 2, 4]));
    }

    #[Test]
    public function median_of_an_even_count_averages_the_two_middle_values(): void
    {
        $this->assertSame(2.5, AnalyticsFormulas::median([4, 1, 3, 2]));
    }

    #[Test]
    public function median_is_null_for_an_empty_list(): void
    {
        $this->assertNull(AnalyticsFormulas::median([]));
    }
}
