import { Search } from 'lucide-react';
import { gruplariSuz, type AyarBolumu } from './sekmeler';

/**
 * AYARLAR SOL GEZİNME (V3-B yeniden tasarım · madde 1-2-6).
 *
 * YATAY ŞERİT NEDEN GİTTİ: on altı madde bir şeride sığmıyordu; kullanıcı
 * neyin var olduğunu tek bakışta göremiyor, aradığını yatay kaydırarak
 * arıyordu. Dikey sütun on altısını da aynı anda gösterir.
 *
 * GRUPLAR BİR HARİTA KURAR: "fiyatla ilgili bir şey arıyorum" diyen kişi
 * doğrudan FİYAT VE DİL bloğuna bakar. Açıklama satırı da bunun parçası —
 * "Keşif & Skor" tek başına ne olduğunu söylemez, "Sinyal ve puanlama
 * modeli" söyler.
 *
 * ARAMA AD + AÇIKLAMA + GİZLİ SÖZCÜKLERİ tarar: kullanıcı "api anahtarı"
 * yazınca "Çeviri Sağlayıcısı" çıkmalı, oysa o kelimeler başlıkta geçmiyor.
 */
export default function AyarGezinme({
  aktifKod,
  sorgu,
  onSorgu,
  onSec,
  rozetler,
}: {
  aktifKod: string;
  sorgu: string;
  onSorgu: (deger: string) => void;
  onSec: (bolum: AyarBolumu) => void;
  /** Dikkat isteyen bölüm kodları (madde 7) — GERÇEK sinyallerden. */
  rozetler: Set<string>;
}) {
  const gruplar = gruplariSuz(sorgu);

  return (
    <nav
      className="sticky top-16 flex max-h-[calc(100dvh-5rem)] flex-col gap-1 overflow-y-auto pr-1"
      aria-label="Ayar bölümleri"
      data-testid="ayar-gezinme"
    >
      <div className="sticky top-0 z-10 bg-app pb-2">
        <div className="relative">
          <Search
            size={15}
            className="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-ink-3"
            aria-hidden
          />
          <input
            type="search"
            className="field-input pl-8"
            placeholder="Ayarlarda ara..."
            value={sorgu}
            onChange={(olay) => onSorgu(olay.target.value)}
            aria-label="Ayarlarda ara"
            data-testid="ayar-arama"
          />
        </div>
      </div>

      {gruplar.length === 0 ? (
        // TASARLANMIŞ BOŞ DURUM: çıplak beyazlık "bozuldu mu?" dedirtir.
        <p className="rounded-lg border border-line-soft bg-g50 p-3 text-sm text-ink-3" data-testid="ayar-arama-bos">
          Eşleşen ayar yok.
          <br />
          Farklı bir kelime deneyin.
        </p>
      ) : (
        gruplar.map((grup) => (
          <div key={grup.baslik} className="mb-1">
            <div className="px-2 pb-1 pt-2 text-[10px] font-semibold tracking-[0.12em] text-ink-3">
              {grup.baslik}
            </div>
            {grup.bolumler.map((bolum) => {
              const Ikon = bolum.ikon;
              const aktif = bolum.kod === aktifKod;

              return (
                <button
                  key={bolum.kod}
                  type="button"
                  className={`flex w-full items-start gap-2.5 rounded-lg px-2 py-2 text-left transition-colors ${
                    aktif ? 'bg-blue-soft' : 'hover:bg-g50'
                  }`}
                  onClick={() => onSec(bolum)}
                  aria-current={aktif ? 'page' : undefined}
                  data-testid={`ayar-sekme-${bolum.kod}`}
                >
                  <span
                    className={`mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg ${
                      aktif ? 'bg-blue text-white' : 'bg-g100 text-ink-3'
                    }`}
                  >
                    <Ikon size={15} aria-hidden />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className={`flex items-center gap-1.5 truncate text-sm ${aktif ? 'font-semibold text-ink' : 'text-ink-2'}`}>
                      {bolum.ad}
                      {/* Dikkat rozeti: sayı değil NOKTA. Sayı, kullanıcıya
                          saymadığımız bir şeyi saymış gibi gösterirdi. */}
                      {rozetler.has(bolum.kod) ? (
                        <i
                          className="size-1.5 shrink-0 rounded-full bg-gold"
                          aria-label="dikkat gerekiyor"
                          data-testid={`ayar-rozet-${bolum.kod}`}
                        />
                      ) : null}
                    </span>
                    <span className="block truncate text-xs text-ink-3">{bolum.aciklama}</span>
                  </span>
                </button>
              );
            })}
          </div>
        ))
      )}
    </nav>
  );
}
