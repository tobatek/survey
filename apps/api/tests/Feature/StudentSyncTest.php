<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Role;
use App\Domains\Integrations\Models\SyncRun;
use App\Domains\Students\Actions\SyncStudentsFromPortalAction;
use App\Domains\Students\Models\Student;
use App\Jobs\SyncStudentsJob;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('sso.portal_base_url', 'https://portal.test');
        Config::set('sso.app_token', 'test-app-token');

        $this->seed(RoleSeeder::class);
    }

    #[Test]
    public function it_upserts_students_from_a_paginated_portal_response(): void
    {
        Http::fake([
            'portal.test/api/master/students*' => Http::sequence()
                ->push([
                    'data' => [
                        ['source_id' => 's1', 'nim' => '2023010001', 'name' => 'Mahasiswa Satu', 'email' => 'satu@example.ac.id', 'program_study_code' => 'D3KEP', 'program_study_name' => 'D3 Keperawatan', 'cohort_year' => 2023, 'status' => 'active'],
                        ['source_id' => 's2', 'nim' => '2023010002', 'name' => 'Mahasiswa Dua', 'status' => 'active'],
                    ],
                    'next_cursor' => 'page-2',
                ])
                ->push([
                    'data' => [
                        ['source_id' => 's3', 'nim' => '2023010003', 'name' => 'Mahasiswa Tiga', 'status' => 'active'],
                    ],
                    'next_cursor' => null,
                ]),
        ]);

        $run = app(SyncStudentsFromPortalAction::class)->execute('manual');

        $this->assertSame(SyncRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(3, $run->processed_count);
        $this->assertSame(3, $run->upserted_count);
        $this->assertSame(0, $run->error_count);
        $this->assertSame(3, Student::query()->count());
        $this->assertDatabaseHas(Student::class, ['nim' => '2023010001', 'program_study_code' => 'D3KEP']);
    }

    #[Test]
    public function a_failed_row_is_recorded_without_stopping_the_whole_run(): void
    {
        Http::fake([
            'portal.test/api/master/students*' => Http::response([
                'data' => [
                    ['source_id' => 's1', 'nim' => '2023010001', 'name' => 'Mahasiswa Satu', 'status' => 'active'],
                    ['source_id' => null, 'nim' => '2023010002', 'name' => 'Mahasiswa Dua', 'status' => 'active'],
                ],
                'next_cursor' => null,
            ]),
        ]);

        $run = app(SyncStudentsFromPortalAction::class)->execute('manual');

        $this->assertSame(SyncRun::STATUS_PARTIAL, $run->status);
        $this->assertSame(1, $run->upserted_count);
        $this->assertSame(1, $run->error_count);
        $this->assertSame(1, $run->errors()->count());
    }

    #[Test]
    public function a_second_sync_updates_existing_students_instead_of_duplicating(): void
    {
        // Http::fake() menumpuk matcher dalam urutan pendaftaran (yang pertama
        // cocok akan dipakai) — bukan menimpa panggilan sebelumnya. Karena itu
        // dua respons berbeda untuk URL yang sama HARUS didaftarkan sekaligus
        // lewat satu Http::sequence(), bukan dua pemanggilan Http::fake().
        Http::fake([
            'portal.test/api/master/students*' => Http::sequence()
                ->push(['data' => [['source_id' => 's1', 'nim' => '2023010001', 'name' => 'Nama Lama', 'status' => 'active']], 'next_cursor' => null])
                ->push(['data' => [['source_id' => 's1', 'nim' => '2023010001', 'name' => 'Nama Baru', 'status' => 'active']], 'next_cursor' => null]),
        ]);

        app(SyncStudentsFromPortalAction::class)->execute('scheduled');
        app(SyncStudentsFromPortalAction::class)->execute('scheduled');

        $this->assertSame(1, Student::query()->count());
        $this->assertSame('Nama Baru', Student::query()->first()->name);
    }

    #[Test]
    public function manual_trigger_endpoint_dispatches_a_queued_job_and_requires_admin_role(): void
    {
        Bus::fake();

        $student = User::factory()->create();
        $student->roles()->attach(Role::query()->where('code', Role::STUDENT_RESPONDENT)->first());

        $this->actingAs($student)->postJson('/api/students/sync')->assertForbidden();

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());

        $this->actingAs($admin)->postJson('/api/students/sync')->assertStatus(202);

        Bus::assertDispatched(SyncStudentsJob::class);
    }

    #[Test]
    public function it_maps_portal_field_names_from_a_plain_array_response(): void
    {
        Http::fake([
            'portal.test/api/master/students*' => Http::response([
                ['id' => 'p1', 'nim' => '2023010009', 'full_name' => 'Mahasiswa Portal', 'study_program_name' => 'D3 Keperawatan', 'academic_status' => 'Aktif'],
            ]),
        ]);

        $run = app(SyncStudentsFromPortalAction::class)->execute('manual');

        $this->assertSame(SyncRun::STATUS_SUCCESS, $run->status);
        $this->assertDatabaseHas(Student::class, [
            'source_id' => 'p1',
            'name' => 'Mahasiswa Portal',
            'program_study_name' => 'D3 Keperawatan',
            'status' => 'Aktif',
        ]);
    }
}
