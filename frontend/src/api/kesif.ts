import { api } from './client';

/**
 * KEŞİF HAVUZU İSTEMCİSİ (İE#21 B1).
 *
 * Sorgu durumu URL'de yaşar (kaydedilebilir, paylaşılabilir, yenilemeye dayanıklı);
 * bu dosya yalnız o durumu HTTP'ye çevirir. Panel filtre listelerini kendi
 * TUTMAZ — seçenekler sunucudan gelir, yoksa iki taraf ayrı düşer.
 */

export interface KesifSatiri {
  id: number;
  ad: string;
  ad_orijinal: string | null;
  kategori: string | null;
  platform: string | null;
  ilan_no: string | null;
  url: string | null;
  gorsel: string | null;
  video_var: boolean;
  satici: string | null;
  satis: number | null;
  satis_toplam: number | null;
  puan: number | null;
  yorum: number | null;
  moq: number | null;
  birim_fiyat: string | null;
  skor: number | null;
  bant: 'yuksek' | 'orta' | 'dusuk' | 'gizli';
  kapsam_disi: boolean;
  kume_anahtari: string | null;
  listede: boolean;
  hazir: boolean;
  yakalandi_at: string | null;
}

export interface KesifKumesi {
  kume_anahtari: string | null;
  kaynak_sayisi: number;
  en_ucuz: string | null;
  en_yuksek_skor: number | null;
  temsilci: KesifSatiri;
  uyeler: KesifSatiri[];
}

export interface KesifSonucu {
  kurulu: boolean;
  mesaj?: string;
  satirlar: KesifSatiri[];
  kumeler: KesifKumesi[] | null;
  toplam: number;
  sayfa: number;
  limit: number;
  secenekler: {
    platformlar: string[];
    kategoriler: string[];
    bantlar: string[];
    modlar: string[];
    esikler: { yuksek: number; orta: number };
  };
}

export interface KesifGorunumu {
  ad: string;
  sorgu: Record<string, string>;
  varsayilan: boolean;
}

export const kesif = {
  ara: (sorgu: URLSearchParams, signal?: AbortSignal) =>
    api.get<KesifSonucu>(`/api/kesif?${sorgu.toString()}`, { signal }),

  gorunumler: (signal?: AbortSignal) =>
    api.get<{ gorunumler: KesifGorunumu[] }>('/api/kesif/gorunumler', { signal }),

  gorunumKaydet: (ad: string, sorgu: Record<string, string>, varsayilan: boolean) =>
    api.post<{ gorunumler: KesifGorunumu[] }>('/api/kesif/gorunumler', { ad, sorgu, varsayilan }),

  gorunumSil: (ad: string) =>
    api.delete<{ gorunumler: KesifGorunumu[] }>(`/api/kesif/gorunumler/${encodeURIComponent(ad)}`),

  /** 2–6 ürün; yedincisi sunucuda reddedilir (matris okunamaz hâle gelir). */
  karsilastir: (ids: number[]) =>
    api.post<{ urunler: KesifSatiri[]; en_iyiler: Record<string, number | null> }>(
      '/api/kesif/karsilastir',
      { ids },
    ),
};
