<?php

namespace Tests\Unit\Analytics;

use App\Domains\Analytics\DTO\AnalyticsFilterDTO;
use App\Domains\Analytics\Support\AnalyticsXlsxExportBuilder;
use App\Domains\Campaigns\Models\Campaign;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// FSD §14.1/§27.3 (Fase 5 slice 5) -- golden dataset gaya sama
// AnalyticsCampaignResultsTest, tapi di sini array summary/questions dibuat
// tangan langsung (bukan lewat query) karena builder ini murni transform
// array -> Spreadsheet, tidak menyentuh DB (Campaign/User dibuat via
// ->make(), bukan ->create()).
class AnalyticsXlsxExportBuilderTest extends TestCase
{
    private function campaign(): Campaign
    {
        return Campaign::factory()->make([
            'id' => 'campaign-1',
            'questionnaire_id' => 'questionnaire-1',
            'minimum_segment_size' => 5,
        ]);
    }

    private function user(): User
    {
        return User::factory()->make(['display_name' => 'Admin LPM']);
    }

    #[Test]
    public function builds_only_the_four_sheets_in_scope_for_this_slice(): void
    {
        $builder = new AnalyticsXlsxExportBuilder;

        $spreadsheet = $builder->build(
            $this->campaign(),
            'Survei Kepuasan',
            $this->user(),
            AnalyticsFilterDTO::fromArray([]),
            ['funnel' => [], 'response_rate' => ['numerator' => 0, 'denominator' => 0, 'value' => null], 'completion_rate' => ['numerator' => 0, 'denominator' => 0, 'value' => null], 'total_responses' => 0, 'last_response_at' => null],
            ['sections' => []],
        );

        $this->assertSame(
            ['Metadata', 'Response Summary', 'Question Results', 'Choice Distribution'],
            $spreadsheet->getSheetNames(),
        );
    }

    #[Test]
    public function response_summary_sheet_shows_suppressed_placeholder_when_summary_is_suppressed(): void
    {
        $builder = new AnalyticsXlsxExportBuilder;

        $spreadsheet = $builder->build(
            $this->campaign(),
            'Survei Kepuasan',
            $this->user(),
            AnalyticsFilterDTO::fromArray([]),
            ['suppressed' => true, 'minimum_segment_size' => 5],
            ['sections' => []],
        );

        $sheet = $spreadsheet->getSheetByName('Response Summary');
        $this->assertStringContainsString('disembunyikan', (string) $sheet->getCell('A1')->getValue());
    }

    #[Test]
    public function suppressed_question_has_no_rows_in_choice_distribution_sheet(): void
    {
        $builder = new AnalyticsXlsxExportBuilder;

        $questions = [
            'sections' => [[
                'code' => 'S1',
                'title' => 'Bagian 1',
                'questions' => [
                    [
                        'code' => 'Q1',
                        'text' => 'Pertanyaan disensor',
                        'type' => 'single_choice',
                        'result' => ['suppressed' => true, 'n' => 2],
                    ],
                    [
                        'code' => 'Q2',
                        'text' => 'Pertanyaan tampil',
                        'type' => 'single_choice',
                        'result' => [
                            'n' => 10,
                            'options' => [
                                ['option_code' => 'A', 'label' => 'A', 'result' => ['numerator' => 6, 'denominator' => 10, 'value' => 60.0]],
                                ['option_code' => 'B', 'label' => 'B', 'result' => ['numerator' => 4, 'denominator' => 10, 'value' => 40.0]],
                            ],
                        ],
                    ],
                ],
            ]],
        ];

        $spreadsheet = $builder->build(
            $this->campaign(),
            'Survei Kepuasan',
            $this->user(),
            AnalyticsFilterDTO::fromArray([]),
            ['funnel' => [], 'response_rate' => ['numerator' => 0, 'denominator' => 0, 'value' => null], 'completion_rate' => ['numerator' => 0, 'denominator' => 0, 'value' => null], 'total_responses' => 0, 'last_response_at' => null],
            $questions,
        );

        $sheet = $spreadsheet->getSheetByName('Choice Distribution');
        $questionCodes = [];
        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $questionCodes[] = $sheet->getCell("A{$row}")->getValue();
        }

        $this->assertNotContains('Q1', $questionCodes);
        $this->assertContains('Q2', $questionCodes);
        $this->assertCount(2, array_filter($questionCodes, fn ($code) => $code === 'Q2'));
    }

    #[Test]
    public function matrix_question_writes_one_row_per_row_option_combination_and_skips_suppressed_rows(): void
    {
        $builder = new AnalyticsXlsxExportBuilder;

        $questions = [
            'sections' => [[
                'code' => 'S1',
                'title' => 'Bagian 1',
                'questions' => [[
                    'code' => 'Q4',
                    'text' => 'Matrix',
                    'type' => 'matrix',
                    'result' => [
                        'rows' => [
                            [
                                'row_code' => 'R1',
                                'label' => 'Baris 1',
                                'n' => 10,
                                'mean' => 3.0,
                                'median' => 3.0,
                                'positive_rate' => ['numerator' => 5, 'denominator' => 10, 'value' => 50.0],
                                'options' => [
                                    ['option_code' => 'L1', 'label' => 'L1', 'result' => ['numerator' => 5, 'denominator' => 10, 'value' => 50.0]],
                                ],
                            ],
                            ['row_code' => 'R2', 'label' => 'Baris 2', 'suppressed' => true, 'n' => 2],
                        ],
                    ],
                ]],
            ]],
        ];

        $spreadsheet = $builder->build(
            $this->campaign(),
            'Survei Kepuasan',
            $this->user(),
            AnalyticsFilterDTO::fromArray([]),
            ['funnel' => [], 'response_rate' => ['numerator' => 0, 'denominator' => 0, 'value' => null], 'completion_rate' => ['numerator' => 0, 'denominator' => 0, 'value' => null], 'total_responses' => 0, 'last_response_at' => null],
            $questions,
        );

        $sheet = $spreadsheet->getSheetByName('Choice Distribution');
        $rowCodes = [];
        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $rowCodes[] = $sheet->getCell("B{$row}")->getValue();
        }

        $this->assertSame(['R1'], $rowCodes);
    }
}
