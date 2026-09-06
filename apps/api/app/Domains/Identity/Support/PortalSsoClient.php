<?php

namespace App\Domains\Identity\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

// Klien server-to-server ke Portal SSO — docs/15_SSO_PORTAL_VALIDASI_SEPARATION_PLAN.md,
// bagian "Kontrak integrasi untuk aplikasi eksternal". Dipanggil dari
// backend (bukan browser), memakai Authorization: Bearer <app_token> +
// amplop AES-256-GCM via SsoCryptoService.
class PortalSsoClient
{
    public function __construct(
        private readonly SsoCryptoService $crypto,
    ) {}

    /**
     * Tukar authorization code (dari redirect Portal) menjadi identitas user.
     * `code` sekali pakai, kedaluwarsa 60 detik — tukar segera setelah diterima.
     *
     * @return array{id: string, email: string, name: string, type: string, nim?: string, nip?: string, roles: string[]}
     */
    public function verify(string $code): array
    {
        $envelope = $this->crypto->encrypt(['code' => $code]);

        $response = Http::baseUrl(config('sso.portal_base_url'))
            ->withToken(config('sso.app_token'))
            ->timeout(5)
            ->post('/api/sso/verify', $envelope);

        if ($response->status() === 401) {
            throw new RuntimeException('Kode otorisasi SSO ditolak Portal (kedaluwarsa/tidak valid).');
        }

        if (! $response->successful()) {
            throw new RuntimeException("Portal SSO /verify gagal: HTTP {$response->status()}.");
        }

        return $this->crypto->decrypt($response->json());
    }

    /**
     * Cek idle-timeout Portal untuk satu email — best-effort/poll-based,
     * dipakai scheduler (bukan tiap request) sesuai rekomendasi dokumen 15.
     */
    public function sessionStatus(string $email): array
    {
        $envelope = $this->crypto->encrypt(['email' => $email]);

        $response = Http::baseUrl(config('sso.portal_base_url'))
            ->withToken(config('sso.app_token'))
            ->timeout(5)
            ->post('/api/sso/session-status', $envelope);

        if (! $response->successful()) {
            throw new RuntimeException("Portal SSO /session-status gagal: HTTP {$response->status()}.");
        }

        return $this->crypto->decrypt($response->json());
    }

    public function authorizeUrl(string $redirectUri): string
    {
        return rtrim(config('sso.portal_base_url'), '/').'/sso/authorize?'.http_build_query([
            'client_id' => config('sso.client_id'),
            'redirect_uri' => $redirectUri,
        ]);
    }
}
