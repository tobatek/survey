<?php

namespace Tests\Feature;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\QuestionnaireSnapshot;
use App\Domains\Respondents\Models\Answer;
use App\Domains\Respondents\Models\SurveyResponse;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// FSD §17.6/§12.2/§5.3/§21 (Fase 5 slice 6 bagian 1).
class AnalyticsCommentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $analyst;

    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['tenant_id' => 'default']);
        $this->admin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());

        $this->analyst = User::factory()->create(['tenant_id' => 'default']);
        $this->analyst->roles()->attach(Role::query()->where('code', Role::RAW_DATA_ANALYST)->first());

        $snapshot = QuestionnaireSnapshot::factory()->create([
            'structure_json' => [
                'sections' => [[
                    'code' => 'S1',
                    'title' => 'Bagian 1',
                    'questions' => [
                        ['code' => 'Q1', 'type' => 'long_text', 'text' => 'Saran', 'options' => [], 'matrix_rows' => []],
                        ['code' => 'Q2', 'type' => 'single_choice', 'text' => 'Pilihan', 'options' => [['code' => 'A', 'label' => 'A', 'is_positive' => false]], 'matrix_rows' => []],
                    ],
                ]],
            ],
        ]);

        $this->campaign = Campaign::factory()->create([
            'tenant_id' => 'default',
            'snapshot_id' => $snapshot->id,
            'minimum_segment_size' => 1,
        ]);
    }

    private function makeComment(string $text, array $segment = ['faculty_name' => 'Teknik', 'program_study_code' => 'TI']): Answer
    {
        $response = SurveyResponse::factory()->create([
            'campaign_id' => $this->campaign->id,
            'snapshot_id' => $this->campaign->snapshot_id,
            'segment_snapshot_json' => $segment,
        ]);

        return Answer::factory()->create([
            'response_id' => $response->id,
            'snapshot_question_code' => 'Q1',
            'value_type' => 'long_text',
            'text_value' => $text,
        ]);
    }

    #[Test]
    public function lpm_admin_without_raw_data_analyst_role_cannot_access_comments(): void
    {
        $this->makeComment('Komentar rahasia sekali');

        $response = $this->actingAs($this->admin)
            ->getJson("/api/analytics/campaigns/{$this->campaign->id}/comments?question_code=Q1");

        $response->assertStatus(403);
    }

    #[Test]
    public function raw_data_analyst_role_can_access_comments(): void
    {
        $this->makeComment('Komentar biasa');

        $response = $this->actingAs($this->analyst)
            ->getJson("/api/analytics/campaigns/{$this->campaign->id}/comments?question_code=Q1");

        $response->assertOk();
    }

    #[Test]
    public function non_text_question_code_returns_422(): void
    {
        $response = $this->actingAs($this->analyst)
            ->getJson("/api/analytics/campaigns/{$this->campaign->id}/comments?question_code=Q2");

        $response->assertStatus(422);
    }

    #[Test]
    public function unknown_question_code_returns_422(): void
    {
        $response = $this->actingAs($this->analyst)
            ->getJson("/api/analytics/campaigns/{$this->campaign->id}/comments?question_code=QX");

        $response->assertStatus(422);
    }

    #[Test]
    public function returned_rows_never_expose_identity_fields(): void
    {
        $this->makeComment('Fasilitas kurang lengkap');

        $response = $this->actingAs($this->analyst)
            ->getJson("/api/analytics/campaigns/{$this->campaign->id}/comments?question_code=Q1");

        $response->assertOk();
        $row = $response->json('data.data.0');

        $this->assertSame(['id', 'question_code', 'text', 'submitted_at', 'segment'], array_keys($row));
        $this->assertSame('Fasilitas kurang lengkap', $row['text']);
        $this->assertSame(['faculty_name' => 'Teknik', 'program_study_code' => 'TI'], $row['segment']);
    }

    #[Test]
    public function listing_is_suppressed_when_matched_count_is_below_threshold(): void
    {
        $this->campaign->update(['minimum_segment_size' => 3]);
        $this->makeComment('Komentar satu');
        $this->makeComment('Komentar dua');

        $response = $this->actingAs($this->analyst)
            ->getJson("/api/analytics/campaigns/{$this->campaign->id}/comments?question_code=Q1");

        $response->assertOk();
        $response->assertJsonPath('data.suppressed', true);
        $this->assertArrayNotHasKey('data', $response->json('data'));
    }

    #[Test]
    public function search_filters_by_comment_text_content(): void
    {
        $this->makeComment('Fasilitas perlu diperbaiki');
        $this->makeComment('Dosen sangat membantu');

        $response = $this->actingAs($this->analyst)
            ->getJson("/api/analytics/campaigns/{$this->campaign->id}/comments?question_code=Q1&search=Dosen");

        $response->assertOk();
        $rows = $response->json('data.data');
        $this->assertCount(1, $rows);
        $this->assertSame('Dosen sangat membantu', $rows[0]['text']);
    }

    #[Test]
    public function pagination_meta_reflects_total_and_per_page(): void
    {
        foreach (range(1, 5) as $i) {
            $this->makeComment("Komentar ke-{$i}");
        }

        $response = $this->actingAs($this->analyst)
            ->getJson("/api/analytics/campaigns/{$this->campaign->id}/comments?question_code=Q1&per_page=2");

        $response->assertOk();
        $this->assertCount(2, $response->json('data.data'));
        $this->assertSame(5, $response->json('data.meta.total'));
        $this->assertSame(3, $response->json('data.meta.last_page'));
    }

    #[Test]
    public function cross_tenant_campaign_returns_403(): void
    {
        $otherCampaign = Campaign::factory()->create(['tenant_id' => 'other-tenant']);

        $response = $this->actingAs($this->analyst)
            ->getJson("/api/analytics/campaigns/{$otherCampaign->id}/comments?question_code=Q1");

        $response->assertStatus(403);
    }

    #[Test]
    public function every_access_is_audit_logged_without_leaking_comment_text(): void
    {
        $this->makeComment('Isi komentar yang tidak boleh muncul di audit log');

        $this->actingAs($this->analyst)
            ->getJson("/api/analytics/campaigns/{$this->campaign->id}/comments?question_code=Q1")
            ->assertOk();

        $event = AuditEvent::query()->where('action', 'analytics.comments.viewed')->first();
        $this->assertNotNull($event);
        $this->assertSame($this->analyst->id, $event->actor_id);
        $this->assertSame($this->campaign->id, $event->resource_id);

        $flattened = json_encode($event->context);
        $this->assertStringNotContainsString('Isi komentar yang tidak boleh muncul', $flattened);
    }
}
