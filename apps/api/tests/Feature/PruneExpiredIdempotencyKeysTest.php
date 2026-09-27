<?php

namespace Tests\Feature;

use App\Domains\Respondents\Models\IdempotencyKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PruneExpiredIdempotencyKeysTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function command_deletes_only_expired_keys(): void
    {
        $expired = IdempotencyKey::factory()->create(['expires_at' => now()->subMinute()]);
        $stillValid = IdempotencyKey::factory()->create(['expires_at' => now()->addDay()]);

        $this->artisan('idempotency-keys:prune-expired')->assertSuccessful();

        $this->assertDatabaseMissing('idempotency_keys', ['id' => $expired->id]);
        $this->assertDatabaseHas('idempotency_keys', ['id' => $stillValid->id]);
    }
}
