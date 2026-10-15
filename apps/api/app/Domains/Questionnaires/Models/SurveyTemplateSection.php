<?php

namespace App\Domains\Questionnaires\Models;

use Database\Factories\SurveyTemplateSectionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyTemplateSection extends Model
{
    /** @use HasFactory<SurveyTemplateSectionFactory> */
    use HasFactory, HasUuids;

    protected static function newFactory(): SurveyTemplateSectionFactory
    {
        return SurveyTemplateSectionFactory::new();
    }

    protected $fillable = [
        'template_id',
        'code',
        'title',
        'description',
        'sort_order',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplate::class, 'template_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyTemplateQuestion::class, 'section_id')->orderBy('sort_order');
    }
}
