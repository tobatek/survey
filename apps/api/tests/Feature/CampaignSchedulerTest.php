<?php

namespace Tests\Feature;

use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Models\QuestionnaireSnapshot;
use App\Domains\Students\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampaignSchedulerTest extends TestCase
{
    use RefreshDatabase;

    // Bug ditemukan 2026-10-01 (uji end-to-end manual) -- lihat komentar
    // serupa di CampaignActivationTest/CampaignInvitationTest.
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
    public function activate_due_command_activates_draft_campaigns_whose_start_has_passed(): void
    {
        Student::factory()->count(2)->create(['tenant_id' => 'default', 'program_study_code' => 'TI']);

        $due = Campaign::factory()->create([
            'tenant_id' => 'default',
            'questionnaire_id' => $this->finalizedQuestionnaire()->id,
            'status' => Campaign::STATUS_DRAFT,
            'starts_at' => now()->subMinute(),
            'target_rule_json' => ['program_study_codes' => ['TI']],
        ]);

        $notYetDue = Campaign::factory()->create([
            'tenant_id' => 'default',
            'status' => Campaign::STATUS_DRAFT,
            'starts_at' => now()->addDay(),
            'target_rule_json' => ['program_study_codes' => ['TI']],
        ]);

        $this->artisan('campaigns:activate-due')->assertSuccessful();

        $this->assertSame(Campaign::STATUS_ACTIVE, $due->fresh()->status);
        $this->assertSame(Campaign::STATUS_DRAFT, $notYetDue->fresh()->status);
    }

    #[Test]
    public function activate_due_command_skips_campaigns_without_target_without_failing(): void
    {
        $withoutTarget = Campaign::factory()->create([
            'tenant_id' => 'default',
            'status' => Campaign::STATUS_DRAFT,
            'starts_at' => now()->subMinute(),
            'target_rule_json' => null,
        ]);

        $this->artisan('campaigns:activate-due')->assertSuccessful();

        $this->assertSame(Campaign::STATUS_DRAFT, $withoutTarget->fresh()->status);
    }

    #[Test]
    public function close_expired_command_closes_active_campaigns_past_their_end_date(): void
    {
        $expired = Campaign::factory()->create([
            'tenant_id' => 'default',
            'status' => Campaign::STATUS_ACTIVE,
            'ends_at' => now()->subMinute(),
        ]);

        $stillOpen = Campaign::factory()->create([
            'tenant_id' => 'default',
            'status' => Campaign::STATUS_ACTIVE,
            'ends_at' => now()->addDay(),
        ]);

        $this->artisan('campaigns:close-expired')->assertSuccessful();

        $this->assertSame(Campaign::STATUS_CLOSED, $expired->fresh()->status);
        $this->assertSame(Campaign::STATUS_ACTIVE, $stillOpen->fresh()->status);
    }
}
