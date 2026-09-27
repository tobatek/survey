<?php

namespace App\Domains\Analytics\Models;

use App\Domains\Campaigns\Models\Campaign;
use Database\Factories\AnalysisAggregateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisAggregate extends Model
{
    /** @use HasFactory<AnalysisAggregateFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected static function newFactory(): AnalysisAggregateFactory
    {
        return AnalysisAggregateFactory::new();
    }

    protected $fillable = [
        'campaign_id',
        'snapshot_checksum',
        'normalized_filter_hash',
        'question_code',
        'option_code',
        'matrix_row_code',
        'numerator',
        'denominator',
        'sum',
        'count',
        'min',
        'max',
        'payload_json',
        'refreshed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'refreshed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
