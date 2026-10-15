<?php

namespace App\Domains\Identity\Support;

use RuntimeException;

// Port dari kontrak "Autentikasi & enkripsi" di
// docs/15_SSO_PORTAL_VALIDASI_SEPARATION_PLAN.md — dipakai oleh App Luar
// (myAMETA Survey) untuk bicara ke /api/sso/verify dan /api/sso/session-status
// milik Portal.
//
// Kunci AES = hash_hkdf('sha256', <sha256(app_token) dalam HEX>, 32, "sso-verify-v1").
// Bahan kunci (IKM) adalah string hex 64 karakter dari sha256(token), BUKAN
// 32 byte biner mentahnya — sama dengan yang dipakai Portal (Portal menyimpan
// token_hash hex di DB dan memakai string itu sebagai IKM). App Luar
// menghitung hash yang sama dari TOKEN MENTAHnya sendiri. Rumus ini dikunci
// oleh SsoCryptoServiceTest::it_derives_the_key_from_the_hex_sha256_of_the_token.
//
// Auth tag GCM berfungsi ganda sebagai signature: tag tidak cocok berarti
// payload dipalsukan/rusak atau kuncinya salah.
//
// Catatan: `hash_hkdf()` PHP melakukan Extract+Expand penuh. Nonce 12 byte,
// base64, dan tag terpisah sudah sesuai kontrak Portal.
class SsoCryptoService
{
    private const HKDF_INFO = 'sso-verify-v1';

    private const CIPHER = 'aes-256-gcm';

    public function __construct(
        private readonly string $appToken,
    ) {}

    /**
     * Enkripsi payload plaintext (akan di-JSON-encode) menjadi amplop
     * { nonce, ciphertext, tag } — semua base64, sesuai kontrak dokumen 15.
     *
     * @param  array<string, mixed>  $payload
     * @return array{nonce: string, ciphertext: string, tag: string}
     */
    public function encrypt(array $payload): array
    {
        $key = $this->deriveKey();
        $nonce = random_bytes(12); // GCM standar: 96-bit nonce
        $plaintext = json_encode($payload, JSON_THROW_ON_ERROR);

        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Gagal mengenkripsi payload SSO.');
        }

        return [
            'nonce' => base64_encode($nonce),
            'ciphertext' => base64_encode($ciphertext),
            'tag' => base64_encode($tag),
        ];
    }

    /**
     * Dekripsi amplop { nonce, ciphertext, tag } (base64) menjadi array
     * plaintext hasil JSON-decode. Melempar RuntimeException bila auth tag
     * tidak cocok (payload dipalsukan/rusak atau kunci salah) — pemanggil
     * harus menerjemahkan ini menjadi respons 401, bukan 500.
     *
     * @param  array{nonce: string, ciphertext: string, tag: string}  $envelope
     * @return array<string, mixed>
     */
    public function decrypt(array $envelope): array
    {
        $key = $this->deriveKey();

        $nonce = base64_decode($envelope['nonce'], strict: true);
        $ciphertext = base64_decode($envelope['ciphertext'], strict: true);
        $tag = base64_decode($envelope['tag'], strict: true);

        if ($nonce === false || $ciphertext === false || $tag === false) {
            throw new RuntimeException('Amplop SSO tidak valid (base64 rusak).');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );

        if ($plaintext === false) {
            throw new RuntimeException('Dekripsi SSO gagal — signature/kunci tidak cocok.');
        }

        return json_decode($plaintext, associative: true, flags: JSON_THROW_ON_ERROR);
    }

    private function deriveKey(): string
    {
        return hash_hkdf('sha256', hash('sha256', $this->appToken), 32, self::HKDF_INFO);
    }
}
