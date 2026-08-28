import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowRight, Command, Search } from 'lucide-react';
import { menuGruplari } from '../../lib/menu';
import { temaAyarla } from '../../lib/tema';

/**
 * KOMUT PALETİ — Ctrl+K (İE#16 D1.7 · OKUBENI §1.2: "uygulama hissinin merkezi").
 *
 * İki tür girdi vardır: EKRAN (git) ve EYLEM (yap). Ok tuşları + Enter ile
 * kullanılır; fare gerekmez.
 *
 * ARAMA TÜRKÇE KARAKTER DUYARSIZDIR: "kesif" yazan "Keşif"i, "gelen kutusu"
 * yazan "Gelen Kutusu"nu bulur. Normalizasyon i/ı, ş/s, ğ/g, ü/u, ö/o, ç/c
 * çiftlerini eşitler — Türkçe klavye kullanmayan biri de aynı sonucu alır.
 */

export interface Komut {
  id: string;
  ad: string;
  ipucu?: string;
  grup: string;
  calistir: () => void;
}

/** Türkçe karakterleri sadeleştirir; küçük harfe çevirir. */
export function sadelestir(metin: string): string {
  return metin
    .toLocaleLowerCase('tr-TR')
    .replace(/ı/g, 'i')
    .replace(/ş/g, 's')
    .replace(/ğ/g, 'g')
    .replace(/ü/g, 'u')
    .replace(/ö/g, 'o')
    .replace(/ç/g, 'c')
    .replace(/â/g, 'a')
    .replace(/î/g, 'i')
    .trim();
}

export function komutlariSuz(komutlar: Komut[], sorgu: string): Komut[] {
  const aranan = sadelestir(sorgu);
  if (aranan === '') return komutlar;

  return komutlar.filter((komut) => {
    const havuz = sadelestir(komut.ad + ' ' + (komut.ipucu ?? '') + ' ' + komut.grup);

    // Her kelime ayrı aranır: "yeni liste" → "yeni" VE "liste" geçmeli.
    return aranan.split(/\s+/).every((parca) => havuz.includes(parca));
  });
}

