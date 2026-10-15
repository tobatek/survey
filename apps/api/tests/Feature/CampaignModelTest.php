<?php

namespace Tests\Feature;

use App\Domains\Campaigns\Models\AudienceMember;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\DeliveryAttempt;
use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Students\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampaignModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function relations_and_casts_resolve_as_expected(): void
    {
        $campaign = Campaign::factory()->create([
            'target_rule_json' => ['program_study_code' => ['TI', 'SI']],
        ]);

        $student = Student::factory()->create(['tenant_id' => $campaign->tenant_id]);

        $audienceMember = AudienceMember::factory()->create([
            'campaign_id' => $campaign->id,
            'subject_type' => AudienceMember::SUBJECT_STUDENT,
            'subject_id' => $student->id,
        ]);

        $eligibility = Eligibility::factory()->create([
            'campaign_id' => $campaign->id,
            'audience_member_id' => $audienceMember->id,
        ]);

        $deliveryAttempt = DeliveryAttempt::factory()->create([
            'eligibility_id' => $eligibility->id,
        ]);

        $this->assertIsArray($campaign->target_rule_json);
        $this->assertTrue($campaign->audienceMembers->contains($audienceMember));
        $this->assertTrue($campaign->eligibilities->contains($eligibility));

        $this->assertTrue($audienceMember->subject()->is($student));
        $this->assertTrue($audienceMember->eligibility->is($eligibility));

        $this->assertTrue($eligibility->deliveryAttempts->contains($deliveryAttempt));
        $this->assertTrue($eligibility->campaign->is($campaign));
    }

    #[Test]
    public function activating_the_same_audience_member_twice_is_prevented_by_the_unique_constraint(): void
    {
        $campaign = Campaign::factory()->create();
        $student = Student::factory()->create(['tenant_id' => $campaign->tenant_id]);

        AudienceMember::factory()->create([
            'campaign_id' => $campaign->id,
            'subject_type' => AudienceMember::SUBJECT_STUDENT,
            'subject_id' => $student->id,
        ]);

        $this->expectException(QueryException::class);

        AudienceMember::factory()->create([
            'campaign_id' => $campaign->id,
            'subject_type' => AudienceMember::SUBJECT_STUDENT,
            'subject_id' => $student->id,
        ]);
    }
}
