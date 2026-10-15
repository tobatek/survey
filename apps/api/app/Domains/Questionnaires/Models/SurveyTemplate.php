<?php

namespace App\Domains\Questionnaires\Models;

use App\Models\User;
use Database\Factories\SurveyTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Struktur anak (sections/questions/options/matrix rows) diisi lewat Import
// Template JSON (FSD §15, lihat CommitTemplateImportAction). Import XLSX
// belum dibangun di slice ini.
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

    public function sections(): HasMany
    {
        return $this->hasMany(SurveyTemplateSection::class, 'template_id')->orderBy('sort_order');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyTemplateQuestion::class, 'template_id');
    }

    public function imports(): HasMany
    {
        return $this->hasMany(TemplateImport::class, 'committed_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
