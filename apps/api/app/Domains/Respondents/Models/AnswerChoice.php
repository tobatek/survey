<?php

namespace App\Domains\Respondents\Models;

use Database\Factories\AnswerChoiceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnswerChoice extends Model
{
    /** @use HasFactory<AnswerChoiceFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected static function newFactory(): AnswerChoiceFactory
    {
        return AnswerChoiceFactory::new();
    }

    protected $fillable = [
        'answer_id',
        'option_code',
        'numeric_score',
    ];

    protected function casts(): array
    {
        return [
            'numeric_score' => 'decimal:4',
        ];
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }
}
