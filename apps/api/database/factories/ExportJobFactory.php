<?php

namespace Database\Factories;

use App\Domains\Analytics\Models\ExportJob;
use App\Domains\Campaigns\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExportJob>
 */
class ExportJobFactory extends Factory
{
    protected $model = ExportJob::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 'default',
            'campaign_id' => Campaign::factory(),
            'requested_by' => User::factory(),
            'format' => ExportJob::FORMAT_XLSX,
            'filter_json' => [],
            'filter_hash' => hash('sha256', (string) str()->uuid()),
            'include_comments' => false,
            'status' => ExportJob::STATUS_QUEUED,
            'requested_at' => now(),
            'expires_at' => now()->addHours(ExportJob::MAX_PROCESSING_WINDOW_HOURS),
        ];
    }

    public function succeeded(): self
    {
        return $this->state(fn () => [
            'status' => ExportJob::STATUS_SUCCEEDED,
            'started_at' => now(),
            'completed_at' => now(),
            'file_path' => 'exports/default/'.str()->uuid().'.xlsx',
            'file_size' => 1024,
            'checksum' => hash('sha256', (string) str()->uuid()),
            'expires_at' => now()->addHours(ExportJob::DOWNLOAD_TTL_HOURS),
        ]);
    }
}
