// Klien HTTP tipis ke Laravel API (apps/api). Memakai cookie-based session
// Sanctum (credentials: 'include'), bukan bearer token, karena SPA dan API
// berada di domain yang sama secara logis (stateful) — lihat config/cors.php
// dan config/sanctum.php di apps/api.

const API_BASE = import.meta.env.VITE_API_BASE_URL ?? '/api'

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code?: string,
    public readonly correlationId?: string,
  ) {
    super(message)
  }
}

async function ensureCsrfCookie(): Promise<void> {
  await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
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
    )
  }

  if (response.status === 204) {
    return undefined as T
  }

  return response.json() as Promise<T>
}
