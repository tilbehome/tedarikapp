import { useEffect, useState } from 'react';
import { Bookmark, Columns3, Rows3, Star, Trash2 } from 'lucide-react';
import { gorunumler as gorunumlerApi } from '../../api/endpoints';
import type { KayitliGorunum } from '../../api/types';
import { Popover } from '../ui/Katman';
import { useToast } from '../Toast';
import { messageOf } from '../../lib/useAsync';

/**
 * K105 §2.3 — TABLO AYARLARI: sütun göster/gizle · yoğunluk · kaydedilmiş
 * görünüm. Tek bileşen, her tablo ekranı aynı popover'ı kullanır (§3).
 *
 * Sütun/yoğunluk tercihi KULLANICI BAŞINA tarayıcıda saklanır
 * (`localStorage`, anahtar `tablo:<ekran>`); kaydedilmiş görünümler sunucuda
 * (`/api/gorunumler/<ekran>`, kesif.gorunumler deseni) — ad + o anki sorgu
 * (sekme/sıralama/gruplama/sütunlar/yoğunluk). Varsayılan görünüm TEK olur.
 */
export type Yogunluk = 'ferah' | 'sik';

export interface SutunTanimi {
  anahtar: string;
  etiket: string;
  /** Kapatılamaz (kimlik sütunu). */
  sabit?: boolean;
}

export interface TabloTercihi {
  gizli: string[];
  yogunluk: Yogunluk;
}

export function tabloTercihiOku(ekran: string, varsayilan: TabloTercihi): TabloTercihi {
  try {
    const ham = window.localStorage.getItem(`tablo:${ekran}`);
    if (!ham) return varsayilan;
    const veri = JSON.parse(ham) as Partial<TabloTercihi>;
    return {
      gizli: Array.isArray(veri.gizli) ? veri.gizli.filter((s): s is string => typeof s === 'string') : varsayilan.gizli,
      yogunluk: veri.yogunluk === 'sik' ? 'sik' : 'ferah',
    };
  } catch {
    return varsayilan;
  }
}

export function tabloTercihiYaz(ekran: string, tercih: TabloTercihi): void {
  try {
    window.localStorage.setItem(`tablo:${ekran}`, JSON.stringify(tercih));
  } catch {
    // Depolama kapalı olabilir (özel pencere): tercih oturumla sınırlı kalır, hata değildir.
  }
}

