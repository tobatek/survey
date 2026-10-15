<?php

namespace Database\Factories;

use App\Domains\Integrations\Models\SyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncRun>
 */
class SyncRunFactory extends Factory
{
    protected $model = SyncRun::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 'default',
            'entity_type' => 'student',
            'mode' => 'scheduled',
            'status' => SyncRun::STATUS_SUCCESS,
            'processed_count' => 0,
            'upserted_count' => 0,
            'error_count' => 0,
            'correlation_id' => (string) str()->uuid(),
            'started_at' => now(),
            'finished_at' => now(),
        ];
    }
}
