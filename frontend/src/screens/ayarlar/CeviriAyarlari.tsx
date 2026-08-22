import { useState, type FormEvent } from 'react';
import { KeyRound, Languages, Sparkles } from 'lucide-react';
import { ceviri as ceviriApi } from '../../api/endpoints';
import { useAsync, messageOf } from '../../lib/useAsync';
import { ErrorNote, Field, Skeleton } from '../../components/ui';
import { useToast } from '../../components/Toast';
import EylemDugmesi from '../../components/EylemDugmesi';

/**
 * AYARLAR > ÇEVİRİ (İE#20 C4).
 *
 * Üç şey yapılır: sağlayıcı/model seçimi, API anahtarının GİRİLMESİ (okunması
 * değil) ve hedef dillerin belirlenmesi. Bir de "çevrilmemiş N ürünü çevir"
 * düğmesi — bu iş KUYRUĞA girer, ekran beklemez.
 *
 * ANAHTAR GERİ GÖSTERİLMEZ. Panel yalnız "tanımlı" bilgisini ve maskeli
 * önizlemeyi görür. Bir sırrı ekrana basmanın tek meşru anı onu ÜRETTİĞİMİZ
 * andır; sonrasında göstermek, omuz üstünden okunmasına ve ekran görüntüsüne
 * girmesine kapı açar.
 */
export default function CeviriAyarlari() {
  const push = useToast((state) => state.push);
  const durum = useAsync((signal) => ceviriApi.ayarlar(signal), []);
  const [anahtar, setAnahtar] = useState('');
  const [kaydediliyor, setKaydediliyor] = useState(false);

  const veri = durum.data;

  const kaydet = async (event: FormEvent) => {
    event.preventDefault();
    if (!veri) return;

    setKaydediliyor(true);
    try {
      await ceviriApi.ayarlariKaydet({
        saglayici: veri.saglayici,
        model: veri.model,
        hedef_diller: veri.hedef_diller,
        acik: veri.acik,
        ...(anahtar.trim() ? { anahtar: anahtar.trim() } : {}),
      });
      setAnahtar('');
      durum.reload();
      push('Çeviri ayarları kaydedildi.');
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setKaydediliyor(false);
    }
  };

  const guncelle = (yama: Partial<NonNullable<typeof veri>>) => {
    if (!veri) return;
    // useAsync verisi salt okunur akar; yerel düzenleme için kopyalanır.
    Object.assign(veri, yama);
    setAnahtar((mevcut) => mevcut); // yeniden render
  };

  return (
    <section className="card mb-4 p-4">
      <h2 className="mb-1 flex items-center gap-2 text-sm font-semibold text-ink-2">
        <Languages className="h-4 w-4" aria-hidden />
        Çeviri (K56 Katman 2)
      </h2>
      <p className="mb-3 text-xs text-ink-3">
        Ürünün tamamı tek istekte çevrilir; TR ve EN <strong>birlikte</strong> üretilir.
        Çeviri her zaman bir <strong>öneridir</strong> — hiçbir alan kendiliğinden yazılmaz.
      </p>

      {durum.loading ? (
        <Skeleton rows={2} />
      ) : durum.error ? (
        <ErrorNote message={durum.error} onRetry={durum.reload} />
      ) : veri ? (
        <form onSubmit={(event) => void kaydet(event)} className="space-y-3">
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Sağlayıcı">
              <select
                className="field-input"
                value={veri.saglayici}
                onChange={(event) => guncelle({ saglayici: event.target.value })}
              >
                {veri.saglayicilar.map((s) => (
                  <option key={s} value={s}>
                    {s}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Model" hint="Boş bırakılırsa sağlayıcının varsayılanı kullanılır">
              <input
                className="field-input"
                value={veri.model}
                onChange={(event) => guncelle({ model: event.target.value })}
              />
            </Field>
          </div>

          <Field
            label="API anahtarı"
            hint={
              veri.anahtar_tanimli
                ? `Tanımlı (${veri.anahtar_onizleme ?? '••••'}). Değiştirmek için yeni anahtarı yazın; boş bırakırsanız mevcut anahtar korunur.`
                : 'Henüz tanımlı değil. Anahtar şifreli saklanır ve bir daha GÖSTERİLMEZ.'
            }
          >
            <div className="flex items-center gap-2">
              <KeyRound className="h-4 w-4 text-ink-3" aria-hidden />
              <input
                className="field-input"
                type="password"
                autoComplete="off"
                placeholder={veri.anahtar_tanimli ? '•••••••• (değiştirmek için yazın)' : 'sk-…'}
                value={anahtar}
                onChange={(event) => setAnahtar(event.target.value)}
              />
            </div>
          </Field>

          <Field label="Hedef diller" hint="Virgülle ayırın. Yeni dil eklemek bir ayar değişikliğidir.">
            <input
              className="field-input"
              value={veri.hedef_diller.join(', ')}
              onChange={(event) =>
                guncelle({
                  hedef_diller: event.target.value
                    .split(',')
                    .map((d) => d.trim().toLowerCase())
                    .filter(Boolean),
                })
              }
            />
          </Field>

          <label className="flex items-center gap-2 text-sm text-ink-2">
            <input
              type="checkbox"
              checked={veri.acik}
              onChange={(event) => guncelle({ acik: event.target.checked })}
            />
            LLM çevirisi açık (kapalıyken sözlük + makine katmanı çalışmaya devam eder)
          </label>

          <div className="flex flex-wrap items-center gap-2 pt-1">
            <button type="submit" className="btn-primary" disabled={kaydediliyor}>
              {kaydediliyor ? 'Kaydediliyor…' : 'Kaydet'}
            </button>

            <EylemDugmesi
              className="btn-ghost"
              mesgulEtiketi="Kuyruğa alınıyor"
              onEylem={async () => {
                const sonuc = await ceviriApi.topluCevir();
                push(sonuc.mesaj, sonuc.kuyruga_alinan > 0 ? 'success' : 'error');
              }}
              onHata={(hata) => push(messageOf(hata), 'error')}
            >
              <span className="inline-flex items-center gap-2">
                <Sparkles className="h-4 w-4" aria-hidden />
                Çevrilmemiş ürünleri çevir
              </span>
            </EylemDugmesi>
          </div>

          <p className="text-xs text-ink-3">
            Toplu çeviri kuyruğa alınır ve arka planda koşar; ilerlemeyi aşağıdaki
            <strong> Kuyruk durumu</strong> bölümünden izleyebilirsiniz. Belge üretimi
            çeviri beklemez — belgeler yalnız hazır (önbellekteki) metni kullanır.
          </p>
        </form>
      ) : null}
    </section>
  );
}
