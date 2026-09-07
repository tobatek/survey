<?php

namespace App\Domains\Questionnaires\Models;

use App\Models\User;
use Database\Factories\SurveyTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Pengisian massal lewat import XLSX/JSON (FSD §15) belum dibangun di slice
// ini — model ini baru dipakai untuk relasi `Questionnaire::template()`.
class SurveyTemplate extends Model
{
    /** @use HasFactory<SurveyTemplateFactory> */
    use HasFactory, HasUuids;

    protected static function newFactory(): SurveyTemplateFactory
    {
        return SurveyTemplateFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'code',
        'title',
        'description',
        'category',
        'audience_type',
        'identity_mode',
        'language',
        'schema_version',
        'status',
        'source_import_id',
        'checksum',
        'created_by',
    ];

    public function questionnaires(): HasMany
    {
        return $this->hasMany(Questionnaire::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
