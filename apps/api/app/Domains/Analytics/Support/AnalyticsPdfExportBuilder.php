<?php

namespace App\Domains\Analytics\Support;

use App\Domains\Analytics\DTO\AnalyticsFilterDTO;
use App\Domains\Campaigns\Models\Campaign;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;

// FSD §14.1 (Fase 5 slice 6 bagian 3) -- cover, metadata/filter, funnel
// (tabel + chart lewat FunnelBarChartRenderer), Question Results (tabel
// teks lewat QuestionResultRowFormatter -- sumber sama dengan XLSX/CSV),
// catatan rumus/denominator, generated_at+version. TIDAK ada chart per-
// tipe-pertanyaan dan TIDAK ada komentar (keputusan scope slice ini).
final class AnalyticsPdfExportBuilder
{
    private const VERSION = 'v1';

    public function __construct(private readonly FunnelBarChartRenderer $chartRenderer) {}

    /**
     * @param  array<string, mixed>  $summary  hasil CampaignAnalyticsSummaryAction::execute()
     * @param  array<string, mixed>  $questions  hasil CampaignQuestionResultsAction::execute()
     */
    public function build(
        Campaign $campaign,
        string $questionnaireTitle,
        User $generatedBy,
        AnalyticsFilterDTO $filter,
        array $summary,
        array $questions,
    ): string {
        $chart = $this->chartRenderer->render($campaign, $questionnaireTitle, $filter, $summary);

        $filterLines = [];
        foreach ($filter->normalized() as $key => $value) {
            if ($value === null || $value === []) {
                continue;
            }

            $filterLines[$key] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        $pdf = Pdf::loadView('exports.analytics-pdf', [
            'campaign' => $campaign,
            'questionnaireTitle' => $questionnaireTitle,
            'generatedByLabel' => $generatedBy->display_name ?? $generatedBy->email,
            'generatedAt' => now()->toIso8601String(),
            'version' => self::VERSION,
            'filterLines' => $filterLines,
            'summary' => $summary,
            'questionRows' => QuestionResultRowFormatter::rows($questions),
            'chartBase64' => base64_encode($chart),
        ]);

        return $pdf->output();
    }
}
