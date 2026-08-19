import type { ApiErrorBody, Envelope } from './types';
import { errorMessages } from '../locales/tr';

/**
 * API istemcisi — docs/10 zarfının tek çözüm noktası.
 *
 * Sorumlulukları:
 *  • `{success, data, error, meta}` zarfını açar; hata durumunda `ApiError` fırlatır.
 *  • CSRF token'ını taşır (`X-CSRF-Token`) — GET dışı her istekte zorunlu.
 *  • 401'de oturumu düşürüp girişe yönlendirir.
 *  • Ağ hatasını da aynı `ApiError` biçimine çevirir; ekranlar tek tip hata görür.
 *
 * Para alanlarına DOKUNMAZ: gelen dize aynen aktarılır (K14/K29).
 */

export class ApiError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly status: number,
    public readonly fields: Record<string, string> = {},
    public readonly meta: Record<string, unknown> = {},
  ) {
    super(message);
    this.name = 'ApiError';
  }

  /** Alan bazlı hata varsa onu, yoksa genel mesajı döndürür. */
  fieldError(name: string): string | undefined {
    return this.fields[name];
  }
}

type Listener = () => void;

let csrfToken: string | null = null;
const unauthorizedListeners = new Set<Listener>();

export function setCsrfToken(token: string | null): void {
  csrfToken = token;
}

export function getCsrfToken(): string | null {
  return csrfToken;
}

/** Oturum düştüğünde (401) haber verilir — router girişe yönlendirir. */
export function onUnauthorized(listener: Listener): () => void {
  unauthorizedListeners.add(listener);
  return () => unauthorizedListeners.delete(listener);
}

/**
 * İE#10.5 Blok 2: bekleyen migration (503 MIGRATION_PENDING) haber verilir —
 * uygulama tam sayfa "Güncelleme tamamlanmalı" ekranına geçer.
 */
const migrationPendingListeners = new Set<Listener>();

export function onMigrationPending(listener: Listener): () => void {
  migrationPendingListeners.add(listener);
  return () => migrationPendingListeners.delete(listener);
}

interface RequestOptions {
  method?: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE';
  body?: unknown;
  /** 401'de yönlendirmeyi bastır (giriş ekranının kendi çağrıları için). */
  silentUnauthorized?: boolean;
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const method = options.method ?? 'GET';
  const headers: Record<string, string> = { Accept: 'application/json' };

  if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }
  if (method !== 'GET' && csrfToken) {
    headers['X-CSRF-Token'] = csrfToken;
  }

  let response: Response;
  try {
    response = await fetch(path, {
      method,
      headers,
      credentials: 'same-origin',
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
    });
  } catch {
    throw new ApiError('NETWORK', errorMessages.NETWORK ?? 'Sunucuya ulaşılamadı.', 0);
  }

  // 204: gövde yok (silme uçları).
  if (response.status === 204) {
    return undefined as T;
  }

  let envelope: Envelope<T> | null = null;
  try {
    envelope = (await response.json()) as Envelope<T>;
  } catch {
    envelope = null;
  }

  if (response.ok && envelope?.success) {
    return envelope.data;
  }

  const error: ApiErrorBody = envelope?.error ?? {
    code: 'SERVER_ERROR',
    message: errorMessages.SERVER_ERROR ?? 'Beklenmeyen bir hata oluştu.',
  };

  if (response.status === 401 && !options.silentUnauthorized) {
    setCsrfToken(null);
    unauthorizedListeners.forEach((listener) => listener());
  }

  if (response.status === 503 && error.code === 'MIGRATION_PENDING') {
    migrationPendingListeners.forEach((listener) => listener());
  }

  throw new ApiError(
    error.code,
    error.message || errorMessages[error.code] || 'Beklenmeyen bir hata oluştu.',
    response.status,
    error.fields ?? {},
    (envelope?.meta as Record<string, unknown>) ?? {},
  );
}

export const api = {
  get: <T>(path: string, options?: RequestOptions) => request<T>(path, { ...options, method: 'GET' }),
  post: <T>(path: string, body?: unknown, options?: RequestOptions) =>
    request<T>(path, { ...options, method: 'POST', body: body ?? {} }),
  patch: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PATCH', body: body ?? {} }),
  put: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PUT', body: body ?? {} }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
};