export default function KomutPaleti({ onKapat }: { onKapat: () => void }) {
  const navigate = useNavigate();
  const [sorgu, setSorgu] = useState('');
  const [secili, setSecili] = useState(0);
  const girisRef = useRef<HTMLInputElement>(null);

  const komutlar = useMemo<Komut[]>(() => {
    const git: Komut[] = menuGruplari.flatMap((grup) =>
      grup.ogeler
        .filter((oge) => oge.hazir)
        .map((oge) => ({
          id: 'git:' + oge.to,
          ad: oge.label,
          ipucu: grup.baslik,
          grup: 'Ekranlar',
          calistir: () => navigate(oge.to),
        })),
    );

    const eylemler: Komut[] = [
      {
        id: 'yeni-liste',
        ad: 'Yeni liste oluştur',
        ipucu: 'Listeler ekranını açar',
        grup: 'Eylemler',
        calistir: () => navigate('/listeler?yeni=1'),
      },
      {
        id: 'gelen-kutusu',
        ad: 'Gelen kutusunu işle',
        grup: 'Eylemler',
        calistir: () => navigate('/gelen-kutusu'),
      },
      { id: 'kategoriler', ad: 'Kategorileri düzenle', grup: 'Eylemler', calistir: () => navigate('/ayarlar/kategoriler') },
      { id: 'kurlar', ad: 'Kurları güncelle', grup: 'Eylemler', calistir: () => navigate('/ayarlar') },
      { id: 'cop', ad: 'Çöp kutusunu aç', grup: 'Eylemler', calistir: () => navigate('/cop-kutusu') },
      { id: 'aktivite', ad: 'Aktivite günlüğü', grup: 'Eylemler', calistir: () => navigate('/aktivite') },
      { id: 'bilesenler', ad: 'Bileşen kitaplığı', ipucu: 'Tasarım sistemi örnekleri', grup: 'Eylemler', calistir: () => navigate('/bilesenler') },
      { id: 'tema-acik', ad: 'Açık temaya geç', grup: 'Görünüm', calistir: () => temaAyarla('acik') },
      { id: 'tema-koyu', ad: 'Koyu temaya geç', grup: 'Görünüm', calistir: () => temaAyarla('koyu') },
      { id: 'tema-sistem', ad: 'Sistem temasını kullan', grup: 'Görünüm', calistir: () => temaAyarla('sistem') },
    ];

    return [...git, ...eylemler];
  }, [navigate]);

  const sonuclar = useMemo(() => komutlariSuz(komutlar, sorgu), [komutlar, sorgu]);

  // Bileşen yalnız açıkken monte edilir (Kabuk öyle çağırır), bu yüzden durum
  // sıfırlamaya gerek yoktur — yalnız odak verilir.
  useEffect(() => {
    girisRef.current?.focus();
  }, []);

  const calistir = (komut: Komut | undefined) => {
    if (komut === undefined) return;
    onKapat();
    komut.calistir();
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-start justify-center bg-g900/45 px-4 pt-[12vh]"
      role="presentation"
      onClick={(olay) => {
        if (olay.target === olay.currentTarget) onKapat();
      }}
    >
      <div
        className="w-full max-w-xl overflow-hidden rounded-lg border border-line bg-surface shadow-3"
        role="dialog"
        aria-modal="true"
        aria-label="Komut paleti"
      >
        <div className="flex items-center gap-2 border-b border-line px-4">
          <Search size={17} className="shrink-0 text-ink-3" aria-hidden />
          <input
            ref={girisRef}
            className="min-h-12 w-full bg-transparent text-base text-ink outline-none placeholder:text-ink-3"
            placeholder="Ne yapmak istiyorsun?"
            value={sorgu}
            onChange={(olay) => {
              setSorgu(olay.target.value);
              // Sorgu değişince seçim başa döner — bu bir OLAY, efekt değil.
              setSecili(0);
            }}
            onKeyDown={(olay) => {
              if (olay.key === 'ArrowDown') {
                olay.preventDefault();
                setSecili((x) => (sonuclar.length === 0 ? 0 : (x + 1) % sonuclar.length));
              } else if (olay.key === 'ArrowUp') {
                olay.preventDefault();
                setSecili((x) => (sonuclar.length === 0 ? 0 : (x - 1 + sonuclar.length) % sonuclar.length));
              } else if (olay.key === 'Enter') {
                olay.preventDefault();
                calistir(sonuclar[secili]);
              } else if (olay.key === 'Escape') {
                onKapat();
              }
            }}
          />
          <kbd className="hidden shrink-0 rounded-md border border-line px-1.5 py-0.5 text-xs text-ink-3 sm:block">
            Esc
          </kbd>
        </div>

        <div className="max-h-[52vh] overflow-y-auto p-2">
          {sonuclar.length === 0 ? (
            <p className="px-3 py-6 text-center text-md text-ink-3">Eşleşen komut yok.</p>
          ) : (
            sonuclar.map((komut, index) => (
              <button
                key={komut.id}
                type="button"
                className={`flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-md ${
                  index === secili ? 'bg-blue-soft text-navy' : 'text-ink-2 hover:bg-g50'
                }`}
                onMouseEnter={() => setSecili(index)}
                onClick={() => calistir(komut)}
              >
                <Command size={15} className="shrink-0 opacity-60" aria-hidden />
                <span className="flex-1 truncate font-medium">{komut.ad}</span>
                {komut.ipucu && <span className="truncate text-sm text-ink-3">{komut.ipucu}</span>}
                {index === secili && <ArrowRight size={14} className="shrink-0" aria-hidden />}
              </button>
            ))
          )}
        </div>

        <div className="flex items-center gap-3 border-t border-line px-4 py-2 text-xs text-ink-3">
          <span>↑↓ gez</span>
          <span>↵ çalıştır</span>
          <span>Esc kapat</span>
        </div>
      </div>
    </div>
  );
}
