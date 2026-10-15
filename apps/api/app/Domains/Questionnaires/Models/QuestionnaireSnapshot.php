<?php

namespace App\Domains\Questionnaires\Models;

use App\Models\User;
use Database\Factories\QuestionnaireSnapshotFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionnaireSnapshot extends Model
{
    /** @use HasFactory<QuestionnaireSnapshotFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected static function newFactory(): QuestionnaireSnapshotFactory
    {
        return QuestionnaireSnapshotFactory::new();
    }

    protected $fillable = [
        'questionnaire_id',
        'version',
        'schema_version',
        'structure_json',
        'checksum',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'structure_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
