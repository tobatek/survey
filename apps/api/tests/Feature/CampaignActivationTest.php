<?php

namespace Tests\Feature;

use App\Domains\Campaigns\Events\CampaignActivated;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Models\QuestionnaireSnapshot;
use App\Domains\Students\Models\Student;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampaignActivationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['tenant_id' => 'default']);
        $this->admin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());
    }

    // Bug ditemukan 2026-10-01 (uji end-to-end manual via browser): campaign
    // yang dibuat lewat Campaign::factory() default punya Questionnaire
    // draft tanpa snapshot -- di dunia nyata itu tidak mungkin terjadi
    // (campaign cuma bisa dibuat dari questionnaire yang sudah finalized,
    // Fase 3 slice 1), tapi factory lama tidak merefleksikan itu sehingga
    // ActivateCampaignAction::execute() tidak pernah benar-benar diuji
    // dengan kondisi realistis (snapshot_checksum questionnaire terisi +
    // QuestionnaireSnapshot yang cocok ada) -- inilah kenapa bug
    // "snapshot_id tidak pernah diisi saat aktivasi" lolos dari semua test
    // sebelumnya.
    private function finalizedQuestionnaire(): Questionnaire
    {
        $snapshot = QuestionnaireSnapshot::factory()->create();
        $snapshot->questionnaire()->update([
            'status' => Questionnaire::STATUS_READY,
            'snapshot_checksum' => $snapshot->checksum,
        ]);

        return $snapshot->questionnaire()->first();
    }

    #[Test]
    public function activating_a_campaign_materializes_audience_and_eligibilities(): void
    {
        Event::fake();

        Student::factory()->count(5)->create(['tenant_id' => 'default', 'program_study_code' => 'TI']);

        $campaign = Campaign::factory()->create([
            'tenant_id' => 'default',
            'questionnaire_id' => $this->finalizedQuestionnaire()->id,
            'target_rule_json' => ['program_study_codes' => ['TI']],
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/campaigns/{$campaign->id}/activate");

        $response->assertOk();
        $response->assertJsonPath('data.status', Campaign::STATUS_ACTIVE);
        $response->assertJsonPath('data.denominator_count', 5);
        $this->assertNotNull($campaign->fresh()->snapshot_id);

        $this->assertDatabaseCount('audience_members', 5);
        $this->assertDatabaseCount('eligibilities', 5);
        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'status' => Campaign::STATUS_ACTIVE,
            'denominator_count' => 5,
            'activated_by' => $this->admin->id,
        ]);

        Event::assertDispatched(CampaignActivated::class, 1);
    }

    #[Test]
    public function attribute_snapshot_only_contains_categorical_fields(): void
    {
        Student::factory()->create([
            'tenant_id' => 'default',
            'program_study_code' => 'TI',
            'faculty_name' => 'Teknik',
        ]);

        $campaign = Campaign::factory()->create([
            'tenant_id' => 'default',
            'questionnaire_id' => $this->finalizedQuestionnaire()->id,
            'target_rule_json' => ['program_study_codes' => ['TI']],
        ]);

        $this->actingAs($this->admin)->postJson("/api/campaigns/{$campaign->id}/activate")->assertOk();

        $member = $campaign->audienceMembers()->first();
        $snapshot = $member->attributes_snapshot_json;

        $this->assertSame(
            ['faculty_name', 'program_study_code', 'program_study_name', 'degree_level', 'cohort_year', 'status'],
            array_keys($snapshot),
        );
        $this->assertArrayNotHasKey('name', $snapshot);
        $this->assertArrayNotHasKey('nim', $snapshot);
        $this->assertArrayNotHasKey('institutional_email', $snapshot);
    }

    #[Test]
    public function activating_twice_is_idempotent_and_dispatches_event_only_once(): void
    {
        Event::fake();

        Student::factory()->count(3)->create(['tenant_id' => 'default', 'program_study_code' => 'TI']);

        $campaign = Campaign::factory()->create([
            'tenant_id' => 'default',
            'questionnaire_id' => $this->finalizedQuestionnaire()->id,
            'target_rule_json' => ['program_study_codes' => ['TI']],
        ]);

        $this->actingAs($this->admin)->postJson("/api/campaigns/{$campaign->id}/activate")->assertOk();
        $second = $this->actingAs($this->admin)->postJson("/api/campaigns/{$campaign->id}/activate");

        $second->assertOk();
        $second->assertJsonPath('data.denominator_count', 3);

        $this->assertDatabaseCount('audience_members', 3);
        $this->assertDatabaseCount('eligibilities', 3);

        Event::assertDispatched(CampaignActivated::class, 1);
    }

    #[Test]
    public function activating_without_target_rule_is_rejected(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default', 'target_rule_json' => null]);

        $response = $this->actingAs($this->admin)->postJson("/api/campaigns/{$campaign->id}/activate");

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'target_not_set');
    }

    #[Test]
    public function activating_with_zero_matches_is_rejected(): void
    {
        $campaign = Campaign::factory()->create([
            'tenant_id' => 'default',
            'questionnaire_id' => $this->finalizedQuestionnaire()->id,
            'target_rule_json' => ['program_study_codes' => ['TIDAK-ADA']],
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/campaigns/{$campaign->id}/activate");

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'empty_target');
    }

    #[Test]
    public function activating_a_closed_campaign_is_rejected(): void
    {
        $campaign = Campaign::factory()->create([
            'tenant_id' => 'default',
            'status' => Campaign::STATUS_CLOSED,
            'target_rule_json' => ['program_study_codes' => ['TI']],
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/campaigns/{$campaign->id}/activate");

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'invalid_status');
    }

    #[Test]
    public function activating_another_tenants_campaign_returns_403(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'other-tenant']);

        $this->actingAs($this->admin)
            ->postJson("/api/campaigns/{$campaign->id}/activate")
            ->assertStatus(403);
    }
}
