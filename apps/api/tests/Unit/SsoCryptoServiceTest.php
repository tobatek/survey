<?php

namespace Tests\Unit;

use App\Domains\Identity\Support\SsoCryptoService;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class SsoCryptoServiceTest extends TestCase
{
    #[Test]
    public function it_round_trips_a_payload_with_the_same_token(): void
    {
        $service = new SsoCryptoService('app-token-abc123');

        $envelope = $service->encrypt(['code' => 'nonce-value', 'n' => 1]);

        $this->assertArrayHasKey('nonce', $envelope);
        $this->assertArrayHasKey('ciphertext', $envelope);
        $this->assertArrayHasKey('tag', $envelope);

        $decrypted = $service->decrypt($envelope);

        $this->assertSame(['code' => 'nonce-value', 'n' => 1], $decrypted);
    }

    #[Test]
    public function it_rejects_a_payload_encrypted_with_a_different_token(): void
    {
        $sender = new SsoCryptoService('token-a');
        $receiver = new SsoCryptoService('token-b');

        $envelope = $sender->encrypt(['code' => 'x']);

        $this->expectException(RuntimeException::class);

        $receiver->decrypt($envelope);
    }

    #[Test]
    public function it_rejects_a_tampered_ciphertext(): void
    {
        $service = new SsoCryptoService('app-token-abc123');

        $envelope = $service->encrypt(['code' => 'x']);
        $envelope['ciphertext'] = base64_encode(random_bytes(strlen(base64_decode($envelope['ciphertext']))));

        $this->expectException(RuntimeException::class);

        $service->decrypt($envelope);
    }
}
