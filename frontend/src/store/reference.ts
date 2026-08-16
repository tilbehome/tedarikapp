import { create } from 'zustand';
import { categories as categoriesApi, system } from '../api/endpoints';
import type { Category, StateMachineMap } from '../api/types';

/**
 * Oturum boyunca değişmeyen referans veriler: kategoriler ve durum geçiş haritası.
 * Her ekranda yeniden çekilmez; kategori yönetimi değişiklik sonrası `loadCategories`
 * çağırır.
 */
interface ReferenceState {
  categories: Category[];
  machine: StateMachineMap | null;
  loaded: boolean;
  load: () => Promise<void>;
  loadCategories: () => Promise<void>;
  categoryName: (id: number | null) => string;
  reset: () => void;
}

export const useReference = create<ReferenceState>((set, get) => ({
  categories: [],
  machine: null,
  loaded: false,

  load: async () => {
    const [machine, list] = await Promise.all([system.stateMachine(), categoriesApi.all()]);
    set({ machine, categories: list, loaded: true });
  },

  loadCategories: async () => {
    set({ categories: await categoriesApi.all() });
  },

  categoryName: (id) => {
    if (id === null) return 'Kategorisiz';
    return get().categories.find((category) => category.id === id)?.name ?? 'Kategorisiz';
  },

  reset: () => set({ categories: [], machine: null, loaded: false }),
}));
