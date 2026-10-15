<?php

namespace Tests\Feature;

use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Employees\Models\Employee;
use App\Domains\Identity\Models\Role;
use App\Domains\Students\Models\Student;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampaignAudiencePreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['tenant_id' => 'default']);
        $this->admin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());

        $this->operator = User::factory()->create(['tenant_id' => 'default']);
        $this->operator->roles()->attach(Role::query()->where('code', Role::SURVEY_OPERATOR)->first());
    }

    private function makeEmployee(array $attributes = []): Employee
    {
        return Employee::query()->create(array_merge([
            'tenant_id' => 'default',
            'source_system' => 'portal',
            'source_id' => (string) Str::uuid(),
            'employee_number' => fake()->unique()->numerify('EMP-####'),
            'name' => fake()->name(),
            'normalized_name' => 'employee',
            'status' => 'active',
        ], $attributes));
    }

    #[Test]
    public function preview_returns_count_and_breakdown_filtered_by_program_study(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default', 'minimum_segment_size' => 1]);

        Student::factory()->count(3)->create(['tenant_id' => 'default', 'program_study_code' => 'TI']);
        Student::factory()->count(2)->create(['tenant_id' => 'default', 'program_study_code' => 'SI']);

        $response = $this->actingAs($this->admin)->postJson(
            "/api/campaigns/{$campaign->id}/audience/preview",
            ['program_study_codes' => ['TI']],
        );

        $response->assertOk();
        $response->assertJsonPath('data.total', 3);
        $response->assertJsonPath('data.students.count', 3);
        $response->assertJsonPath('data.students.breakdown.TI', 3);
        $response->assertJsonPath('data.is_empty', false);

        $this->assertSame(['program_study_codes' => ['TI']], array_filter(
            $campaign->fresh()->target_rule_json,
            fn ($v) => $v !== null,
        ));
    }

    #[Test]
    public function lpm_admin_can_use_position_filter(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default']);

        $this->makeEmployee(['position_name' => 'Dekan']);
        $this->makeEmployee(['position_name' => 'Staff']);

        $response = $this->actingAs($this->admin)->postJson(
            "/api/campaigns/{$campaign->id}/audience/preview",
            ['position_names' => ['Dekan']],
        );

        $response->assertOk();
        $response->assertJsonPath('data.employees.count', 1);
    }

    #[Test]
    public function survey_operator_cannot_use_position_filter(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default']);

        $response = $this->actingAs($this->operator)->postJson(
            "/api/campaigns/{$campaign->id}/audience/preview",
            ['position_names' => ['Dekan']],
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'position_filter_not_allowed');
    }

    #[Test]
    public function small_breakdown_groups_are_merged_into_lainnya(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default', 'minimum_segment_size' => 5]);

        Student::factory()->count(10)->create(['tenant_id' => 'default', 'program_study_code' => 'TI']);
        Student::factory()->count(2)->create(['tenant_id' => 'default', 'program_study_code' => 'SI']);
        Student::factory()->count(1)->create(['tenant_id' => 'default', 'program_study_code' => 'TE']);

        $response = $this->actingAs($this->admin)->postJson("/api/campaigns/{$campaign->id}/audience/preview", []);

        $response->assertOk();
        $response->assertJsonPath('data.students.breakdown.TI', 10);
        $response->assertJsonPath('data.students.breakdown.lainnya', 3);
        $response->assertJsonMissingPath('data.students.breakdown.SI');
        $response->assertJsonMissingPath('data.students.breakdown.TE');
    }

    #[Test]
    public function preview_on_non_draft_campaign_is_rejected(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default', 'status' => Campaign::STATUS_ACTIVE]);

        $this->actingAs($this->admin)
            ->postJson("/api/campaigns/{$campaign->id}/audience/preview", [])
            ->assertStatus(422);
    }

    #[Test]
    public function preview_on_another_tenants_campaign_returns_403(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'other-tenant']);

        $this->actingAs($this->admin)
            ->postJson("/api/campaigns/{$campaign->id}/audience/preview", [])
            ->assertStatus(403);
    }

    #[Test]
    public function preview_with_no_matches_returns_empty_flag_and_still_saves_target_rule(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default']);

        $response = $this->actingAs($this->admin)->postJson(
            "/api/campaigns/{$campaign->id}/audience/preview",
            ['program_study_codes' => ['TIDAK-ADA']],
        );

        $response->assertOk();
        $response->assertJsonPath('data.total', 0);
        $response->assertJsonPath('data.is_empty', true);
        $this->assertSame(['TIDAK-ADA'], $campaign->fresh()->target_rule_json['program_study_codes']);
    }
}
