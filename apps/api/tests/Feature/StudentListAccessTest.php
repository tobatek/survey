<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Role;
use App\Domains\Students\Models\Student;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentListAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    #[Test]
    public function a_respondent_cannot_list_students(): void
    {
        $student = User::factory()->create();
        $student->roles()->attach(Role::query()->where('code', Role::STUDENT_RESPONDENT)->first());

        $this->actingAs($student)->getJson('/api/students')->assertForbidden();
    }

    #[Test]
    public function an_lpm_admin_can_list_and_search_students(): void
    {
        Student::factory()->create(['nim' => '2023010001', 'normalized_nim' => '2023010001', 'name' => 'Budi Santoso', 'normalized_name' => 'budi santoso']);
        Student::factory()->create(['nim' => '2023010002', 'normalized_nim' => '2023010002', 'name' => 'Ani Wijaya', 'normalized_name' => 'ani wijaya']);

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());

        $response = $this->actingAs($admin)->getJson('/api/students?search=budi');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.nim', '2023010001');
    }

    #[Test]
    public function an_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/students')->assertUnauthorized();
    }
}
