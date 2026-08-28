import { useEffect, useState, type FormEvent } from 'react';
import { Download } from 'lucide-react';
import { settings as settingsApi } from '../../../api/endpoints';
import { useAsync, messageOf } from '../../../lib/useAsync';
import { rate } from '../../../lib/format';
import { ErrorNote, Field, Skeleton } from '../../../components/ui';
import { useToast } from '../../../components/Toast';
import EylemDugmesi from '../../../components/EylemDugmesi';
import KurTarihcesi from './KurTarihcesi';

/**
 * AYARLAR > 7 KUR & PARA BİRİMLERİ (V3-B C1).
 *
 * Kur bir TİCARİ KARARDIR (K4): TCMB önerisi forma DOLDURULUR, kaydetmek
 * kullanıcının işidir. "Getir ve kaydet" diye tek düğme YOKTUR.
 */
export default function Kurlar() {
  const push = useToast((state) => state.push);
  const settingsState = useAsync(() => settingsApi.read(), []);

  const [yuan, setYuan] = useState('');
  const [usd, setUsd] = useState('');
  const [fields, setFields] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  // B5: getirilen öneri KAYDEDİLMEZ, forma yazılır ve burada rozetlenir —
  // kullanıcı neyin nereden geldiğini görmeden "Kaydet" dememeli.
  const [oneri, setOneri] = useState<{ kaynak: string; tarih: string | null } | null>(null);
  /** Kur kaydedilince geçmiş tablosunu yeniden kurar. */
  const [tarihceAnahtari, setTarihceAnahtari] = useState(0);

  useEffect(() => {
    const data = settingsState.data;
    if (!data) return;
    // Yüklenen ayarlar form alanlarını tohumlar — react-hooks 7 bu deseni uyarıyor; formun sunucu
    // verisiyle ilklenmesi mevcut davranıştır ve F41 kapsamında ele alınacak.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setYuan(data.yuan_tl);
    setUsd(data.usd_tl);
  }, [settingsState.data]);

  /** TCMB'den güncel kuru FORMA doldurur. Kaydetme kullanıcının işidir. */
  const kuruGetir = async () => {
    setFields({});
    try {
      const sonuc = await settingsApi.suggestRates();
      setYuan(sonuc.yuan_tl);
      setUsd(sonuc.usd_tl);
      setOneri({ kaynak: sonuc.kaynak, tarih: sonuc.tarih });
      push(`${sonuc.kaynak} kuru forma dolduruldu. Kaydetmek için "Kurları güncelle" deyin.`);
    } catch (caught) {
      // Görünür hata (emir §B5): sessizce eski değerle devam edilmez.
      setOneri(null);
      push(messageOf(caught), 'error');
      throw caught;
    }
  };

  const save = async (event: FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setFields({});
    try {
      const result = await settingsApi.updateRates({
        yuan_tl: yuan.trim().replace(',', '.'),
        usd_tl: usd.trim().replace(',', '.'),
      });
      settingsState.reload();
      // Tarihçe kendi bileşeninde; kur kaydedilince tazelenmesi için anahtar artar.
      setTarihceAnahtari((n) => n + 1);
      setOneri(null);
      // 3b (K48 ek): aynı değerle basmak tarihçeye yazmaz — bildirim de bunu söyler.
      if (result.changes.length === 0) {
        push(`Kurlar zaten güncel (${rate(result.yuan_tl)} / ${rate(result.usd_tl)}).`);
      } else {
        const parts = result.changes.map(
          (change) => `${change.currency === 'CNY' ? 'Yuan' : 'Dolar'} ${rate(change.from)} → ${rate(change.to)}`,
        );
        push(
          `${parts.join(', ')} güncellendi. Kilitlenmemiş listeler yeni kuru izler; ` +
            'kilitli listeler etkilenmedi.',
        );
      }
    } catch (caught) {
      push(messageOf(caught), 'error');
      const fieldErrors = (caught as { fields?: Record<string, string> }).fields;
      if (fieldErrors) setFields(fieldErrors);
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <section className="card mb-4 p-4">
        <h2 className="mb-3 text-sm font-semibold text-ink-2">Kurlar</h2>
        {settingsState.loading ? (
          <Skeleton rows={1} />
        ) : settingsState.error ? (
          <ErrorNote message={settingsState.error} onRetry={settingsState.reload} />
        ) : (
          <form onSubmit={(event) => void save(event)} className="space-y-3">
            <div className="grid gap-3 sm:grid-cols-2">
              <Field label="Yuan → TL" hint="Örn. 4,1250" error={fields['yuan_tl']}>
                <input className="field-input" inputMode="decimal" value={yuan} onChange={(event) => setYuan(event.target.value)} />
              </Field>
              <Field label="Dolar → TL" hint="Örn. 41,8000" error={fields['usd_tl']}>
                <input className="field-input" inputMode="decimal" value={usd} onChange={(event) => setUsd(event.target.value)} />
              </Field>
            </div>
            {oneri ? (
              <p className="flex items-start gap-1.5 rounded-lg bg-info-soft p-2 text-xs text-info">
                <Download className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden />
                {oneri.kaynak} kuru forma dolduruldu{oneri.tarih ? ` (${oneri.tarih} bülteni)` : ''}. Henüz
                KAYDEDİLMEDİ — kontrol edip "Kurları güncelle" deyin.
              </p>
            ) : null}
            <p className="text-xs text-ink-3">
              Yeni kur <strong>kilitlenmemiş</strong> listelere işler ve onların ₺ karşılıkları anında güncellenir.
              "İletildi" durumuna geçmiş listelerin kuru kilitlidir ve değişmez.
            </p>
            <div className="flex flex-wrap items-center gap-2">
              <button type="submit" className="btn-primary" disabled={busy}>
                {busy ? 'Kaydediliyor…' : 'Kurları güncelle'}
              </button>
              <EylemDugmesi className="btn-ghost" mesgulEtiketi="Getiriliyor" onEylem={kuruGetir}>
                <span className="inline-flex items-center gap-2">
                  <Download className="h-4 w-4" aria-hidden />
                  Güncel kuru getir
                </span>
              </EylemDugmesi>
            </div>
          </form>
        )}
      </section>

      <KurTarihcesi key={tarihceAnahtari} />
    </>
  );
}
