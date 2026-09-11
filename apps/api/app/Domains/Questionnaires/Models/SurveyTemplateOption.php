<?php

namespace App\Domains\Questionnaires\Models;

use Database\Factories\SurveyTemplateOptionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyTemplateOption extends Model
{
    /** @use HasFactory<SurveyTemplateOptionFactory> */
    use HasFactory, HasUuids;

    protected static function newFactory(): SurveyTemplateOptionFactory
    {
        return SurveyTemplateOptionFactory::new();
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
        return $this->belongsTo(SurveyTemplateQuestion::class, 'question_id');
    }

    public function targetSection(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplateSection::class, 'target_section_id');
    }
}
