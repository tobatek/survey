<?php

namespace Database\Factories;

use App\Domains\Questionnaires\Models\TemplateImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateImport>
 */
class TemplateImportFactory extends Factory
{
    protected $model = TemplateImport::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 'default',
            'filename' => 'template.json',
            'raw_payload' => [
                'schema_version' => '1.0',
                'template' => [
                    'code' => 'TPL-'.fake()->unique()->numerify('####'),
                    'title' => fake()->sentence(3),
                    'category' => 'layanan_mahasiswa',
                    'audience_type' => 'student',
                    'identity_mode' => 'anonymous',
                    'language' => 'id',
                ],
                'sections' => [],
            ],
            'status' => TemplateImport::STATUS_VALIDATED,
            'errors_json' => [],
            'warnings_json' => [],
            'preview_json' => ['sections' => 0, 'questions' => 0, 'options' => 0],
            'checksum' => fake()->sha256(),
            'expires_at' => now()->addHour(),
        ];
    }
}
