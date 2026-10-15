// Klien HTTP tipis ke Laravel API (apps/api). Memakai cookie-based session
// Sanctum (credentials: 'include'), bukan bearer token, karena SPA dan API
// berada di domain yang sama secara logis (stateful) — lihat config/cors.php
// dan config/sanctum.php di apps/api.

export const API_BASE = import.meta.env.VITE_API_BASE_URL ?? '/api'

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code?: string,
    public readonly correlationId?: string,
    public readonly fields?: unknown,
  ) {
    super(message)
  }
}

// Origin API dipakai untuk /sanctum/csrf-cookie (di luar prefix /api). Di
// produksi SPA & API beda host (VITE_API_BASE_URL absolut), jadi path relatif
// akan nyasar ke Nginx SPA. Di dev, API_BASE relatif ('/api') -> origin kosong
// supaya tetap lewat proxy Vite.
const API_ORIGIN = /^https?:\/\//.test(API_BASE) ? new URL(API_BASE).origin : ''

async function ensureCsrfCookie(): Promise<void> {
  await fetch(`${API_ORIGIN}/sanctum/csrf-cookie`, { credentials: 'include' })
}

function readCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`))
  return match ? decodeURIComponent(match[1]) : null
}

export async function apiFetch<T>(path: string, options: RequestInit = {}): Promise<T> {
  const method = (options.method ?? 'GET').toUpperCase()

  if (method !== 'GET' && method !== 'HEAD') {
    await ensureCsrfCookie()
  }

  const xsrfToken = readCookie('XSRF-TOKEN')

  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
      ...options.headers,
    },
  })

  if (!response.ok) {
    const body = await response.json().catch(() => null)
    throw new ApiError(
      body?.error?.message ?? `Permintaan gagal (${response.status})`,
      response.status,
      body?.error?.code,
      body?.error?.correlation_id,
      body?.error?.fields,
    )
  }

  if (response.status === 204) {
    return undefined as T
  }

  return response.json() as Promise<T>
}
