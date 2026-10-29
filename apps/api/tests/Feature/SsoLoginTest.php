<?php

namespace Tests\Feature;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Support\SsoCryptoService;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// Portal SSO sungguhan tidak tersedia untuk diuji dari repo ini — sesuai
// strategi FSD §27.2 "SSO callback mocked contract", test ini memalsukan
// respons /api/sso/verify dengan amplop terenkripsi yang valid (memakai
// app_token yang sama seperti yang dipakai SsoController), lalu memverifikasi
// perilaku sisi myAMETA Survey end-to-end.
class SsoLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('sso.portal_base_url', 'https://portal.test');
        Config::set('sso.app_token', 'test-app-token');
        Config::set('sso.client_id', 'myameta-survey');
        Config::set('sso.redirect_uri', 'http://api.test/auth/callback');
        Config::set('services.spa.url', 'http://spa.test');

        $this->seed(RoleSeeder::class);
    }

    #[Test]
    public function redirect_sends_the_browser_to_the_portal_authorize_url(): void
    {
        $response = $this->get('/auth/redirect');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://portal.test/sso/authorize?', $response->headers->get('Location'));
        $this->assertStringContainsString('client_id=myameta-survey', $response->headers->get('Location'));
    }

    #[Test]
    public function callback_creates_a_new_student_user_and_logs_them_in(): void
    {
        $identity = [
            'id' => 'portal-user-123',
            'email' => 'mahasiswa@example.ac.id',
            'name' => 'Mahasiswa Contoh',
            'type' => 'student',
            'nim' => '2023010001',
            'roles' => [],
        ];

        Http::fake([
            'portal.test/api/sso/verify' => Http::response(
                $this->encryptedEnvelope($identity),
                200,
            ),
        ]);

        $response = $this->get('/auth/callback?code=one-time-code');

        $response->assertRedirect('http://spa.test');

        $user = User::query()->where('email', 'mahasiswa@example.ac.id')->firstOrFail();
        $this->assertSame('portal-user-123', $user->hub_user_id);
        $this->assertTrue($user->hasRole(Role::STUDENT_RESPONDENT));
        $this->assertNotNull($user->last_login_at);

        $this->assertDatabaseHas(AuditEvent::class, [
            'action' => 'login_succeeded',
            'result' => 'success',
            'actor_id' => $user->id,
        ]);

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function callback_reuses_the_existing_user_on_a_second_login(): void
    {
        $identity = [
            'id' => 'portal-user-456',
            'email' => 'staff@example.ac.id',
            'name' => 'Staf Contoh',
            'type' => 'staff',
            'nip' => '198001012020121001',
            'roles' => [],
        ];

        Http::fake([
            'portal.test/api/sso/verify' => Http::response($this->encryptedEnvelope($identity), 200),
        ]);

        $this->get('/auth/callback?code=code-1');
        $firstUserId = User::query()->where('hub_user_id', 'portal-user-456')->firstOrFail()->id;

        $this->get('/auth/callback?code=code-2');
        $secondUserId = User::query()->where('hub_user_id', 'portal-user-456')->firstOrFail()->id;

        $this->assertSame($firstUserId, $secondUserId);
        $this->assertSame(1, User::query()->where('hub_user_id', 'portal-user-456')->count());
    }

    #[Test]
    public function callback_redirects_to_login_with_correlation_id_when_portal_rejects_the_code(): void
    {
        Http::fake([
            'portal.test/api/sso/verify' => Http::response('', 401),
        ]);

        $response = $this->get('/auth/callback?code=bad-code');

        $response->assertRedirect();
        $this->assertStringStartsWith('http://spa.test/login?error=sso_failed&correlation_id=', $response->headers->get('Location'));

        $this->assertDatabaseHas(AuditEvent::class, [
            'action' => 'login_failed',
        ]);

        $this->assertGuest();
    }

    #[Test]
    public function callback_without_parameters_restarts_the_flow(): void
    {
        $this->get('/auth/callback')->assertRedirect(route('auth.redirect'));

        $this->assertGuest();
    }

    #[Test]
    public function callback_without_code_fails_when_the_flow_was_already_restarted(): void
    {
        $response = $this->withSession(['sso_restarted_at' => now()->timestamp])->get('/auth/callback');

        $this->assertStringContainsString('error=sso_failed', $response->headers->get('Location'));
        $this->assertGuest();
    }

    #[Test]
    public function callback_with_a_portal_error_shows_the_failure_page(): void
    {
        $response = $this->get('/auth/callback?error=access_denied');

        $this->assertStringContainsString('error=sso_failed', $response->headers->get('Location'));
    }

    #[Test]
    public function a_rejected_code_logs_the_reason_with_the_correlation_id(): void
    {
        Http::fake(['portal.test/api/sso/verify' => Http::response('', 401)]);
        Log::spy();

        $response = $this->get('/auth/callback?code=bad-code');

        preg_match('/correlation_id=([\w-]+)/', $response->headers->get('Location'), $m);

        Log::shouldHaveReceived('warning')->withArgs(
            fn ($message, $context) => $message === 'SSO callback gagal'
                && $context['reason'] === 'code_expired_or_rejected'
                && $context['correlation_id'] === $m[1]
                && ! str_contains(json_encode($context), 'bad-code'),
        )->once();
    }

    #[Test]
    public function redirect_route_sends_the_browser_to_the_portal(): void
    {
        $this->get('/auth/redirect')->assertRedirectContains('https://portal.test/sso/authorize?');
    }

    #[Test]
    public function me_endpoint_returns_the_authenticated_user_with_roles(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());

        $response = $this->actingAs($user)->getJson('/api/me');

        $response->assertOk()->assertJson([
            'data' => [
                'email' => $user->email,
                'roles' => [Role::LPM_ADMIN],
            ],
        ]);
    }

    #[Test]
    public function me_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    private function encryptedEnvelope(array $identity): array
    {
        return (new SsoCryptoService('test-app-token'))->encrypt($identity);
    }

    #[Test]
    public function json_logout_returns_204_and_ends_the_session(): void
    {
        $user = User::factory()->create(['tenant_id' => 'default']);

        $this->actingAs($user)->postJson('/logout')->assertNoContent();

        $this->assertGuest('web');
    }
}
