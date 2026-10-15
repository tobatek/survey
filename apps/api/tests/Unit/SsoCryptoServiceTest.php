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
    public function it_derives_the_key_from_the_hex_sha256_of_the_token(): void
    {
        $token = 'app-token-abc123';
        $key = hash_hkdf('sha256', hash('sha256', $token), 32, 'sso-verify-v1');

        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt('{"code":"x"}', 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);

        $decrypted = (new SsoCryptoService($token))->decrypt([
            'nonce' => base64_encode($nonce),
            'ciphertext' => base64_encode($ciphertext),
            'tag' => base64_encode($tag),
        ]);

        $this->assertSame(['code' => 'x'], $decrypted);
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
