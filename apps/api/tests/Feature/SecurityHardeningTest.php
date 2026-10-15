<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Role;
use App\Domains\Integrations\Models\SyncRun;
use App\Domains\Questionnaires\Models\MatrixRow;
use App\Domains\Questionnaires\Models\Question;
use App\Domains\Questionnaires\Models\QuestionnaireSection;
use App\Domains\Questionnaires\Models\QuestionOption;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// FSD §27.4 (Fase 6 paket F) -- menutup gap cakupan test yang ditemukan
// lewat sweep eksplorasi (bukan berarti kodenya rentan -- 4 controller
// builder sub-resource ini sudah sejak awal pakai `authorizeManage()`
// (trait HandlesQuestionnaireLock) yang delegasi ke QuestionnairePolicy,
// SAMA persis dengan Questionnaire sendiri yang sudah dites di
// QuestionnaireTenantIsolationTest -- test ini MEMBUKTIKAN itu, bukan
// memperbaiki). `SyncRunController` beda -- itu BUG NYATA yang ditemukan
// dan diperbaiki di paket ini (tidak pernah filter tenant_id sama sekali
// walau kolom+index-nya sudah ada sejak awal).
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $adminTenantA;

    private User $adminTenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->adminTenantA = User::factory()->create(['tenant_id' => 'tenant-a']);
        $this->adminTenantA->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());

        $this->adminTenantB = User::factory()->create(['tenant_id' => 'tenant-b']);
        $this->adminTenantB->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());
    }

    #[Test]
    public function admin_cannot_update_section_from_other_tenant(): void
    {
        $section = QuestionnaireSection::factory()->create();
        $section->questionnaire()->update(['tenant_id' => 'tenant-b', 'lock_version' => 1]);

        $this->actingAs($this->adminTenantA)
            ->patchJson("/api/sections/{$section->id}", ['title' => 'Coba Ubah'], ['If-Match' => '1'])
            ->assertStatus(403);
    }

    #[Test]
    public function admin_cannot_update_question_from_other_tenant(): void
    {
        $section = QuestionnaireSection::factory()->create();
        $section->questionnaire()->update(['tenant_id' => 'tenant-b', 'lock_version' => 1]);
        $question = Question::factory()->create(['questionnaire_id' => $section->questionnaire_id, 'section_id' => $section->id]);

        $this->actingAs($this->adminTenantA)
            ->patchJson("/api/questions/{$question->id}", ['text' => 'Coba Ubah?'], ['If-Match' => '1'])
            ->assertStatus(403);
    }

    #[Test]
    public function admin_cannot_update_option_from_other_tenant(): void
    {
        $section = QuestionnaireSection::factory()->create();
        $section->questionnaire()->update(['tenant_id' => 'tenant-b', 'lock_version' => 1]);
        $question = Question::factory()->create(['questionnaire_id' => $section->questionnaire_id, 'section_id' => $section->id, 'type' => 'single_choice']);
        $option = QuestionOption::factory()->create(['question_id' => $question->id]);

        $this->actingAs($this->adminTenantA)
            ->patchJson("/api/options/{$option->id}", ['label' => 'Coba Ubah'], ['If-Match' => '1'])
            ->assertStatus(403);
    }

    #[Test]
    public function admin_cannot_update_matrix_row_from_other_tenant(): void
    {
        $section = QuestionnaireSection::factory()->create();
        $section->questionnaire()->update(['tenant_id' => 'tenant-b', 'lock_version' => 1]);
        $question = Question::factory()->create(['questionnaire_id' => $section->questionnaire_id, 'section_id' => $section->id, 'type' => 'matrix']);
        $matrixRow = MatrixRow::factory()->create(['question_id' => $question->id]);

        $this->actingAs($this->adminTenantA)
            ->patchJson("/api/matrix-rows/{$matrixRow->id}", ['label' => 'Coba Ubah'], ['If-Match' => '1'])
            ->assertStatus(403);
    }

    #[Test]
    public function sync_run_list_only_returns_own_tenant_runs(): void
    {
        SyncRun::factory()->create(['tenant_id' => 'tenant-a', 'entity_type' => 'student']);
        SyncRun::factory()->create(['tenant_id' => 'tenant-b', 'entity_type' => 'student']);

        $response = $this->actingAs($this->adminTenantA)->getJson('/api/sync-runs');

        $response->assertOk();
        // SyncRunResource tidak expose tenant_id -- verifikasi lewat jumlah
        // baris (cuma milik tenant-a) alih-alih membandingkan field.
        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function sync_run_show_from_other_tenant_returns_403(): void
    {
        $run = SyncRun::factory()->create(['tenant_id' => 'tenant-b', 'entity_type' => 'student']);

        $this->actingAs($this->adminTenantA)
            ->getJson("/api/sync-runs/{$run->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function repeated_invalid_token_attempts_are_rate_limited(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->postJson('/api/my-surveys/start-by-token', ['token' => "percobaan-{$i}"])
                ->assertStatus(404);
        }

        $this->postJson('/api/my-surveys/start-by-token', ['token' => 'percobaan-11'])
            ->assertStatus(429);
    }

    #[Test]
    public function responses_include_security_headers(): void
    {
        $response = $this->getJson('/api/health/ready');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }
}