export default function TabloAyarlari({
  ekran,
  sutunlar,
  tercih,
  onTercih,
  sorgu,
  onGorunumUygula,
}: {
  ekran: string;
  sutunlar: SutunTanimi[];
  tercih: TabloTercihi;
  onTercih: (yeni: TabloTercihi) => void;
  /** Şu anki ekran durumu (URL) — görünüm olarak kaydedilir. */
  sorgu: Record<string, string>;
  onGorunumUygula: (sorgu: Record<string, string>) => void;
}) {
  const push = useToast((state) => state.push);
  const [acik, setAcik] = useState(false);
  const [gorunumler, setGorunumler] = useState<KayitliGorunum[]>([]);
  const [yeniAd, setYeniAd] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let iptal = false;
    gorunumlerApi
      .hepsi(ekran)
      .then((sonuc) => {
        if (!iptal) setGorunumler(sonuc.gorunumler);
      })
      .catch(() => {
        // Görünüm listesi süs değil ama ekranı da durdurmaz; kaydetme denemesinde hata görünür.
      });
    return () => {
      iptal = true;
    };
  }, [ekran]);

  const degistir = (yeni: TabloTercihi) => {
    tabloTercihiYaz(ekran, yeni);
    onTercih(yeni);
  };

  const kaydet = async (varsayilan: boolean) => {
    const ad = yeniAd.trim();
    if (ad === '') return;
    setBusy(true);
    try {
      const sonuc = await gorunumlerApi.kaydet(ekran, { ad, sorgu: { ...sorgu, gizli: tercih.gizli.join(','), yogunluk: tercih.yogunluk }, varsayilan });
      setGorunumler(sonuc.gorunumler);
      setYeniAd('');
      push(`"${ad}" görünümü kaydedildi.`);
    } catch (hata) {
      push(messageOf(hata), 'error');
    } finally {
      setBusy(false);
    }
  };

  const sil = async (ad: string) => {
    setBusy(true);
    try {
      const sonuc = await gorunumlerApi.sil(ekran, ad);
      setGorunumler(sonuc.gorunumler);
      push(`"${ad}" görünümü silindi.`);
    } catch (hata) {
      push(messageOf(hata), 'error');
    } finally {
      setBusy(false);
    }
  };

  const uygula = (g: KayitliGorunum) => {
    const { gizli, yogunluk, ...sorguKalan } = g.sorgu;
    if (gizli !== undefined || yogunluk !== undefined) {
      degistir({
        gizli: gizli ? gizli.split(',').filter(Boolean) : tercih.gizli,
        yogunluk: yogunluk === 'sik' ? 'sik' : 'ferah',
      });
    }
    onGorunumUygula(sorguKalan);
    setAcik(false);
  };

  return (
    <div className="relative">
      <button type="button" className="btn-ghost" aria-haspopup="dialog" aria-expanded={acik} onClick={() => setAcik((v) => !v)} data-testid="tablo-ayarlari">
        <Columns3 className="h-4 w-4" aria-hidden /> Sütunlar
      </button>
      <Popover acik={acik} onKapat={() => setAcik(false)} hizalama="sag">
        <div className="w-72 space-y-3 text-sm">
          <fieldset>
            <legend className="mb-1 flex items-center gap-1 text-xs font-semibold text-ink-3">
              <Columns3 className="h-3.5 w-3.5" aria-hidden /> Sütunlar
            </legend>
            <ul className="space-y-1">
              {sutunlar.map((s) => (
                <li key={s.anahtar}>
                  <label className="flex items-center gap-2">
                    <input
                      type="checkbox"
                      checked={!tercih.gizli.includes(s.anahtar)}
                      disabled={s.sabit}
                      onChange={(olay) =>
                        degistir({
                          ...tercih,
                          gizli: olay.target.checked ? tercih.gizli.filter((g) => g !== s.anahtar) : [...tercih.gizli, s.anahtar],
                        })
                      }
                    />
                    {s.etiket}
                  </label>
                </li>
              ))}
            </ul>
          </fieldset>
          <fieldset>
            <legend className="mb-1 flex items-center gap-1 text-xs font-semibold text-ink-3">
              <Rows3 className="h-3.5 w-3.5" aria-hidden /> Yoğunluk
            </legend>
            <div className="flex gap-1" role="radiogroup" aria-label="Yoğunluk">
              {(['ferah', 'sik'] as Yogunluk[]).map((y) => (
                <button
                  key={y}
                  type="button"
                  role="radio"
                  aria-checked={tercih.yogunluk === y}
                  className={`rounded-md px-2 py-1 ${tercih.yogunluk === y ? 'bg-navy text-white' : 'bg-g100 text-ink-2'}`}
                  onClick={() => degistir({ ...tercih, yogunluk: y })}
                >
                  {y === 'ferah' ? 'Rahat' : 'Sıkı'}
                </button>
              ))}
            </div>
          </fieldset>
          <fieldset>
            <legend className="mb-1 flex items-center gap-1 text-xs font-semibold text-ink-3">
              <Bookmark className="h-3.5 w-3.5" aria-hidden /> Kaydedilmiş görünümler
            </legend>
            {gorunumler.length === 0 ? <p className="text-xs text-ink-3">Henüz görünüm yok; aşağıdan bu ekran durumunu adlandırıp kaydedin.</p> : null}
            <ul className="space-y-1">
              {gorunumler.map((g) => (
                <li key={g.ad} className="flex items-center gap-1">
                  <button type="button" className="flex-1 truncate rounded-md px-2 py-1 text-left hover:bg-g50" onClick={() => uygula(g)}>
                    {g.varsayilan ? <Star className="mr-1 inline h-3 w-3 text-warn" aria-label="Varsayılan" /> : null}
                    {g.ad}
                  </button>
                  <button type="button" className="rounded p-1 text-ink-3 hover:text-err" aria-label={`${g.ad} görünümünü sil`} disabled={busy} onClick={() => void sil(g.ad)}>
                    <Trash2 className="h-3.5 w-3.5" aria-hidden />
                  </button>
                </li>
              ))}
            </ul>
            <div className="mt-2 flex gap-1">
              <input className="field-input !min-h-8 flex-1 text-xs" placeholder="Görünüm adı" aria-label="Görünüm adı" value={yeniAd} onChange={(olay) => setYeniAd(olay.target.value)} />
              <button type="button" className="btn-primary !min-h-8 !px-2 !text-xs" disabled={busy || yeniAd.trim() === ''} onClick={() => void kaydet(false)}>
                Kaydet
              </button>
            </div>
          </fieldset>
          <p className="text-xs text-ink-3">Sütunlar ve filtreler görünüm bazında hatırlanır.</p>
        </div>
      </Popover>
    </div>
  );
}
