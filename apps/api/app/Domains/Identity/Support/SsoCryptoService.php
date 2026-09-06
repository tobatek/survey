<?php

namespace App\Domains\Identity\Support;

use RuntimeException;

// Port dari kontrak "Autentikasi & enkripsi" di
// docs/15_SSO_PORTAL_VALIDASI_SEPARATION_PLAN.md — dipakai oleh App Luar
// (myAMETA Survey) untuk bicara ke /api/sso/verify dan /api/sso/session-status
// milik Portal.
//
// Kunci AES = HKDF-Expand(sha256(app_token), 32 bytes, info="sso-verify-v1").
// App Luar menurunkan kunci dari TOKEN MENTAHnya sendiri (bukan dari hash
// yang dikirim Portal — App Luar tidak pernah menerima hash itu).
//
// Auth tag GCM berfungsi ganda sebagai signature: tag tidak cocok berarti
// payload dipalsukan/rusak atau kuncinya salah.
//
// PENTING — belum diverifikasi byte-for-byte: dokumen 15 menyebut file
// referensi `apps/api/app/Services/SsoCryptoService.php` di repo Portal
// (`apps_validasi_data_pddikti`), yang tidak tersedia di repo ini untuk
// dibandingkan langsung. Implementasi di bawah mengikuti bacaan paling
// literal dari deskripsi ("HKDF-Expand" dipanggil di atas hash SHA-256 dari
// app_token, via `hash_hkdf()` PHP bawaan — yang sebenarnya melakukan
// Extract+Expand penuh, bukan Expand-only). WAJIB divalidasi terhadap kode
// Portal sungguhan (uji round-trip lintas repo) sebelum dipakai produksi —
// lihat exit criteria Fase 1 di docs/16_PLAN_FULLSTACK_LARAVEL_MYAMETA_SURVEY.md.
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
        return hash_hkdf('sha256', hash('sha256', $this->appToken, binary: true), 32, self::HKDF_INFO);
    }
}
