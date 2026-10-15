<?php

namespace App\Domains\Questionnaires\Models;

use Database\Factories\QuestionOptionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    /** @use HasFactory<QuestionOptionFactory> */
    use HasFactory, HasUuids;

    protected static function newFactory(): QuestionOptionFactory
    {
        return QuestionOptionFactory::new();
    }

    protected $fillable = [
        'question_id',
        'code',
        'label',
        'numeric_score',
        'is_positive',
        'is_not_applicable',
        'target_section_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'numeric_score' => 'decimal:2',
            'is_positive' => 'boolean',
            'is_not_applicable' => 'boolean',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function targetSection(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireSection::class, 'target_section_id');
    }
}
