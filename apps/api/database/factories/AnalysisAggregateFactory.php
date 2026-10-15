<?php

namespace Database\Factories;

use App\Domains\Analytics\Models\AnalysisAggregate;
use App\Domains\Campaigns\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalysisAggregate>
 */
class AnalysisAggregateFactory extends Factory
{
    protected $model = AnalysisAggregate::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'snapshot_checksum' => hash('sha256', (string) str()->uuid()),
            'normalized_filter_hash' => hash('sha256', 'no-filter'),
            'question_code' => 'q'.fake()->unique()->numberBetween(1, 100000),
            'option_code' => null,
            'matrix_row_code' => null,
            'numerator' => 0,
            'denominator' => 0,
            'count' => 0,
            'refreshed_at' => now(),
        ];
    }
}
