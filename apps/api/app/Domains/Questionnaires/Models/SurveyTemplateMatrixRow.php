<?php

namespace App\Domains\Questionnaires\Models;

use Database\Factories\SurveyTemplateMatrixRowFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyTemplateMatrixRow extends Model
{
    /** @use HasFactory<SurveyTemplateMatrixRowFactory> */
    use HasFactory, HasUuids;

    protected static function newFactory(): SurveyTemplateMatrixRowFactory
    {
        return SurveyTemplateMatrixRowFactory::new();
    }

    protected $fillable = [
        'question_id',
        'code',
        'label',
        'sort_order',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplateQuestion::class, 'question_id');
    }
}
