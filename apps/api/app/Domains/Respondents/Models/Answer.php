<?php

namespace App\Domains\Respondents\Models;

use Database\Factories\AnswerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Answer extends Model
{
    /** @use HasFactory<AnswerFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected static function newFactory(): AnswerFactory
    {
        return AnswerFactory::new();
    }

    protected $fillable = [
        'response_id',
        'snapshot_question_code',
        'matrix_row_code',
        'value_type',
        'text_value',
        'numeric_value',
        'date_value',
        'is_not_applicable',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'numeric_value' => 'decimal:4',
            'date_value' => 'date',
            'is_not_applicable' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(SurveyResponse::class, 'response_id');
    }

    public function choices(): HasMany
    {
        return $this->hasMany(AnswerChoice::class);
    }
}
