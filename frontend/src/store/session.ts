import { create } from 'zustand';
import { auth } from '../api/endpoints';
import { ApiError, setCsrfToken } from '../api/client';
import type { User } from '../api/types';

/**
 * Oturum durumu.
 *
 * Aşamalar backend'inkiyle aynı: anonim → şifre doğru (TOTP bekliyor) → girişli.
 * CSRF token'ı `GET /api/auth/me` yanıtından gelir ve API istemcisine verilir.
 */

export type Stage = 'anonymous' | 'awaiting-totp' | 'authenticated';

interface SessionState {
  stage: Stage;
  user: User | null;
  /** Açılışta oturum var mı diye bir kez bakılır. */
  checked: boolean;
  check: () => Promise<void>;
  login: (email: string, password: string, remember: boolean) => Promise<void>;
  submitTotp: (code: string) => Promise<void>;
  submitRecovery: (code: string) => Promise<number>;
  logout: () => Promise<void>;
  /** 401 alındığında API istemcisi bunu tetikler. */
  drop: () => void;
}

export const useSession = create<SessionState>((set) => ({
  stage: 'anonymous',
  user: null,
  checked: false,

  check: async () => {
    try {
      const data = await auth.me();
      setCsrfToken(data.csrf_token);
      set({ stage: 'authenticated', user: data.user, checked: true });
    } catch (error) {
      const stage: Stage = error instanceof ApiError && error.code === 'TOTP_REQUIRED' ? 'awaiting-totp' : 'anonymous';
      setCsrfToken(null);
      set({ stage, user: null, checked: true });
    }
  },

  login: async (email, password, remember) => {
    await auth.login(email, password, remember);
    set({ stage: 'awaiting-totp' });
  },

  submitTotp: async (code) => {
    const data = await auth.totp(code);
    const me = await auth.me();
    setCsrfToken(me.csrf_token);
    set({ stage: 'authenticated', user: data.user });
  },

  submitRecovery: async (code) => {
    const data = await auth.recovery(code);
    const me = await auth.me();
    setCsrfToken(me.csrf_token);
    set({ stage: 'authenticated', user: data.user });
    return data.remaining_codes;
  },

  logout: async () => {
    try {
      await auth.logout();
    } finally {
      setCsrfToken(null);
      set({ stage: 'anonymous', user: null });
    }
  },

  drop: () => {
    setCsrfToken(null);
    set({ stage: 'anonymous', user: null });
  },
}));
