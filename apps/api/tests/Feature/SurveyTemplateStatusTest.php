<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\SurveyTemplate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// FSD §17.7 PATCH /api/templates/{id}/status — aktifkan/nonaktifkan template
// pasca-import.
class SurveyTemplateStatusTest extends TestCase
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

    #[Test]
    public function admin_can_deactivate_then_reactivate_own_template(): void
    {
        $template = SurveyTemplate::factory()->create(['tenant_id' => $this->admin->tenant_id, 'status' => 'active']);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/templates/{$template->id}/status", ['status' => 'inactive']);
        $response->assertOk();
        $response->assertJsonPath('data.status', 'inactive');
        $this->assertDatabaseHas('survey_templates', ['id' => $template->id, 'status' => 'inactive']);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/templates/{$template->id}/status", ['status' => 'active']);
        $response->assertOk();
        $response->assertJsonPath('data.status', 'active');
    }

    #[Test]
    public function invalid_status_value_is_rejected(): void
    {
        $template = SurveyTemplate::factory()->create(['tenant_id' => $this->admin->tenant_id]);

        $this->actingAs($this->admin)
            ->patchJson("/api/templates/{$template->id}/status", ['status' => 'archived'])
            ->assertStatus(422);
    }

    #[Test]
    public function admin_cannot_toggle_status_of_template_from_other_tenant(): void
    {
        $template = SurveyTemplate::factory()->create(['tenant_id' => 'other-tenant', 'status' => 'active']);

        $this->actingAs($this->admin)
            ->patchJson("/api/templates/{$template->id}/status", ['status' => 'inactive'])
            ->assertStatus(403);
    }

    #[Test]
    public function viewer_role_cannot_toggle_status(): void
    {
        $viewer = User::factory()->create(['tenant_id' => $this->admin->tenant_id]);
        $viewer->roles()->attach(Role::query()->where('code', Role::EXECUTIVE_VIEWER)->first());

        $template = SurveyTemplate::factory()->create(['tenant_id' => $this->admin->tenant_id, 'status' => 'active']);

        $this->actingAs($viewer)
            ->patchJson("/api/templates/{$template->id}/status", ['status' => 'inactive'])
            ->assertStatus(403);
    }

    #[Test]
    public function listing_without_query_only_returns_active_templates(): void
    {
        SurveyTemplate::factory()->create(['tenant_id' => $this->admin->tenant_id, 'status' => 'active', 'title' => 'Aktif']);
        SurveyTemplate::factory()->create(['tenant_id' => $this->admin->tenant_id, 'status' => 'inactive', 'title' => 'Nonaktif']);

        $response = $this->actingAs($this->admin)->getJson('/api/templates');

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertSame(['Aktif'], $titles->all());
    }

    #[Test]
    public function listing_with_status_all_returns_every_status_for_own_tenant(): void
    {
        SurveyTemplate::factory()->create(['tenant_id' => $this->admin->tenant_id, 'status' => 'active', 'title' => 'Aktif']);
        SurveyTemplate::factory()->create(['tenant_id' => $this->admin->tenant_id, 'status' => 'inactive', 'title' => 'Nonaktif']);
        SurveyTemplate::factory()->create(['tenant_id' => 'other-tenant', 'status' => 'active', 'title' => 'Tenant Lain']);

        $response = $this->actingAs($this->admin)->getJson('/api/templates?status=all');

        $titles = collect($response->json('data'))->pluck('title')->sort()->values();
        $this->assertSame(['Aktif', 'Nonaktif'], $titles->all());
    }
}
