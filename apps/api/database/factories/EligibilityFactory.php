<?php

namespace Database\Factories;

use App\Domains\Campaigns\Models\AudienceMember;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\Eligibility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Eligibility>
 */
class EligibilityFactory extends Factory
{
    protected $model = Eligibility::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'audience_member_id' => AudienceMember::factory(),
            'status' => Eligibility::STATUS_ELIGIBLE,
        ];
    }
}
