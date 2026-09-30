<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// FSD §23.2 "deep dependency health hanya untuk admin/monitor, bukan
// public" (Fase 6 paket E).
class HealthDeepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    #[Test]
    public function technical_admin_can_view_deep_health(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('code', Role::TECHNICAL_ADMIN)->first());

        $response = $this->actingAs($admin)->getJson('/api/health/deep');

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['database', 'redis', 'queues', 'failed_jobs', 'disk_free_bytes']]);
        $response->assertJsonPath('data.database.connected', true);
    }

    #[Test]
    public function other_roles_cannot_view_deep_health(): void
    {
        $lpmAdmin = User::factory()->create();
        $lpmAdmin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());

        $this->actingAs($lpmAdmin)->getJson('/api/health/deep')->assertStatus(403);
    }

    #[Test]
    public function unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/health/deep')->assertStatus(401);
    }
}
