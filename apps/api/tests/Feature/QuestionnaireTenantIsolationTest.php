<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// QuestionnairePolicy (Fase 2 slice 6) — menutup gap keamanan: sebelum Policy
// ada, route model binding {questionnaire} meng-resolve Questionnaire milik
// tenant mana pun tanpa cek kecocokan tenant_id, cuma role yang dicek
// middleware. Test ini membuktikan tenant lain sekarang ditolak 403.
class QuestionnaireTenantIsolationTest extends TestCase
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
    public function admin_cannot_view_questionnaire_from_other_tenant(): void
    {
        $questionnaire = Questionnaire::factory()->create(['tenant_id' => 'tenant-b']);

        $this->actingAs($this->adminTenantA)
            ->getJson("/api/questionnaires/{$questionnaire->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function admin_cannot_update_questionnaire_from_other_tenant(): void
    {
        $questionnaire = Questionnaire::factory()->create(['tenant_id' => 'tenant-b', 'lock_version' => 1]);

        $this->actingAs($this->adminTenantA)
            ->patchJson("/api/questionnaires/{$questionnaire->id}", ['title' => 'Coba Ubah'], ['If-Match' => '1'])
            ->assertStatus(403);

        $this->assertDatabaseHas('questionnaires', ['id' => $questionnaire->id, 'title' => $questionnaire->title]);
    }

    #[Test]
    public function admin_cannot_delete_questionnaire_from_other_tenant(): void
    {
        $questionnaire = Questionnaire::factory()->create(['tenant_id' => 'tenant-b']);

        $this->actingAs($this->adminTenantA)
            ->deleteJson("/api/questionnaires/{$questionnaire->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('questionnaires', ['id' => $questionnaire->id]);
    }

    #[Test]
    public function admin_can_still_manage_questionnaire_from_own_tenant(): void
    {
        $questionnaire = Questionnaire::factory()->create(['tenant_id' => 'tenant-a', 'lock_version' => 1]);

        $this->actingAs($this->adminTenantA)
            ->getJson("/api/questionnaires/{$questionnaire->id}")
            ->assertOk();

        $this->actingAs($this->adminTenantA)
            ->patchJson("/api/questionnaires/{$questionnaire->id}", ['title' => 'Judul Baru'], ['If-Match' => '1'])
            ->assertOk();
    }
}
