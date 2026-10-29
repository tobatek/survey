<?php

namespace App\Http\Controllers\Api;

use App\Domains\Audit\Actions\RecordAuditEventAction;
use App\Domains\Settings\Models\BrandingSetting;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

// Branding institusi (logo, nama, warna utama, favicon, teks login/footer).
// show() PUBLIK -- dipakai halaman login sebelum user terautentikasi, jadi
// hanya mengembalikan data tampilan (tidak ada data sensitif). update()
// hanya LPM Admin & Technical Admin (lihat routes/api.php). SVG sengaja
// tidak diizinkan untuk upload (risiko script tertanam).
class BrandingController extends Controller
{
    private const TENANT = 'default';

    public function show(): JsonResponse
    {
        $setting = BrandingSetting::query()->where('tenant_id', self::TENANT)->first();

        return response()->json(['data' => $this->payload($setting)]);
    }

    public function update(Request $request, RecordAuditEventAction $recordAuditEvent): JsonResponse
    {
        $validated = $request->validate([
            'institution_name' => ['nullable', 'string', 'max:120'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'login_title' => ['nullable', 'string', 'max:120'],
            'login_description' => ['nullable', 'string', 'max:500'],
            'footer_text' => ['nullable', 'string', 'max:200'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,webp', 'max:256'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ]);

        $setting = BrandingSetting::query()->firstOrNew(['tenant_id' => self::TENANT]);

        foreach (['institution_name', 'primary_color', 'login_title', 'login_description', 'footer_text'] as $field) {
            if (array_key_exists($field, $validated)) {
                $setting->{$field} = $validated[$field] === '' ? null : $validated[$field];
            }
        }

        $this->applyFile($request, $setting, 'logo', 'logo_path');
        $this->applyFile($request, $setting, 'favicon', 'favicon_path');

        $setting->updated_by = Auth::id();
        $setting->save();

        $recordAuditEvent->execute(
            action: 'branding_updated',
            result: 'success',
            actor: $request->user(),
            resourceType: 'branding_setting',
            resourceId: $setting->id,
        );

        return response()->json(['data' => $this->payload($setting)]);
    }

    private function applyFile(Request $request, BrandingSetting $setting, string $input, string $column): void
    {
        $remove = $request->boolean("remove_{$input}");

        if (! $remove && ! $request->hasFile($input)) {
            return;
        }

        if ($setting->{$column} !== null) {
            Storage::disk('public')->delete($setting->{$column});
            $setting->{$column} = null;
        }

        if (! $remove) {
            $setting->{$column} = $request->file($input)->store('branding', 'public');
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function payload(?BrandingSetting $setting): array
    {
        return [
            'institution_name' => $setting?->institution_name,
            'primary_color' => $setting?->primary_color,
            'login_title' => $setting?->login_title,
            'login_description' => $setting?->login_description,
            'footer_text' => $setting?->footer_text,
            'logo_url' => $this->url($setting?->logo_path, $setting),
            'favicon_url' => $this->url($setting?->favicon_path, $setting),
        ];
    }

    // Query `v` = waktu update, supaya cache browser/CDN tidak menahan logo lama.
    private function url(?string $path, ?BrandingSetting $setting): ?string
    {
        if ($path === null) {
            return null;
        }

        return Storage::disk('public')->url($path).'?v='.$setting?->updated_at?->timestamp;
    }
}
