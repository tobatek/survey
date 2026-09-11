<?php

namespace App\Domains\Questionnaires\Models;

use Database\Factories\SurveyTemplateQuestionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyTemplateQuestion extends Model
{
    /** @use HasFactory<SurveyTemplateQuestionFactory> */
    use HasFactory, HasUuids;

    protected static function newFactory(): SurveyTemplateQuestionFactory
    {
        return SurveyTemplateQuestionFactory::new();
    }

    protected $fillable = [
        'template_id',
        'section_id',
        'code',
        'type',
        'text',
        'help_text',
        'required',
        'allow_not_applicable',
        'validation_json',
        'analytics_config_json',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'allow_not_applicable' => 'boolean',
            'validation_json' => 'array',
            'analytics_config_json' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplate::class, 'template_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplateSection::class, 'section_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(SurveyTemplateOption::class, 'question_id')->orderBy('sort_order');
    }

    public function matrixRows(): HasMany
    {
        return $this->hasMany(SurveyTemplateMatrixRow::class, 'question_id')->orderBy('sort_order');
    }
}
