<?php

namespace App\Domains\Questionnaires\Models;

use Database\Factories\MatrixRowFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatrixRow extends Model
{
    /** @use HasFactory<MatrixRowFactory> */
    use HasFactory, HasUuids;

    protected static function newFactory(): MatrixRowFactory
    {
        return MatrixRowFactory::new();
    }

    protected $fillable = [
        'question_id',
        'code',
        'label',
        'sort_order',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
