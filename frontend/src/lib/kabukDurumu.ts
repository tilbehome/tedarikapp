import { useSyncExternalStore } from 'react';

/**
 * KALICI KABUK DURUMU (İE#16 D1.4).
 *
 * Menü daraltması, açık/kapalı grup başlıkları ve son bakılan kayıtlar
 * tarayıcıda saklanır: kullanıcı paneli her açtığında bıraktığı düzeni bulur.
 * Ekranın SÜZGEÇ ve SIRALAMA durumu burada DEĞİL, URL'de tutulur (D1.4) —
 * çünkü o durumun paylaşılabilir ve geri tuşuyla gezilebilir olması gerekir.
 */

interface KabukDurumu {
  daraltilmis: boolean;
  kapaliGruplar: string[];
  sonBakilanlar: { to: string; label: string }[];
}

const ANAHTAR = 'tdk-kabuk';
const BOS: KabukDurumu = { daraltilmis: false, kapaliGruplar: [], sonBakilanlar: [] };
const dinleyiciler = new Set<() => void>();

function oku(): KabukDurumu {
  try {
    const ham = localStorage.getItem(ANAHTAR);
    if (ham === null) return BOS;
    const veri = JSON.parse(ham) as Partial<KabukDurumu>;

    return {
      daraltilmis: veri.daraltilmis === true,
      kapaliGruplar: Array.isArray(veri.kapaliGruplar) ? veri.kapaliGruplar.filter((x) => typeof x === 'string') : [],
      sonBakilanlar: Array.isArray(veri.sonBakilanlar) ? veri.sonBakilanlar.slice(0, 3) : [],
    };
  } catch {
    // Bozuk kayıt panelin açılışını engellemez — varsayılana dönülür.
    return BOS;
  }
}

let durum: KabukDurumu = typeof localStorage === 'undefined' ? BOS : oku();

function yaz(yeni: KabukDurumu): void {
  durum = yeni;
  try {
    localStorage.setItem(ANAHTAR, JSON.stringify(yeni));
  } catch {
    // Depolama dolu/kapalı olabilir; durum bellekte yaşamaya devam eder.
  }
  dinleyiciler.forEach((dinleyici) => dinleyici());
}

export function menuyuDaralt(daraltilmis: boolean): void {
  yaz({ ...durum, daraltilmis });
}

export function menuyuCevir(): void {
  yaz({ ...durum, daraltilmis: !durum.daraltilmis });
}

export function grubuCevir(baslik: string): void {
  const kapali = durum.kapaliGruplar.includes(baslik)
    ? durum.kapaliGruplar.filter((x) => x !== baslik)
    : [...durum.kapaliGruplar, baslik];
  yaz({ ...durum, kapaliGruplar: kapali });
}

/** Son bakılan kayıtlar — en yeni başta, en çok 3 (kanon §3). */
export function sonBakilaniEkle(kayit: { to: string; label: string }): void {
  if (kayit.label.trim() === '') return;
  const kalan = durum.sonBakilanlar.filter((x) => x.to !== kayit.to);
  yaz({ ...durum, sonBakilanlar: [kayit, ...kalan].slice(0, 3) });
}

export function useKabukDurumu(): KabukDurumu {
  return useSyncExternalStore(
    (dinleyici) => {
      dinleyiciler.add(dinleyici);

      return () => dinleyiciler.delete(dinleyici);
    },
    () => durum,
    () => BOS,
  );
}
