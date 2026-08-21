import { useSyncExternalStore } from 'react';

/**
 * TEMA — Açık / Koyu / Sistem (İE#16 D1.1 · V3 kanonu §4).
 *
 * Koyu tema İKİNCİ BİR CSS SETİ DEĞİLDİR: `tokens.css` içindeki
 * `[data-theme="dark"]` bloğu yalnız değişkenleri değiştirir, bileşenlerin
 * sınıfları aynı kalır. Bu dosyanın tek işi kök öğeye doğru işareti koymaktır.
 *
 * "Sistem" seçili iken işaret KALDIRILIR ve tarayıcının `prefers-color-scheme`
 * tercihi geçerli olur — ayrıca dinlenir, kullanıcı işletim sistemi temasını
 * değiştirdiğinde panel anında uyar.
 */

export type Tema = 'acik' | 'koyu' | 'sistem';

const ANAHTAR = 'tdk-tema';
const dinleyiciler = new Set<() => void>();

function okunanTema(): Tema {
  const kayitli = localStorage.getItem(ANAHTAR);

  return kayitli === 'acik' || kayitli === 'koyu' || kayitli === 'sistem' ? kayitli : 'sistem';
}

let mevcut: Tema = typeof localStorage === 'undefined' ? 'sistem' : okunanTema();

function sistemKoyuMu(): boolean {
  return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

/** Kök öğeye `data-theme` yazar; "sistem" seçiliyse işareti KALDIRIR. */
export function temayiUygula(tema: Tema = mevcut): void {
  const kok = document.documentElement;
  const koyu = tema === 'koyu' || (tema === 'sistem' && sistemKoyuMu());

  if (tema === 'sistem') {
    kok.removeAttribute('data-theme');
    // Tarayıcı form denetimleri ve kaydırma çubuğu da doğru tonda görünsün.
    kok.style.colorScheme = koyu ? 'dark' : 'light';
  } else {
    kok.setAttribute('data-theme', tema === 'koyu' ? 'dark' : 'light');
    kok.style.colorScheme = koyu ? 'dark' : 'light';
  }
}

export function temaAyarla(tema: Tema): void {
  mevcut = tema;
  localStorage.setItem(ANAHTAR, tema);
  temayiUygula(tema);
  dinleyiciler.forEach((dinleyici) => dinleyici());
}

/** Sıradaki tema: açık → koyu → sistem → açık (tek düğmeyle döngü). */
export function temaDondur(): Tema {
  const sonraki: Tema = mevcut === 'acik' ? 'koyu' : mevcut === 'koyu' ? 'sistem' : 'acik';
  temaAyarla(sonraki);

  return sonraki;
}

export function useTema(): Tema {
  return useSyncExternalStore(
    (dinleyici) => {
      dinleyiciler.add(dinleyici);

      return () => dinleyiciler.delete(dinleyici);
    },
    () => mevcut,
    () => 'sistem' as Tema,
  );
}

/**
 * Uygulama açılışında bir kez çağrılır: kayıtlı tercihi uygular ve "sistem"
 * seçiliyken işletim sistemi temasını dinlemeye başlar.
 */
export function temayiBaslat(): void {
  temayiUygula();
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (mevcut === 'sistem') {
      temayiUygula();
      dinleyiciler.forEach((dinleyici) => dinleyici());
    }
  });
}

export const temaEtiketleri: Record<Tema, string> = {
  acik: 'Açık tema',
  koyu: 'Koyu tema',
  sistem: 'Sistem teması',
};
