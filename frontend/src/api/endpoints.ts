import { api } from './client';
import type {
  ActivityEntry,
  Category,
  Product,
  ProductStatus,
  RateHistoryEntry,
  Settings,
  StateMachineMap,
  SupplyList,
  SystemStatus,
  Trash,
  User,
  Visibility,
} from './types';

/**
 * Uç tanımları — docs/10 ile birebir. Ekranlar ham yol dizesi kullanmaz;
 * sözleşme değişirse tek dosya değişir.
 */

export const auth = {
  login: (email: string, password: string, remember: boolean) =>
    // K45: 2FA tanımlıysa {stage:'totp'}, değilse doğrudan {user} döner (şifre yeterli).
    api.post<{ stage?: string; user?: User }>('/api/auth/login', { email, password, remember }, { silentUnauthorized: true }),
  totp: (code: string) => api.post<{ user: User }>('/api/auth/totp', { code }, { silentUnauthorized: true }),
  recovery: (code: string) =>
    api.post<{ user: User; remaining_codes: number }>('/api/auth/recovery', { code }, { silentUnauthorized: true }),
  me: () => api.get<{ user: User; csrf_token: string }>('/api/auth/me', { silentUnauthorized: true }),
  logout: () => api.post<void>('/api/auth/logout'),
  sessions: () => api.get<{ id: number; created_at: string; expires_at: string; is_current: boolean }[]>('/api/auth/sessions'),
  revokeSession: (id: number) => api.delete<void>(`/api/auth/sessions/${id}`),
};

export const lists = {
  all: (params: { visibility?: Visibility; status?: string; q?: string } = {}) => {
    const query = new URLSearchParams();
    if (params.visibility) query.set('visibility', params.visibility);
    if (params.status) query.set('status', params.status);
    if (params.q) query.set('q', params.q);
    const suffix = query.toString();
    return api.get<SupplyList[]>(`/api/lists${suffix ? `?${suffix}` : ''}`);
  },
  find: (id: number) => api.get<SupplyList>(`/api/lists/${id}`),
  create: (body: { name: string; period?: string; supplier_name?: string; note?: string }) =>
    api.post<SupplyList>('/api/lists', body),
  update: (id: number, body: Record<string, unknown>) => api.patch<SupplyList>(`/api/lists/${id}`, body),
  remove: (id: number) => api.delete<void>(`/api/lists/${id}`),
  duplicate: (id: number, name?: string) =>
    api.post<SupplyList>(`/api/lists/${id}/duplicate`, name ? { name } : {}),
};

export const products = {
  forList: (listId: number, params: { status?: ProductStatus; q?: string; category_id?: number } = {}) => {
    const query = new URLSearchParams();
    if (params.status) query.set('status', params.status);
    if (params.q) query.set('q', params.q);
    if (params.category_id !== undefined) query.set('category_id', String(params.category_id));
    const suffix = query.toString();
    return api.get<Product[]>(`/api/lists/${listId}/products${suffix ? `?${suffix}` : ''}`);
  },
  create: (listId: number, body: Record<string, unknown>) =>
    api.post<Product>(`/api/lists/${listId}/products`, body),
  update: (id: number, body: Record<string, unknown>) => api.patch<Product>(`/api/products/${id}`, body),
  setStatus: (id: number, status: ProductStatus) =>
    api.patch<Product>(`/api/products/${id}/status`, { status }),
  remove: (id: number) => api.delete<void>(`/api/products/${id}`),
  bulk: (body: { ids: number[]; action: 'status' | 'move' | 'delete'; status?: ProductStatus; target_list_id?: number }) =>
    api.patch<{ updated: number; failed: { id: number; error: string }[] }>('/api/products/bulk', body),
  reorder: (listId: number, orderedIds: number[]) =>
    api.patch<{ updated: number }>(`/api/lists/${listId}/products/reorder`, { ordered_ids: orderedIds }),
};

export const categories = {
  all: () => api.get<Category[]>('/api/categories'),
  create: (name: string) => api.post<Category>('/api/categories', { name }),
  update: (id: number, body: { name?: string; sort?: number }) => api.patch<Category>(`/api/categories/${id}`, body),
  remove: (id: number) => api.delete<void>(`/api/categories/${id}`),
};

export const settings = {
  read: () => api.get<Settings>('/api/settings'),
  updateRates: (body: { yuan_tl?: string; usd_tl?: string }) =>
    api.put<{ yuan_tl: string; usd_tl: string }>('/api/settings/rates', body),
  rateHistory: (currency?: string) =>
    api.get<RateHistoryEntry[]>(`/api/settings/rates/history${currency ? `?currency=${currency}` : ''}`),
};

export const system = {
  status: () => api.get<SystemStatus>('/api/system/status'),
  /** İzinli durum geçişleri — arayüz kendi kopyasını TUTMAZ (İE#8 §2). */
  stateMachine: () => api.get<StateMachineMap>('/api/system/state-machine'),
  migrate: () => api.post<{ applied: string[]; applied_count: number }>('/api/system/migrate'),
};

export const trash = {
  read: () => api.get<Trash>('/api/trash'),
  restore: (type: 'lists' | 'products', id: number) =>
    api.post<{ type: string; id: number }>(`/api/trash/${type}/${id}/restore`),
  purge: (type: 'lists' | 'products', id: number) => api.delete<void>(`/api/trash/${type}/${id}`),
};

export const activity = {
  read: (params: { entity_type?: string; page?: number } = {}) => {
    const query = new URLSearchParams();
    if (params.entity_type) query.set('entity_type', params.entity_type);
    if (params.page) query.set('page', String(params.page));
    const suffix = query.toString();
    return api.get<ActivityEntry[]>(`/api/activity${suffix ? `?${suffix}` : ''}`);
  },
};
