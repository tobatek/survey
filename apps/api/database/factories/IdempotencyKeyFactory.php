<?php

namespace Database\Factories;

use App\Domains\Respondents\Models\IdempotencyKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdempotencyKey>
 */
class IdempotencyKeyFactory extends Factory
{
    protected $model = IdempotencyKey::class;

    public function definition(): array
    {
        return [
            'scope_hash' => hash('sha256', (string) str()->uuid()),
            'key_hash' => hash('sha256', (string) str()->uuid()),
            'request_hash' => hash('sha256', (string) str()->uuid()),
            'status' => IdempotencyKey::STATUS_PROCESSING,
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
        ];
    }
}
