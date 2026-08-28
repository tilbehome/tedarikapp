import { useState, type FormEvent } from 'react';
import { AlertTriangle, CheckCircle2, KeyRound, Languages, Loader2, PlugZap, Sparkles } from 'lucide-react';
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

  /**
   * D12 — TOPLU ÇEVİRİ ARTIK EKRANDA İLERLER.
   *
   * Eski hâl tek istekte "kuyruğa alındı" deyip susuyordu; kuyruğu işleyen
   * olmadığı için hiçbir şey olmuyordu (saha: 1432 dk bekleyen işler). Şimdi
   * sunucu her istekte bir zaman bütçesi kadar çeviriyor ve kalanı söylüyor;
   * panel `devam_var` olduğu sürece bir sonraki isteği atıyor. Sayaç kullanıcıya
   * işin GERÇEKTEN yürüdüğünü gösterir.
   */
  const [ilerleme, setIlerleme] = useState<{ toplam: number; cevrilen: number; bitti: boolean } | null>(null);

  const topluCeviriyiSurdur = async (): Promise<void> => {
    let toplam = 0;
    let cevrilen = 0;

    // Üst sınır bir güvenlik ağıdır: sunucu her turda ilerlemiyorsa sonsuz
    // döngüye girmeyiz. Normalde tur sayısı liste boyutuyla orantılıdır.
    for (let tur = 0; tur < 200; tur++) {
      const sonuc = await ceviriApi.topluCevir();
      if (tur === 0) toplam = sonuc.toplam;
      cevrilen += sonuc.cevrilen;
      setIlerleme({ toplam: Math.max(toplam, cevrilen), cevrilen, bitti: !sonuc.devam_var });

      if (!sonuc.devam_var) {
        // Sunucu bir ENGEL bildirdiyse onu göster: "0 çevrildi" deyip susmak,
        // kullanıcıya düğmenin bozuk olduğunu düşündürür.
        push(
          sonuc.engel ??
            (sonuc.kalan === 0
              ? `Tamamlandı — ${cevrilen} ürün çevrildi.`
              : `${cevrilen} ürün çevrildi; ${sonuc.kalan} ürün arka planda tamamlanacak.`),
          sonuc.engel !== null ? 'error' : 'success',
        );

        return;
      }
    }

    push('Çeviri sürüyor; kalanlar arka planda tamamlanacak.', 'success');
  };
  const [anahtar, setAnahtar] = useState('');
  const [kaydediliyor, setKaydediliyor] = useState(false);
  /**
   * D4a (saha bulgusu, 25 Ağu 2026): "Hedef diller" alanı HER TUŞ VURUŞUNDA
   * listeyi normalize edip (`split/trim/filter` → `join(', ')`) kutuya geri
   * yazıyordu. Sonuç: "tr, en, zh" yazmaya çalışan kullanıcı virgülden sonraki
   * boşluğu kaybediyor, imleç sona atlıyor ve metin bozuluyordu.
   *
   * Çözüm: kutu SERBEST METİN taslağı tutar; listeye çevirme yalnız alan
   * terk edilince (blur) ya da kaydederken yapılır. Yazarken kimse araya
   * girmez — düzenleme bittiğinde biçim düzelir.
   */
  const [dillerTaslak, setDillerTaslak] = useState<string | null>(null);
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

    // Kullanıcı alanı terk etmeden Kaydet'e basmış olabilir: taslak ÖNCE işlenir,
    // yoksa son yazdığı dil kaydedilmeden gider.
    const hedefDiller = dilleriIsle();

    setKaydediliyor(true);
    try {
      await ceviriApi.ayarlariKaydet({
        saglayici: veri.saglayici,
        model: veri.model_ham,
        hedef_diller: hedefDiller,
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

  /** "tr, en , ZH" → ['tr','en','zh'] — tek kural, iki çağıran (blur + kaydet). */
  const dilleriAyristir = (metin: string): string[] =>
    metin
      .split(',')
      .map((d) => d.trim().toLowerCase())
      .filter(Boolean);

  /** Taslağı listeye işler ve taslağı bırakır (kutu yine sunucu verisini gösterir). */
  const dilleriIsle = (): string[] => {
    if (dillerTaslak === null) return veri?.hedef_diller ?? [];
    const diller = dilleriAyristir(dillerTaslak);
    guncelle({ hedef_diller: diller });
    setDillerTaslak(null);

    return diller;
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

          {!veri.anahtar_tanimli ? (
            <p
              className="flex items-start gap-2 rounded-lg border border-err/30 bg-err-soft p-2 text-xs text-err"
              role="alert"
              data-testid="llm-anahtar-uyarisi"
            >
              <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden />
              <span>
                <strong>LLM anahtarı tanımlı değil — üç dil garantisi yok.</strong> Sözlük ve
                makine katmanı ne bulursa dolduruyor, ama bu çeviriler GEÇİCİDİR: ilgili satırlar
                listelerde ve belgelerde “çeviri bekliyor” işaretli kalır. Kalıcı çeviri için
                aşağıya bir sağlayıcı anahtarı girin.
              </span>
            </p>
          ) : null}

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
              // D4a: yazarken TASLAK gösterilir; kayıtlı liste yalnız taslak yokken basılır.
              value={dillerTaslak ?? veri.hedef_diller.join(', ')}
              onChange={(event) => setDillerTaslak(event.target.value)}
              onBlur={() => dilleriIsle()}
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
              mesgulEtiketi="Çevriliyor"
              onEylem={topluCeviriyiSurdur}
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

          {ilerleme !== null ? (
            <p className="flex items-center gap-2 text-xs text-ink-2" role="status" data-testid="ceviri-ilerleme">
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
              {ilerleme.bitti
                ? `${ilerleme.cevrilen}/${ilerleme.toplam} çevrildi — tamamlandı.`
                : `${ilerleme.cevrilen}/${ilerleme.toplam} çevrildi…`}
            </p>
          ) : null}

          <p className="text-xs text-ink-3">
            Çeviri kurulum İSTEMEZ: düğmeye bastığınızda ürünler burada, bu ekranda
            çevrilir. Uzun listelerde iş parçalara bölünür ve yukarıdaki sayaç ilerler;
            sekmeyi kapatırsanız kalanlar kaybolmaz, panele bir dahaki girişinizde
            kendiliğinden tamamlanır. Belge üretimi çeviri beklemez — belgeler yalnız
            hazır (önbellekteki) metni kullanır.
          </p>
        </form>
      ) : null}
    </section>
  );
}
