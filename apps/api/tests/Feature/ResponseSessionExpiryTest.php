<?php

namespace Tests\Feature;

use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Questionnaires\Models\QuestionnaireSnapshot;
use App\Domains\Respondents\Models\ResponseSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResponseSessionExpiryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function saving_a_draft_refreshes_the_rolling_ttl(): void
    {
        $snapshot = QuestionnaireSnapshot::factory()->create(['structure_json' => ['sections' => []]]);
        $session = ResponseSession::factory()->create([
            'snapshot_id' => $snapshot->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->getJson("/api/responses/{$session->id}")->assertOk();
        $this->putJson(
            "/api/responses/{$session->id}/draft",
            ['answers' => []],
            ['If-Match' => '0'],
        )->assertOk();

        $refreshed = $session->fresh()->expires_at;
        $this->assertTrue($refreshed->greaterThan(now()->addDays(6)));
    }

    #[Test]
    public function command_expires_stale_draft_sessions_and_cascades_identified_eligibility(): void
    {
        $eligibility = Eligibility::factory()->create(['status' => Eligibility::STATUS_STARTED]);
        $staleIdentified = ResponseSession::factory()->identified()->create([
            'eligibility_id' => $eligibility->id,
            'status' => ResponseSession::STATUS_DRAFT,
            'expires_at' => now()->subMinute(),
        ]);

        $staleAnonymous = ResponseSession::factory()->create([
            'status' => ResponseSession::STATUS_DRAFT,
            'expires_at' => now()->subMinute(),
        ]);

        $notYetStale = ResponseSession::factory()->create([
            'status' => ResponseSession::STATUS_DRAFT,
            'expires_at' => now()->addDay(),
        ]);

        $alreadyCompleted = ResponseSession::factory()->create([
            'status' => ResponseSession::STATUS_COMPLETED,
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('responses:expire-stale-sessions')->assertSuccessful();

        $this->assertSame(ResponseSession::STATUS_EXPIRED, $staleIdentified->fresh()->status);
        $this->assertSame(Eligibility::STATUS_EXPIRED, $eligibility->fresh()->status);
        $this->assertNotNull($eligibility->fresh()->expired_at);

        $this->assertSame(ResponseSession::STATUS_EXPIRED, $staleAnonymous->fresh()->status);

        $this->assertSame(ResponseSession::STATUS_DRAFT, $notYetStale->fresh()->status);
        $this->assertSame(ResponseSession::STATUS_COMPLETED, $alreadyCompleted->fresh()->status);
    }

    #[Test]
    public function command_does_not_expire_an_eligibility_that_is_already_terminal(): void
    {
        $eligibility = Eligibility::factory()->create(['status' => Eligibility::STATUS_COMPLETED]);
        $session = ResponseSession::factory()->identified()->create([
            'eligibility_id' => $eligibility->id,
            'status' => ResponseSession::STATUS_DRAFT,
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('responses:expire-stale-sessions')->assertSuccessful();

        $this->assertSame(ResponseSession::STATUS_EXPIRED, $session->fresh()->status);
        $this->assertSame(Eligibility::STATUS_COMPLETED, $eligibility->fresh()->status);
    }
}
