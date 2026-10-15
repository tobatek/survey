<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\TemplateImport;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// Kebalikan TemplateImportTest (jalur JSON) — memastikan workbook XLSX
// (FSD §15.2) dikonversi lewat XlsxTemplateWorkbookParser menjadi payload
// yang sama persis divalidasi/dikomit TemplateImportValidator/
// CommitTemplateImportAction seperti jalur JSON.
class TemplateXlsxImportTest extends TestCase
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
     * @param  array<string, array<int, array<int, mixed>>>  $rowsBySheet
     */
    private function buildWorkbookBase64(array $rowsBySheet): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach ($rowsBySheet as $sheetName => $rows) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetName);
            $sheet->fromArray($rows, null, 'A1');
        }

        $writer = new Xlsx($spreadsheet);
        $path = tempnam(sys_get_temp_dir(), 'test_xlsx_');
        $writer->save($path);
        $bytes = file_get_contents($path);
        unlink($path);

        return base64_encode($bytes);
    }

    /**
     * @param  array<string, array<int, array<int, mixed>>>  $rowsBySheet
     */
    private function postXlsxImport(array $rowsBySheet, string $filename = 'template.xlsx')
    {
        return $this->actingAs($this->admin)->postJson('/api/settings/template-imports', [
            'filename' => $filename,
            'content' => $this->buildWorkbookBase64($rowsBySheet),
            'format' => 'xlsx',
        ]);
    }

    /**
     * @return array<string, array<int, array<int, mixed>>>
     */
    private function validWorkbookRows(string $templateCode = 'TPL-XLSX-01'): array
    {
        return [
            'Template' => [
                ['template_code', 'title', 'description', 'category', 'audience_type', 'identity_mode', 'language'],
                [$templateCode, 'Survei Kepuasan Mahasiswa', '', 'layanan_mahasiswa', 'student', 'verified_anonymous', 'id'],
            ],
            'Sections' => [
                ['section_code', 'title', 'description', 'order'],
                ['S1', 'Data Diri', '', 1],
                ['S2', 'Kepuasan', '', 2],
            ],
            'Questions' => [
                ['section_code', 'question_code', 'text', 'help_text', 'type', 'required', 'allow_not_applicable', 'min_value', 'max_value', 'order', 'chart_type'],
                ['S1', 'Q1', 'Siapa nama lengkap Anda?', '', 'short_text', 'TRUE', 'FALSE', '', '', 1, ''],
                ['S2', 'Q2', 'Seberapa puas Anda?', '', 'single_choice', 'TRUE', 'FALSE', '', '', 1, ''],
            ],
            'Options' => [
                ['question_code', 'option_code', 'label', 'numeric_score', 'is_positive', 'is_not_applicable', 'order', 'target_section_code'],
                ['Q2', 'O1', 'Puas', 1, 'TRUE', 'FALSE', 1, ''],
                ['Q2', 'O2', 'Tidak Puas', 0, 'FALSE', 'FALSE', 2, ''],
            ],
        ];
    }

    #[Test]
    public function valid_xlsx_workbook_validates_then_commits_matching_json_equivalent(): void
    {
        $response = $this->postXlsxImport($this->validWorkbookRows());

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'validated');
        $response->assertJsonPath('data.preview.sections', 2);
        $response->assertJsonPath('data.preview.questions', 2);
        $response->assertJsonPath('data.preview.options', 2);

        $importId = $response->json('data.id');
        $commitResponse = $this->actingAs($this->admin)->postJson("/api/settings/template-imports/{$importId}/commit");
        $commitResponse->assertCreated();
        $commitResponse->assertJsonPath('data.code', 'TPL-XLSX-01');

        $this->assertDatabaseHas('survey_templates', ['code' => 'TPL-XLSX-01']);
        $this->assertDatabaseHas('survey_template_sections', ['code' => 'S1']);
        $this->assertDatabaseHas('survey_template_sections', ['code' => 'S2']);
        $this->assertDatabaseHas('survey_template_questions', ['code' => 'Q2', 'type' => 'single_choice']);
        $this->assertDatabaseHas('survey_template_options', ['code' => 'O1', 'numeric_score' => 1]);

        $import = TemplateImport::find($importId);
        $this->assertSame('committed', $import->status);
    }

    #[Test]
    public function xlsx_missing_required_sheet_is_rejected_at_file_level(): void
    {
        $rows = $this->validWorkbookRows();
        unset($rows['Options']);

        $response = $this->postXlsxImport($rows);

        $response->assertJsonPath('data.status', 'failed');
        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('missing_sheet', $codes);
    }

    #[Test]
    public function xlsx_missing_required_column_header_is_rejected_at_file_level(): void
    {
        $rows = $this->validWorkbookRows();
        $rows['Questions'][0] = array_values(array_diff($rows['Questions'][0], ['type']));

        $response = $this->postXlsxImport($rows);

        $response->assertJsonPath('data.status', 'failed');
        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('missing_column', $codes);
    }

    #[Test]
    public function xlsx_invalid_boolean_value_surfaces_as_stage_four_error(): void
    {
        $rows = $this->validWorkbookRows();
        $rows['Questions'][1][5] = 'maybe';

        $response = $this->postXlsxImport($rows);

        $response->assertJsonPath('data.status', 'failed');
        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('question_invalid_boolean', $codes);
    }

    #[Test]
    public function xlsx_with_wrong_zip_signature_is_rejected_at_signature_check(): void
    {
        // Fase 6 paket C -- konten yang bahkan bukan arsip ZIP ditolak SEBELUM
        // sampai ke parser PhpSpreadsheet (lihat ValidateTemplateImportAction).
        $response = $this->actingAs($this->admin)->postJson('/api/settings/template-imports', [
            'filename' => 'corrupt.xlsx',
            'content' => base64_encode('bukan file xlsx sungguhan'),
            'format' => 'xlsx',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'failed');
        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('invalid_xlsx_signature', $codes);
    }

    #[Test]
    public function xlsx_with_valid_zip_signature_but_corrupt_workbook_is_rejected_gracefully(): void
    {
        // Signature ZIP valid (PK\x03\x04) tapi bukan struktur xlsx yang
        // benar -- signature check saja tidak cukup, parser sendiri harus
        // tetap menangani ini dengan baik (bukan exception tak tertangani).
        $response = $this->actingAs($this->admin)->postJson('/api/settings/template-imports', [
            'filename' => 'corrupt.xlsx',
            'content' => base64_encode("PK\x03\x04bukan struktur zip xlsx yang benar"),
            'format' => 'xlsx',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'failed');
        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('invalid_xlsx', $codes);
    }

    #[Test]
    public function matrix_rows_sheet_populates_matrix_question_rows_correctly(): void
    {
        $rows = $this->validWorkbookRows('TPL-XLSX-MATRIX');
        $rows['Sections'][] = ['S3', 'Penutup', '', 3];
        $rows['Questions'][] = ['S3', 'Q3', 'Nilai setiap aspek berikut.', '', 'matrix', 'TRUE', 'FALSE', '', '', 1, ''];
        $rows['Options'][] = ['Q3', 'M1', 'Baik', '', '', '', 1, ''];
        $rows['Options'][] = ['Q3', 'M2', 'Kurang Baik', '', '', '', 2, ''];
        $rows['MatrixRows'] = [
            ['question_code', 'row_code', 'label', 'order'],
            ['Q3', 'R1', 'Kecepatan Layanan', 1],
            ['Q3', 'R2', 'Keramahan Staf', 2],
        ];

        $response = $this->postXlsxImport($rows);
        $response->assertJsonPath('data.status', 'validated');

        $importId = $response->json('data.id');
        $this->actingAs($this->admin)->postJson("/api/settings/template-imports/{$importId}/commit")->assertCreated();

        $this->assertDatabaseHas('survey_template_questions', ['code' => 'Q3', 'type' => 'matrix']);
        $this->assertDatabaseHas('survey_template_matrix_rows', ['code' => 'R1', 'label' => 'Kecepatan Layanan']);
        $this->assertDatabaseHas('survey_template_matrix_rows', ['code' => 'R2', 'label' => 'Keramahan Staf']);
    }

    #[Test]
    public function sample_xlsx_is_downloadable_and_importable_as_is(): void
    {
        $sampleResponse = $this->actingAs($this->admin)->get('/api/settings/template-imports/sample.xlsx');
        $sampleResponse->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $sampleResponse->headers->get('Content-Type'));

        $base64 = base64_encode($sampleResponse->streamedContent());

        $response = $this->actingAs($this->admin)->postJson('/api/settings/template-imports', [
            'filename' => 'sample.xlsx',
            'content' => $base64,
            'format' => 'xlsx',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'validated');
    }

    #[Test]
    public function format_field_defaults_to_json_when_absent(): void
    {
        $payload = [
            'schema_version' => '1.0',
            'template' => [
                'code' => 'TPL-DEFAULT-FORMAT',
                'title' => 'Survei Tanpa Field Format',
                'category' => 'layanan_mahasiswa',
                'audience_type' => 'student',
                'identity_mode' => 'anonymous',
                'language' => 'id',
            ],
            'sections' => [
                [
                    'code' => 'S1',
                    'title' => 'Bagian 1',
                    'questions' => [
                        ['code' => 'Q1', 'text' => 'Pertanyaan?', 'type' => 'short_text', 'required' => true],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/settings/template-imports', [
            'filename' => 'no-format.json',
            'content' => json_encode($payload),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'validated');
    }
}
