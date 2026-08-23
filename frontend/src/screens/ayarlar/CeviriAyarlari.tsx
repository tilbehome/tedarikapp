import { useState, type FormEvent } from 'react';
import { AlertTriangle, CheckCircle2, KeyRound, Languages, PlugZap, Sparkles } from 'lucide-react';
import { ceviri as ceviriApi } from '../../api/endpoints';
import { useAsync, messageOf } from '../../lib/useAsync';
import { ErrorNote, Field, Skeleton } from '../../components/ui';
import { useToast } from '../../components/Toast';
import EylemDugmesi from '../../components/EylemDugmesi';
import { epostaGibiMi, otomatikDoldurmaKapali, useAutofillKalkani } from '../../lib/autofill';

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
  // D1: test sonucu EKRANDA kalır. Toast baloncuğu kaçırılabilir; bir hata
  // mesajının (ör. "model_not_found") okunup model alanına yansıtılması gerekir.
  const [test, setTest] = useState<{ basarili: boolean; mesaj: string } | null>(null);
  // D3: tarayıcı otomatik doldurmasına karşı kalkan (gerekçe: lib/autofill.ts).
  const modelKalkani = useAutofillKalkani();
  const dillerKalkani = useAutofillKalkani();

  const veri = durum.data;
  const modelEpostaGibi = veri ? epostaGibiMi(veri.model_ham) : false;

  const kaydet = async (event: FormEvent) => {
    event.preventDefault();
    if (!veri) return;

    setKaydediliyor(true);
    try {
      await ceviriApi.ayarlariKaydet({
        saglayici: veri.saglayici,
        model: veri.model_ham,
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
            <Field
              label="Model"
              hint={`Boş bırakılırsa sağlayıcının varsayılanı kullanılır: ${veri.varsayilan_model}`}
            >
              {/* D1: alan BOŞKEN etkin varsayılan gri yer tutucu olarak görünür.
                  Değeri kutuya yazmak yanlış olurdu: kullanıcı onu kendi seçimi
                  sanır ve sağlayıcı değiştirdiğinde varsayılan artık izlemez. */}
              <input
                {...otomatikDoldurmaKapali('llm-model')}
                {...modelKalkani}
                className="field-input"
                value={veri.model_ham}
                placeholder={veri.varsayilan_model}
                onChange={(event) => guncelle({ model_ham: event.target.value })}
                aria-invalid={modelEpostaGibi}
              />
              {modelEpostaGibi ? (
                <p className="mt-1 flex items-start gap-1.5 text-xs text-warn">
                  <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden />
                  Bu bir e-posta adresi gibi görünüyor. Model adları "@" içermez —
                  tarayıcı bu kutuyu otomatik doldurmuş olabilir. Boş bırakırsanız
                  varsayılan model ({veri.varsayilan_model}) kullanılır.
                </p>
              ) : null}
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
                {...otomatikDoldurmaKapali('llm-anahtar')}
                className="field-input"
                type="password"
                autoComplete="new-password"
                placeholder={veri.anahtar_tanimli ? '•••••••• (değiştirmek için yazın)' : 'sk-…'}
                value={anahtar}
                onChange={(event) => setAnahtar(event.target.value)}
              />
            </div>
          </Field>

          <Field label="Hedef diller" hint="Virgülle ayırın. Yeni dil eklemek bir ayar değişikliğidir.">
            <input
              {...otomatikDoldurmaKapali('hedef-diller')}
              {...dillerKalkani}
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
              mesgulEtiketi="Test ediliyor"
              onEylem={async () => {
                setTest(null);
                const sonuc = await ceviriApi.baglantiTesti();
                setTest({
                  basarili: sonuc.basarili,
                  mesaj: sonuc.basarili
                    ? `${sonuc.saglayici} · ${sonuc.model} yanıt verdi (${sonuc.sure_ms} ms).`
                    : `${sonuc.saglayici} · ${sonuc.model} — ${sonuc.hata ?? 'bilinmeyen hata'}`,
                });
              }}
              onHata={(hata) => setTest({ basarili: false, mesaj: messageOf(hata) })}
            >
              <span className="inline-flex items-center gap-2">
                <PlugZap className="h-4 w-4" aria-hidden />
                Bağlantıyı test et
              </span>
            </EylemDugmesi>

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

          {test ? (
            <p
              className={`flex items-start gap-2 rounded-lg p-2 text-xs ${
                test.basarili ? 'bg-ok-bg text-ok' : 'bg-err-bg text-err'
              }`}
              role="status"
            >
              {test.basarili ? (
                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />
              ) : (
                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />
              )}
              {/* Sağlayıcının hata metni AYNEN gösterilir: "model_not_found"
                  kullanıcının model adını düzeltmesi için gereken tek ipucudur. */}
              {test.mesaj}
            </p>
          ) : null}

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
