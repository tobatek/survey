<?php

namespace Database\Factories;

use App\Domains\Campaigns\Models\DeliveryAttempt;
use App\Domains\Campaigns\Models\Eligibility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryAttempt>
 */
class DeliveryAttemptFactory extends Factory
{
    protected $model = DeliveryAttempt::class;

    public function definition(): array
    {
        return [
            'eligibility_id' => Eligibility::factory(),
            'channel' => DeliveryAttempt::CHANNEL_MAIL,
            'status' => DeliveryAttempt::STATUS_QUEUED,
            'attempt_number' => 1,
        ];
    }
}
