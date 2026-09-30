<?php

namespace App\Domains\Analytics\Support;

use App\Domains\Analytics\DTO\AnalyticsFilterDTO;
use App\Domains\Campaigns\Models\Campaign;

// FSD §14.1 (Fase 5 slice 6 bagian 3-4) -- bar chart funnel respons, dipakai
// BERSAMA oleh AnalyticsPdfExportBuilder (chart ditempel base64 di halaman)
// DAN sebagai isi file export format PNG (dikembalikan apa adanya) -- satu
// sumber kebenaran chart, dua format konsumen.
//
// Pakai PHP GD bawaan (sudah aktif di server, tidak nambah dependency) dan
// font bitmap built-in (imagestring, bukan TTF yang perlu di-bundle) --
// sengaja sederhana (bukan chart interaktif), cukup untuk syarat FSD PNG
// "ukuran/resolusi cukup untuk presentasi, judul, filter, n, dan sumber
// waktu tertanam" -- keempatnya dirender langsung ke gambar di sini.
final class FunnelBarChartRenderer
{
    private const WIDTH = 900;

    private const HEIGHT = 500;

    private const MARGIN = 60;

    /**
     * @param  array<string, mixed>  $summary  hasil CampaignAnalyticsSummaryAction::execute()
     */
    public function render(Campaign $campaign, string $questionnaireTitle, AnalyticsFilterDTO $filter, array $summary): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 20, 20, 20);
        $blue = imagecolorallocate($image, 37, 99, 235);
        $gray = imagecolorallocate($image, 110, 110, 110);

        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $white);
        imagestring($image, 5, self::MARGIN, 20, $questionnaireTitle.' -- Funnel Respons', $black);

        if ($summary['suppressed'] ?? false) {
            imagestring($image, 5, self::MARGIN, self::HEIGHT / 2, 'Data disembunyikan -- di bawah ambang batas minimum_segment_size', $gray);
            imagestring($image, 2, self::MARGIN, self::HEIGHT - 30, 'Dibuat: '.now()->toIso8601String(), $gray);

            return $this->output($image);
        }

        $funnel = $summary['funnel'];
        $max = max(1, max($funnel));
        $chartTop = 60;
        $chartBottom = self::HEIGHT - 90;
        $chartHeight = $chartBottom - $chartTop;
        $barCount = count($funnel);
        $barAreaWidth = self::WIDTH - (2 * self::MARGIN);
        $barWidth = intdiv($barAreaWidth, $barCount) - 20;

        $x = self::MARGIN;
        foreach ($funnel as $status => $count) {
            $barHeight = (int) round(($count / $max) * $chartHeight);
            $y = $chartBottom - $barHeight;

            imagefilledrectangle($image, $x, $y, $x + $barWidth, $chartBottom, $blue);
            imagestring($image, 3, $x, $y - 18, (string) $count, $black);
            imagestring($image, 2, $x, $chartBottom + 8, substr($status, 0, 12), $black);

            $x += $barWidth + 20;
        }

        $filterText = $this->filterSummaryText($filter);
        imagestring($image, 2, self::MARGIN, self::HEIGHT - 50, 'Filter: '.$filterText, $gray);
        imagestring($image, 2, self::MARGIN, self::HEIGHT - 30, 'Dibuat: '.now()->toIso8601String(), $gray);

        return $this->output($image);
    }

    private function filterSummaryText(AnalyticsFilterDTO $filter): string
    {
        $parts = [];
        foreach ($filter->normalized() as $key => $value) {
            if ($value === null || $value === []) {
                continue;
            }

            $parts[] = $key.'='.(is_array($value) ? implode('|', $value) : $value);
        }

        return $parts === [] ? 'tidak ada' : implode('; ', $parts);
    }

    /**
     * @param  \GdImage  $image
     */
    private function output($image): string
    {
        ob_start();
        imagepng($image);
        $content = ob_get_clean();
        imagedestroy($image);

        return $content;
    }
}
