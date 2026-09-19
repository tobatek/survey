<?php

namespace Database\Factories;

use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Questionnaires\Models\Questionnaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 'default',
            'questionnaire_id' => Questionnaire::factory(),
            'status' => Campaign::STATUS_DRAFT,
            'timezone' => 'Asia/Jakarta',
            'minimum_segment_size' => 5,
        ];
    }
}
