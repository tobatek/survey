<?php

namespace Tests\Feature;

use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\QuestionnaireSnapshot;
use App\Domains\Respondents\Models\Answer;
use App\Domains\Respondents\Models\AnswerChoice;
use App\Domains\Respondents\Models\ResponseSession;
use App\Domains\Respondents\Models\SurveyResponse;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// FSD §13.4/§27.3 -- golden dataset: nilai dihitung manual dulu (lihat
// komentar per pertanyaan) sebelum dijadikan assertion, konsisten pola
// AnalyticsFormulasTest (slice 1).
class AnalyticsCampaignResultsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $respondent;

    private Campaign $campaign;

    private QuestionnaireSnapshot $snapshot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['tenant_id' => 'default']);
        $this->admin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());

        $this->respondent = User::factory()->create(['tenant_id' => 'default']);

        $this->snapshot = QuestionnaireSnapshot::factory()->create([
            'structure_json' => [
                'sections' => [[
                    'code' => 'S1',
                    'title' => 'Bagian 1',
                    'questions' => [
                        [
                            'code' => 'Q1', 'type' => 'single_choice', 'text' => 'Pilihan tunggal',
                            'options' => [
                                ['code' => 'OPT_A', 'label' => 'A', 'is_positive' => false],
                                ['code' => 'OPT_B', 'label' => 'B', 'is_positive' => false],
                            ],
                            'matrix_rows' => [],
                        ],
                        [
                            'code' => 'Q2', 'type' => 'multiple_choice', 'text' => 'Pilihan ganda',
                            'options' => [
                                ['code' => 'M1', 'label' => 'M1', 'is_positive' => false],
                                ['code' => 'M2', 'label' => 'M2', 'is_positive' => false],
                                ['code' => 'M3', 'label' => 'M3', 'is_positive' => false],
                            ],
                            'matrix_rows' => [],
                        ],
                        [
                            'code' => 'Q3', 'type' => 'likert', 'text' => 'Skala kepuasan',
                            'options' => [
                                ['code' => 'L1', 'label' => 'Sangat tidak puas', 'is_positive' => false],
                                ['code' => 'L2', 'label' => 'Tidak puas', 'is_positive' => false],
                                ['code' => 'L3', 'label' => 'Puas', 'is_positive' => true],
                                ['code' => 'L4', 'label' => 'Sangat puas', 'is_positive' => true],
                            ],
                            'matrix_rows' => [],
                        ],
                        [
                            'code' => 'Q4', 'type' => 'matrix', 'text' => 'Matrix layanan',
                            'options' => [
                                ['code' => 'L1', 'label' => 'Sangat tidak puas', 'is_positive' => false],
                                ['code' => 'L2', 'label' => 'Tidak puas', 'is_positive' => false],
                                ['code' => 'L3', 'label' => 'Puas', 'is_positive' => true],
                                ['code' => 'L4', 'label' => 'Sangat puas', 'is_positive' => true],
                            ],
                            'matrix_rows' => [
                                ['code' => 'R1', 'label' => 'Dosen'],
                                ['code' => 'R2', 'label' => 'Fasilitas'],
                            ],
                        ],
                        [
                            'code' => 'Q5', 'type' => 'number', 'text' => 'Jumlah SKS',
                            'options' => [], 'matrix_rows' => [],
                        ],
                        [
                            'code' => 'Q6', 'type' => 'date', 'text' => 'Tanggal lulus',
                            'options' => [], 'matrix_rows' => [],
                        ],
                        [
                            'code' => 'Q7', 'type' => 'short_text', 'text' => 'Saran',
                            'options' => [], 'matrix_rows' => [],
                        ],
                    ],
                ]],
            ],
        ]);

        $this->campaign = Campaign::factory()->create([
            'tenant_id' => 'default',
            'status' => Campaign::STATUS_CLOSED,
            'snapshot_id' => $this->snapshot->id,
            'denominator_count' => 4,
        ]);
    }

    private function makeResponse(): SurveyResponse
    {
        return SurveyResponse::factory()->create([
            'campaign_id' => $this->campaign->id,
            'snapshot_id' => $this->snapshot->id,
        ]);
    }

    private function makeAnswer(SurveyResponse $response, string $code, array $overrides = []): Answer
    {
        return Answer::factory()->create(array_merge([
            'response_id' => $response->id,
            'snapshot_question_code' => $code,
            'text_value' => null,
        ], $overrides));
    }

    private function seedGoldenDataset(): void
    {
        // Q1 single_choice: 3 jawaban valid (OPT_A x2, OPT_B x1) + 1 NA.
        $r1 = $this->makeResponse();
        $r2 = $this->makeResponse();
        $r3 = $this->makeResponse();
        $r4 = $this->makeResponse();

        $a = $this->makeAnswer($r1, 'Q1', ['value_type' => 'single_choice']);
        AnswerChoice::factory()->create(['answer_id' => $a->id, 'option_code' => 'OPT_A']);
        $a = $this->makeAnswer($r2, 'Q1', ['value_type' => 'single_choice']);
        AnswerChoice::factory()->create(['answer_id' => $a->id, 'option_code' => 'OPT_A']);
        $a = $this->makeAnswer($r3, 'Q1', ['value_type' => 'single_choice']);
        AnswerChoice::factory()->create(['answer_id' => $a->id, 'option_code' => 'OPT_B']);
        $this->makeAnswer($r4, 'Q1', ['value_type' => 'single_choice', 'is_not_applicable' => true]);

        // Q2 multiple_choice: respondent1 pilih M1+M2, respondent2 pilih M2,
        // respondent3 NA -> respondents_with_valid_answer = 2.
        $a = $this->makeAnswer($r1, 'Q2', ['value_type' => 'multiple_choice']);
        AnswerChoice::factory()->create(['answer_id' => $a->id, 'option_code' => 'M1']);
        AnswerChoice::factory()->create(['answer_id' => $a->id, 'option_code' => 'M2']);
        $a = $this->makeAnswer($r2, 'Q2', ['value_type' => 'multiple_choice']);
        AnswerChoice::factory()->create(['answer_id' => $a->id, 'option_code' => 'M2']);
        $this->makeAnswer($r3, 'Q2', ['value_type' => 'multiple_choice', 'is_not_applicable' => true]);

        // Q3 likert: skor L1=1, L2=2, L3=3(positive), L4=4(positive).
        // Jawaban: L4, L3, L1 -> mean=(4+3+1)/3=2.6667, median=3,
        // positive_rate = 2/3 (L3+L4).
        $a = $this->makeAnswer($r1, 'Q3', ['value_type' => 'likert']);
        AnswerChoice::factory()->create(['answer_id' => $a->id, 'option_code' => 'L4', 'numeric_score' => 4]);
        $a = $this->makeAnswer($r2, 'Q3', ['value_type' => 'likert']);
        AnswerChoice::factory()->create(['answer_id' => $a->id, 'option_code' => 'L3', 'numeric_score' => 3]);
        $a = $this->makeAnswer($r3, 'Q3', ['value_type' => 'likert']);
        AnswerChoice::factory()->create(['answer_id' => $a->id, 'option_code' => 'L1', 'numeric_score' => 1]);

        // Q4 matrix row R1: L2(score2), L4(score4) -> mean=3, positive=1/2.
        // Row R2: L3(score3) saja -> mean=3, positive=1/1.
        $a = $this->makeAnswer($r1, 'Q4', ['value_type' => 'matrix', 'matrix_row_code' => 'R1']);
        AnswerChoice::factory()->create(['answer_id' => $a->id, 'option_code' => 'L2', 'numeric_score' => 2]);
        $a = $this->makeAnswer($r2, 'Q4', ['value_type' => 'matrix', 'matrix_row_code' => 'R1']);
        AnswerChoice::factory()->create(['answer_id' => $a->id, 'option_code' => 'L4', 'numeric_score' => 4]);
        $a = $this->makeAnswer($r1, 'Q4', ['value_type' => 'matrix', 'matrix_row_code' => 'R2']);
        AnswerChoice::factory()->create(['answer_id' => $a->id, 'option_code' => 'L3', 'numeric_score' => 3]);

        // Q5 number: 10, 20, 30 -> mean=20, median=20, min=10, max=30.
        $this->makeAnswer($r1, 'Q5', ['value_type' => 'number', 'numeric_value' => 10]);
        $this->makeAnswer($r2, 'Q5', ['value_type' => 'number', 'numeric_value' => 20]);
        $this->makeAnswer($r3, 'Q5', ['value_type' => 'number', 'numeric_value' => 30]);

        // Q6 date: 2026-01-01 x2, 2026-01-02 x1.
        $this->makeAnswer($r1, 'Q6', ['value_type' => 'date', 'date_value' => '2026-01-01']);
        $this->makeAnswer($r2, 'Q6', ['value_type' => 'date', 'date_value' => '2026-01-01']);
        $this->makeAnswer($r3, 'Q6', ['value_type' => 'date', 'date_value' => '2026-01-02']);

        // Q7 short_text: 2 valid, 1 NA.
        $this->makeAnswer($r1, 'Q7', ['value_type' => 'short_text', 'text_value' => 'Bagus']);
        $this->makeAnswer($r2, 'Q7', ['value_type' => 'short_text', 'text_value' => 'Cukup']);
        $this->makeAnswer($r3, 'Q7', ['value_type' => 'short_text', 'is_not_applicable' => true]);
    }

    private function questionResult(array $json, string $code): array
    {
        return collect($json['data']['sections'][0]['questions'])
            ->firstWhere('code', $code)['result'];
    }

    #[Test]
    public function list_only_shows_active_or_closed_campaigns_with_responses(): void
    {
        $this->seedGoldenDataset();

        $draftCampaign = Campaign::factory()->create(['tenant_id' => 'default', 'status' => Campaign::STATUS_DRAFT]);
        $closedWithoutResponses = Campaign::factory()->create(['tenant_id' => 'default', 'status' => Campaign::STATUS_CLOSED]);

        $response = $this->actingAs($this->admin)->getJson('/api/analytics/campaigns');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($this->campaign->id));
        $this->assertFalse($ids->contains($draftCampaign->id));
        $this->assertFalse($ids->contains($closedWithoutResponses->id));

        $row = collect($response->json('data'))->firstWhere('id', $this->campaign->id);
        $this->assertSame(4, $row['denominator_count']);
        $this->assertNull($row['last_aggregated_at']);
    }

    #[Test]
    public function summary_computes_response_rate_and_completion_rate(): void
    {
        $this->seedGoldenDataset();

        ResponseSession::factory()->create(['campaign_id' => $this->campaign->id, 'status' => ResponseSession::STATUS_COMPLETED]);
        ResponseSession::factory()->create(['campaign_id' => $this->campaign->id, 'status' => ResponseSession::STATUS_COMPLETED]);
        ResponseSession::factory()->create(['campaign_id' => $this->campaign->id, 'status' => ResponseSession::STATUS_EXPIRED]);

        $response = $this->actingAs($this->admin)->getJson("/api/analytics/campaigns/{$this->campaign->id}/summary");

        $response->assertOk();
        $response->assertJsonPath('data.total_responses', 4);
        $response->assertJsonPath('data.completion_rate.numerator', 2);
        $response->assertJsonPath('data.completion_rate.denominator', 3);
    }

    #[Test]
    public function single_choice_question_percentages_match_golden_dataset(): void
    {
        $this->seedGoldenDataset();

        $response = $this->actingAs($this->admin)->getJson("/api/analytics/campaigns/{$this->campaign->id}/questions");
        $response->assertOk();

        $result = $this->questionResult($response->json(), 'Q1');

        $this->assertSame(3, $result['n']);
        $options = collect($result['options'])->keyBy('option_code');
        $this->assertEqualsWithDelta(66.6667, $options['OPT_A']['result']['value'], 0.001);
        $this->assertEqualsWithDelta(33.3333, $options['OPT_B']['result']['value'], 0.001);
    }

    #[Test]
    public function multiple_choice_question_percentages_can_exceed_100_total(): void
    {
        $this->seedGoldenDataset();

        $response = $this->actingAs($this->admin)->getJson("/api/analytics/campaigns/{$this->campaign->id}/questions");
        $result = $this->questionResult($response->json(), 'Q2');

        $this->assertSame(2, $result['n']);
        $options = collect($result['options'])->keyBy('option_code');
        $this->assertEquals(50.0, $options['M1']['result']['value']);
        $this->assertEquals(100.0, $options['M2']['result']['value']);
        $this->assertEquals(0.0, $options['M3']['result']['value']);
    }

    #[Test]
    public function likert_question_mean_median_and_positive_rate_match_golden_dataset(): void
    {
        $this->seedGoldenDataset();

        $response = $this->actingAs($this->admin)->getJson("/api/analytics/campaigns/{$this->campaign->id}/questions");
        $result = $this->questionResult($response->json(), 'Q3');

        $this->assertEqualsWithDelta(2.6667, $result['mean'], 0.001);
        $this->assertEquals(3.0, $result['median']);
        $this->assertEqualsWithDelta(66.6667, $result['positive_rate']['value'], 0.001);
    }

    #[Test]
    public function matrix_question_computes_per_row_results(): void
    {
        $this->seedGoldenDataset();

        $response = $this->actingAs($this->admin)->getJson("/api/analytics/campaigns/{$this->campaign->id}/questions");
        $result = $this->questionResult($response->json(), 'Q4');

        $rows = collect($result['rows'])->keyBy('row_code');
        $this->assertEquals(3.0, $rows['R1']['mean']);
        $this->assertEqualsWithDelta(50.0, $rows['R1']['positive_rate']['value'], 0.001);
        $this->assertEquals(3.0, $rows['R2']['mean']);
        $this->assertEqualsWithDelta(100.0, $rows['R2']['positive_rate']['value'], 0.001);
    }

    #[Test]
    public function number_question_returns_mean_median_min_max(): void
    {
        $this->seedGoldenDataset();

        $response = $this->actingAs($this->admin)->getJson("/api/analytics/campaigns/{$this->campaign->id}/questions");
        $result = $this->questionResult($response->json(), 'Q5');

        $this->assertSame(3, $result['n']);
        $this->assertEquals(20.0, $result['mean']);
        $this->assertEquals(20.0, $result['median']);
        $this->assertEquals(10.0, $result['min']);
        $this->assertEquals(30.0, $result['max']);
    }

    #[Test]
    public function date_question_returns_count_per_bucket(): void
    {
        $this->seedGoldenDataset();

        $response = $this->actingAs($this->admin)->getJson("/api/analytics/campaigns/{$this->campaign->id}/questions");
        $result = $this->questionResult($response->json(), 'Q6');

        $this->assertSame(3, $result['n']);
        $buckets = collect($result['buckets'])->keyBy('date');
        $this->assertSame(2, $buckets['2026-01-01']['count']);
        $this->assertSame(1, $buckets['2026-01-02']['count']);
    }

    #[Test]
    public function text_question_only_returns_valid_answer_count_not_content(): void
    {
        $this->seedGoldenDataset();

        $response = $this->actingAs($this->admin)->getJson("/api/analytics/campaigns/{$this->campaign->id}/questions");
        $result = $this->questionResult($response->json(), 'Q7');

        $this->assertSame(['n' => 2], $result);
    }

    #[Test]
    public function respondent_role_cannot_access_analytics_endpoints(): void
    {
        $this->seedGoldenDataset();

        $this->actingAs($this->respondent)->getJson('/api/analytics/campaigns')->assertStatus(403);
        $this->actingAs($this->respondent)->getJson("/api/analytics/campaigns/{$this->campaign->id}/summary")->assertStatus(403);
        $this->actingAs($this->respondent)->getJson("/api/analytics/campaigns/{$this->campaign->id}/questions")->assertStatus(403);
    }

    #[Test]
    public function analytics_on_another_tenants_campaign_returns_403(): void
    {
        $otherCampaign = Campaign::factory()->create(['tenant_id' => 'other-tenant']);

        $this->actingAs($this->admin)
            ->getJson("/api/analytics/campaigns/{$otherCampaign->id}/summary")
            ->assertStatus(403);
    }
}
