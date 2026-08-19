/**
 * Panel API istemcisi (İE#11 C4): token YALNIZ chrome.storage.local'da yaşar —
 * sayfaya/console'a sızmaz, loglanmaz. Tüm istekler background'dan yapılır.
 */

import type { CapturePayload, SelectorSet } from './types';

export interface ExtensionSettings {
  panelUrl: string;
  token: string;
}

export async function getSettings(): Promise<ExtensionSettings> {
  const stored = await chrome.storage.local.get(['panelUrl', 'token']);
  return {
    panelUrl: typeof stored.panelUrl === 'string' ? stored.panelUrl.replace(/\/+$/, '') : '',
    token: typeof stored.token === 'string' ? stored.token : '',
  };
}

export async function saveSettings(settings: ExtensionSettings): Promise<void> {
  await chrome.storage.local.set({
    panelUrl: settings.panelUrl.replace(/\/+$/, ''),
    token: settings.token,
  });
}

interface Envelope<T> {
  success: boolean;
  data: T;
  error?: { code: string; message: string } | null;
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const { panelUrl, token } = await getSettings();
  if (panelUrl === '' || token === '') {
    throw new Error('AYAR_EKSIK');
  }

  const response = await fetch(panelUrl + path, {
    ...init,
    headers: {
      Authorization: `Bearer ${token}`,
      ...(init.body !== undefined ? { 'Content-Type': 'application/json' } : {}),
      ...(init.headers ?? {}),
    },
  });

  const envelope = (await response.json().catch(() => null)) as Envelope<T> | null;
  if (!response.ok || envelope === null || !envelope.success) {
    throw new Error(envelope?.error?.message ?? `Panel yanıt vermedi (HTTP ${response.status}).`);
  }
  return envelope.data;
}

export const panelApi = {
  selectors: (platform: string) => request<SelectorSet>(`/api/extension/selectors?platform=${platform}`),
  lists: () => request<{ id: number; name: string; status: string }[]>('/api/extension/lists'),
  capture: (payload: CapturePayload) =>
    request<{
      inbox_id: number;
      status: string;
      product_id: number | null;
      duplicate: { product_id: number; list_id: number; list_name: string } | null;
    }>('/api/capture', { method: 'POST', body: JSON.stringify(payload) }),
};
