<?php

namespace App\Domains\Questionnaires\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Tipe MVP (FSD §7.3) — lihat validasi TYPES di ValidateQuestionnaireStructureAction.
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, HasUuids;

    public const TYPES = [
        'short_text',
        'long_text',
        'single_choice',
        'multiple_choice',
        'dropdown',
        'likert',
        'matrix',
        'number',
        'date',
    ];

    public const CHOICE_TYPES = ['single_choice', 'dropdown', 'likert'];

    protected static function newFactory(): QuestionFactory
    {
        return QuestionFactory::new();
    }

    protected $fillable = [
        'questionnaire_id',
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

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireSection::class, 'section_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order');
    }

    public function matrixRows(): HasMany
    {
        return $this->hasMany(MatrixRow::class)->orderBy('sort_order');
    }
}
