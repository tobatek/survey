<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Role;
use App\Domains\Settings\Models\BrandingSetting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('public');
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create(['tenant_id' => 'default']);
        $user->roles()->attach(Role::query()->where('code', $roleCode)->first());

        return $user;
    }

    #[Test]
    public function branding_is_publicly_readable_with_null_defaults(): void
    {
        $response = $this->getJson('/api/branding');

        $response->assertOk()->assertJsonPath('data.institution_name', null)->assertJsonPath('data.logo_url', null);
    }

    #[Test]
    public function an_admin_can_update_text_color_and_upload_logo(): void
    {
        $admin = $this->userWithRole(Role::LPM_ADMIN);

        $response = $this->actingAs($admin)->post('/api/branding', [
            'institution_name' => 'STIKES Advaita Medika',
            'primary_color' => '#1d4ed8',
            'login_title' => 'Selamat Datang',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.institution_name', 'STIKES Advaita Medika')
            ->assertJsonPath('data.primary_color', '#1d4ed8');
        $this->assertNotNull($response->json('data.logo_url'));

        $setting = BrandingSetting::query()->where('tenant_id', 'default')->firstOrFail();
        Storage::disk('public')->assertExists($setting->logo_path);

        $this->getJson('/api/branding')->assertOk()->assertJsonPath('data.login_title', 'Selamat Datang');
    }

    #[Test]
    public function removing_the_logo_deletes_the_file(): void
    {
        $admin = $this->userWithRole(Role::LPM_ADMIN);

        $this->actingAs($admin)->post('/api/branding', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ], ['Accept' => 'application/json'])->assertOk();

        $path = BrandingSetting::query()->firstOrFail()->logo_path;

        $this->actingAs($admin)->post('/api/branding', ['remove_logo' => true], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.logo_url', null);

        Storage::disk('public')->assertMissing($path);
    }

    #[Test]
    public function it_rejects_an_invalid_color_and_svg_logo(): void
    {
        $admin = $this->userWithRole(Role::LPM_ADMIN);

        $this->actingAs($admin)->postJson('/api/branding', ['primary_color' => 'biru'])
            ->assertUnprocessable();

        $this->actingAs($admin)->post('/api/branding', [
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    #[Test]
    public function a_respondent_cannot_update_branding(): void
    {
        $respondent = $this->userWithRole(Role::STUDENT_RESPONDENT);

        $this->actingAs($respondent)->postJson('/api/branding', ['institution_name' => 'X'])
            ->assertForbidden();
    }
}
