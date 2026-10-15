<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\SurveyTemplate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// Kebalikan TemplateImportTest — memastikan GET /api/templates/{id}/export
// menghasilkan JSON yang cocok skema import (FSD §15.3) dan bisa diimpor
// ulang tanpa modifikasi (round-trip).
class TemplateExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());
    }

    /**
     * @return array<string, mixed>
     */
    private function richPayload(string $templateCode = 'TPL-EXPORT-01'): array
    {
        return [
            'schema_version' => '1.0',
            'template' => [
                'code' => $templateCode,
                'title' => 'Survei Kepuasan Mahasiswa',
                'description' => 'Contoh lengkap untuk uji export.',
                'category' => 'layanan_mahasiswa',
                'audience_type' => 'student',
                'identity_mode' => 'verified_anonymous',
                'language' => 'id',
            ],
            'sections' => [
                [
                    'code' => 'S1',
                    'title' => 'Data Diri',
                    'description' => 'Bagian identitas.',
                    'questions' => [
                        [
                            'code' => 'Q1',
                            'text' => 'Berapa usia Anda?',
                            'type' => 'number',
                            'help_text' => 'Isi dalam tahun.',
                            'required' => false,
                            'allow_not_applicable' => true,
                            'min_value' => 1,
                            'max_value' => 100,
                            'chart_type' => 'bar',
                        ],
                    ],
                ],
                [
                    'code' => 'S2',
                    'title' => 'Kepuasan',
                    'questions' => [
                        [
                            'code' => 'Q2',
                            'text' => 'Seberapa puas Anda dengan layanan akademik?',
                            'type' => 'single_choice',
                            'required' => true,
                            'chart_type' => 'pie',
                            'options' => [
                                ['code' => 'O1', 'label' => 'Puas', 'numeric_score' => 1, 'is_positive' => true, 'target_section_code' => 'S3'],
                                ['code' => 'O2', 'label' => 'Tidak Puas', 'numeric_score' => 0, 'is_positive' => false, 'is_not_applicable' => false],
                            ],
                        ],
                    ],
                ],
                [
                    'code' => 'S3',
                    'title' => 'Penutup',
                    'questions' => [
                        [
                            'code' => 'Q3',
                            'text' => 'Nilai setiap aspek berikut.',
                            'type' => 'matrix',
                            'required' => true,
                            'options' => [
                                ['code' => 'M1', 'label' => 'Baik'],
                                ['code' => 'M2', 'label' => 'Kurang Baik'],
                            ],
                            'matrix_rows' => [
                                ['code' => 'R1', 'label' => 'Kecepatan Layanan'],
                                ['code' => 'R2', 'label' => 'Keramahan Staf'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function importAndCommit(array $payload): SurveyTemplate
    {
        $importResponse = $this->actingAs($this->admin)->postJson('/api/settings/template-imports', [
            'filename' => 'template.json',
            'content' => json_encode($payload),
        ]);
        $importResponse->assertCreated();

        $commitResponse = $this->actingAs($this->admin)
            ->postJson("/api/settings/template-imports/{$importResponse->json('data.id')}/commit");
        $commitResponse->assertCreated();

        return SurveyTemplate::query()->findOrFail($commitResponse->json('data.id'));
    }

    #[Test]
    public function it_exports_a_template_as_downloadable_json_matching_the_import_schema(): void
    {
        $template = $this->importAndCommit($this->richPayload());

        $response = $this->actingAs($this->admin)->getJson("/api/templates/{$template->id}/export");

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename="TPL-EXPORT-01.json"');
        $response->assertJsonPath('schema_version', '1.0');
        $response->assertJsonPath('template.code', 'TPL-EXPORT-01');
        $response->assertJsonPath('sections.0.questions.0.min_value', 1);
        $response->assertJsonPath('sections.1.questions.0.options.0.target_section_code', 'S3');
        $response->assertJsonPath('sections.2.questions.0.matrix_rows.0.code', 'R1');
    }

    #[Test]
    public function it_round_trips_through_the_existing_import_validator_and_commit_action(): void
    {
        $original = $this->importAndCommit($this->richPayload('TPL-EXPORT-02'));

        $exported = $this->actingAs($this->admin)
            ->getJson("/api/templates/{$original->id}/export")
            ->json();

        $exported['template']['code'] = 'TPL-EXPORT-02-REIMPORTED';

        $reimported = $this->importAndCommit($exported);

        $original->loadMissing(['sections.questions.options', 'sections.questions.matrixRows']);
        $reimported->loadMissing(['sections.questions.options', 'sections.questions.matrixRows']);

        $this->assertSame($original->sections->count(), $reimported->sections->count());
        $this->assertSame(
            $original->sections->flatMap->questions->count(),
            $reimported->sections->flatMap->questions->count(),
        );
        $this->assertSame(
            $original->sections->flatMap->questions->flatMap->options->count(),
            $reimported->sections->flatMap->questions->flatMap->options->count(),
        );
        $this->assertSame(
            $original->sections->flatMap->questions->flatMap->matrixRows->count(),
            $reimported->sections->flatMap->questions->flatMap->matrixRows->count(),
        );

        $reimportedBranchOption = $reimported->sections->firstWhere('code', 'S2')->questions->first()->options->firstWhere('code', 'O1');
        $reimportedTargetSection = $reimported->sections->firstWhere('id', $reimportedBranchOption->target_section_id);
        $this->assertSame('S3', $reimportedTargetSection->code);
    }

    #[Test]
    public function it_returns_403_for_a_template_belonging_to_another_tenant(): void
    {
        $template = SurveyTemplate::factory()->create(['tenant_id' => 'other-tenant']);

        $this->actingAs($this->admin)
            ->getJson("/api/templates/{$template->id}/export")
            ->assertStatus(403);
    }

    #[Test]
    public function it_returns_403_for_a_role_not_permitted_to_view_templates(): void
    {
        $respondent = User::factory()->create(['tenant_id' => $this->admin->tenant_id]);
        $respondent->roles()->attach(Role::query()->where('code', Role::STUDENT_RESPONDENT)->first());

        $template = SurveyTemplate::factory()->create(['tenant_id' => $this->admin->tenant_id]);

        $this->actingAs($respondent)
            ->getJson("/api/templates/{$template->id}/export")
            ->assertStatus(403);
    }

    #[Test]
    public function it_returns_404_for_a_nonexistent_template_id(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/templates/'.Str::uuid()->toString().'/export')
            ->assertStatus(404);
    }

    #[Test]
    public function it_omits_null_optional_fields_rather_than_emitting_explicit_nulls(): void
    {
        $template = SurveyTemplate::factory()->create(['tenant_id' => $this->admin->tenant_id, 'description' => null]);
        $section = $template->sections()->create(['code' => 'S1', 'title' => 'Minimal', 'description' => null, 'sort_order' => 0]);
        $question = $template->questions()->create([
            'section_id' => $section->id,
            'code' => 'Q1',
            'type' => 'short_text',
            'text' => 'Pertanyaan minimal?',
            'help_text' => null,
            'required' => true,
            'allow_not_applicable' => false,
            'validation_json' => null,
            'analytics_config_json' => null,
            'sort_order' => 0,
        ]);
        $question->options()->create([
            'code' => 'O1',
            'label' => 'Satu-satunya opsi',
            'numeric_score' => null,
            'is_positive' => true,
            'is_not_applicable' => false,
            'target_section_id' => null,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/templates/{$template->id}/export");

        $response->assertOk();
        $response->assertJsonMissingPath('template.description');
        $response->assertJsonMissingPath('sections.0.description');
        $response->assertJsonMissingPath('sections.0.questions.0.help_text');
        $response->assertJsonMissingPath('sections.0.questions.0.min_value');
        $response->assertJsonMissingPath('sections.0.questions.0.max_value');
        $response->assertJsonMissingPath('sections.0.questions.0.chart_type');
        $response->assertJsonMissingPath('sections.0.questions.0.options.0.numeric_score');
        $response->assertJsonMissingPath('sections.0.questions.0.options.0.target_section_code');
    }
}
