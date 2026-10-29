<?php

namespace App\Domains\Integrations\Support;

use Generator;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// Klien read-only ke Portal Master Data API — docs/15 Tahap 0:
// "GET /api/master/students", "GET /api/master/employees", diproteksi
// dengan token aplikasi yang sama dengan mekanisme SSO (Bearer, TANPA
// amplop AES — hanya /sso/verify dan /sso/session-status yang dienkripsi
// per kontrak dokumen 15). Respons dipaginasi lewat cursor; myAMETA Survey
// tidak pernah menulis balik ke endpoint ini (single source of truth tetap
// Portal).
class PortalMasterDataClient
{
    /**
     * @return Generator<int, array<int, array<string, mixed>>>
     */
    public function paginate(string $resource): Generator
    {
        $cursor = null;

        do {
            $response = Http::baseUrl(config('sso.portal_base_url'))
                ->withToken(config('sso.app_token'))
                ->timeout(10)
                ->get("/api/master/{$resource}", array_filter(['cursor' => $cursor]));

            if (! $response->successful()) {
                throw new RuntimeException(
                    "Portal Master Data /{$resource} gagal: HTTP {$response->status()}."
                );
            }

            $body = $response->json() ?? [];

            // Portal bisa membalas amplop {data, next_cursor} atau array polos
            // (daftar baris langsung, tanpa paginasi cursor).
            if (array_is_list($body)) {
                yield $body;

                return;
            }

            yield $body['data'] ?? [];

            $cursor = $body['next_cursor'] ?? null;
        } while ($cursor !== null);
    }
}
