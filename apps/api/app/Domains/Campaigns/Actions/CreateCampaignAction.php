<?php

namespace App\Domains\Campaigns\Actions;

use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Models\User;

class CreateCampaignAction
{
    public function execute(Questionnaire $questionnaire, User $creator): Campaign
    {
        $campaign = Campaign::query()->create([
            'tenant_id' => $creator->tenant_id,
            'questionnaire_id' => $questionnaire->id,
            'status' => Campaign::STATUS_DRAFT,
            'timezone' => 'Asia/Jakarta',
            'minimum_segment_size' => 5,
        ]);

        return $campaign->fresh();
    }
}
