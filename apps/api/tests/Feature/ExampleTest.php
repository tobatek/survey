<?php

namespace Tests\Feature;

use Tests\TestCase;

// Backend ini API-only (SPA terpisah di apps/web) — tidak ada halaman `/`,
// jadi test bawaan Laravel diganti memeriksa liveness bawaan framework.
class ExampleTest extends TestCase
{
    public function test_the_liveness_endpoint_is_reachable(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }
}
