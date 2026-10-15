<?php

namespace App\Domains\Questionnaires\Models;

use Database\Factories\QuestionnaireSectionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionnaireSection extends Model
{
    /** @use HasFactory<QuestionnaireSectionFactory> */
    use HasFactory, HasUuids;

    protected static function newFactory(): QuestionnaireSectionFactory
    {
        return QuestionnaireSectionFactory::new();
    }

    protected $fillable = [
        'questionnaire_id',
        'code',
        'title',
        'description',
        'sort_order',
    ];

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'section_id')->orderBy('sort_order');
    }
}
