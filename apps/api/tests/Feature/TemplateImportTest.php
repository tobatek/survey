<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\SurveyTemplate;
use App\Domains\Questionnaires\Models\TemplateImport;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TemplateImportTest extends TestCase
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
    private function validPayload(string $templateCode = 'TPL-VALID-01'): array
    {
        return [
            'schema_version' => '1.0',
            'template' => [
                'code' => $templateCode,
                'title' => 'Survei Kepuasan Mahasiswa',
                'category' => 'layanan_mahasiswa',
                'audience_type' => 'student',
                'identity_mode' => 'verified_anonymous',
                'language' => 'id',
            ],
            'sections' => [
                [
                    'code' => 'S1',
                    'title' => 'Data Diri',
                    'questions' => [
                        [
                            'code' => 'Q1',
                            'text' => 'Siapa nama lengkap Anda?',
                            'type' => 'short_text',
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'code' => 'S2',
                    'title' => 'Kepuasan',
                    'questions' => [
                        [
                            'code' => 'Q2',
                            'text' => 'Seberapa puas Anda?',
                            'type' => 'single_choice',
                            'required' => true,
                            'options' => [
                                ['code' => 'O1', 'label' => 'Puas', 'numeric_score' => 1],
                                ['code' => 'O2', 'label' => 'Tidak Puas', 'numeric_score' => 0],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function postImport(array $payload, ?string $filename = 'template.json')
    {
        return $this->actingAs($this->admin)->postJson('/api/settings/template-imports', [
            'filename' => $filename,
            'content' => json_encode($payload),
        ]);
    }

    #[Test]
    public function valid_json_can_be_validated_then_committed(): void
    {
        $response = $this->postImport($this->validPayload());
        $response->assertCreated();
        $response->assertJsonPath('data.status', 'validated');
        $response->assertJsonPath('data.preview.sections', 2);
        $response->assertJsonPath('data.preview.questions', 2);

        $importId = $response->json('data.id');

        $commitResponse = $this->actingAs($this->admin)->postJson("/api/settings/template-imports/{$importId}/commit");
        $commitResponse->assertCreated();
        $commitResponse->assertJsonPath('data.code', 'TPL-VALID-01');

        $this->assertDatabaseHas('survey_templates', ['code' => 'TPL-VALID-01']);
        $this->assertDatabaseHas('survey_template_sections', ['code' => 'S1']);
        $this->assertDatabaseHas('survey_template_sections', ['code' => 'S2']);
        $this->assertDatabaseHas('survey_template_questions', ['code' => 'Q2', 'type' => 'single_choice']);
        $this->assertDatabaseHas('survey_template_options', ['code' => 'O1', 'numeric_score' => 1]);
        $this->assertDatabaseHas('audit_events', ['action' => 'survey_template.import']);

        $import = TemplateImport::find($importId);
        $this->assertSame('committed', $import->status);
        $this->assertNotNull($import->committed_template_id);
    }

    #[Test]
    public function invalid_json_content_is_rejected_at_stage_one(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/settings/template-imports', [
            'filename' => 'broken.json',
            'content' => '{ini bukan json',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'failed');
        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('invalid_json', $codes);
    }

    #[Test]
    public function unsupported_schema_version_major_is_rejected(): void
    {
        $payload = $this->validPayload();
        $payload['schema_version'] = '2.0';

        $response = $this->postImport($payload);
        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('unsupported_schema_version', $codes);
    }

    #[Test]
    public function missing_required_fields_are_reported_with_path(): void
    {
        $payload = $this->validPayload();
        unset($payload['template']['title']);

        $response = $this->postImport($payload);
        $response->assertJsonPath('data.status', 'failed');

        $errors = collect($response->json('data.errors'));
        $this->assertTrue($errors->contains(fn ($e) => $e['code'] === 'template_field_required' && $e['path'] === 'template.title'));
    }

    #[Test]
    public function duplicate_section_code_is_rejected(): void
    {
        $payload = $this->validPayload();
        $payload['sections'][1]['code'] = 'S1';

        $response = $this->postImport($payload);
        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('duplicate_section_code', $codes);
    }

    #[Test]
    public function branch_target_referring_to_unknown_section_is_rejected(): void
    {
        $payload = $this->validPayload();
        $payload['sections'][1]['questions'][0]['options'][0]['target_section_code'] = 'S99';

        $response = $this->postImport($payload);
        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('branch_target_not_found', $codes);
    }

    #[Test]
    public function branch_target_pointing_to_own_section_is_rejected(): void
    {
        $payload = $this->validPayload();
        $payload['sections'][1]['questions'][0]['options'][0]['target_section_code'] = 'S2';

        $response = $this->postImport($payload);
        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('branch_target_is_self', $codes);
    }

    #[Test]
    public function branch_cycle_is_detected(): void
    {
        $payload = $this->validPayload();
        // S2 -> S1 dan S1 tidak punya jalur lain -> S1 default ke S2 -> siklus.
        $payload['sections'][1]['questions'][0]['options'][0]['target_section_code'] = 'S1';

        $response = $this->postImport($payload);
        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('branch_cycle_detected', $codes);
    }

    #[Test]
    public function choice_question_without_options_is_rejected(): void
    {
        $payload = $this->validPayload();
        $payload['sections'][1]['questions'][0]['options'] = [];

        $response = $this->postImport($payload);
        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('question_has_no_options', $codes);
    }

    #[Test]
    public function template_code_already_used_in_tenant_is_rejected(): void
    {
        SurveyTemplate::factory()->create(['tenant_id' => $this->admin->tenant_id, 'code' => 'TPL-VALID-01']);

        $response = $this->postImport($this->validPayload());
        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('template_code_taken', $codes);
    }

    #[Test]
    public function commit_is_rejected_when_staging_has_errors(): void
    {
        $payload = $this->validPayload();
        unset($payload['template']['title']);

        $response = $this->postImport($payload);
        $importId = $response->json('data.id');

        $this->actingAs($this->admin)
            ->postJson("/api/settings/template-imports/{$importId}/commit")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'has_errors');
    }

    #[Test]
    public function commit_is_rejected_when_staging_already_committed(): void
    {
        $response = $this->postImport($this->validPayload());
        $importId = $response->json('data.id');

        $this->actingAs($this->admin)->postJson("/api/settings/template-imports/{$importId}/commit")->assertCreated();

        $this->actingAs($this->admin)
            ->postJson("/api/settings/template-imports/{$importId}/commit")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'already_committed');
    }

    #[Test]
    public function commit_is_rejected_when_staging_expired(): void
    {
        $import = TemplateImport::factory()->create([
            'tenant_id' => $this->admin->tenant_id,
            'status' => TemplateImport::STATUS_VALIDATED,
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/settings/template-imports/{$import->id}/commit")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'expired');
    }

    #[Test]
    public function non_admin_role_cannot_upload_or_commit(): void
    {
        $viewer = User::factory()->create();
        $viewer->roles()->attach(Role::query()->where('code', Role::EXECUTIVE_VIEWER)->first());

        $this->actingAs($viewer)->postJson('/api/settings/template-imports', [
            'filename' => 'template.json',
            'content' => json_encode($this->validPayload()),
        ])->assertStatus(403);
    }

    #[Test]
    public function admin_cannot_view_or_commit_staging_import_from_other_tenant(): void
    {
        $otherTenantImport = TemplateImport::factory()->create([
            'tenant_id' => 'other-tenant',
            'status' => TemplateImport::STATUS_VALIDATED,
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/settings/template-imports/{$otherTenantImport->id}")
            ->assertStatus(403);

        $this->actingAs($this->admin)
            ->postJson("/api/settings/template-imports/{$otherTenantImport->id}/commit")
            ->assertStatus(403);
    }

    #[Test]
    public function listing_history_only_returns_own_tenant_imports(): void
    {
        TemplateImport::factory()->create(['tenant_id' => $this->admin->tenant_id, 'filename' => 'mine.json']);
        TemplateImport::factory()->create(['tenant_id' => 'other-tenant', 'filename' => 'not-mine.json']);

        $response = $this->actingAs($this->admin)->getJson('/api/settings/template-imports');

        $response->assertOk();
        $filenames = collect($response->json('data'))->pluck('filename');
        $this->assertContains('mine.json', $filenames);
        $this->assertNotContains('not-mine.json', $filenames);
    }
}
