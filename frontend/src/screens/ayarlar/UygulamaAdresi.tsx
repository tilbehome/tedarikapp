import { useState } from 'react';
import { AlertTriangle } from 'lucide-react';
import { settings as settingsApi } from '../../api/endpoints';
import { messageOf } from '../../lib/useAsync';
import { useToast } from '../../components/Toast';
import { Field } from '../../components/ui';
import { ApiError } from '../../api/client';
import { otomatikDoldurmaKapali, useAutofillKalkani } from '../../lib/autofill';

/**
 * Ayarlar > Sistem — UYGULAMA ADRESİ (rc8/K4 · dış denetim F-08).
 *
 * Paylaşım bağlantısı, QR kodu ve kanal metnindeki adres bu değerden üretilir.
 * Kurulum sihirbazı yazar; sunucu taşınırsa ya da alan adı değişirse
 * BURADAN düzeltilir — daha önce panelde karşılığı yoktu, oysa kod ve belge
 * "panelden değiştirilir" diyordu.
 *
 * Değer yoksa ya da kanonik değilse uygulama link üretmeyi tümden reddeder
 * (istemcinin `Host` başlığına düşmek, paylaşım adresini saldırgana yazdırmak
 * demekti). Bu yüzden eksiklik BİLGİ satırı değil, KIRMIZI ŞERİTTİR.
 *
 * Kaydetme parola tekrarı ister: açık bir oturumu ele geçiren biri tek alanı
 * değiştirerek bundan sonraki tüm linkleri kendi sunucusuna yönlendirebilirdi.
 */
export default function UygulamaAdresi({
  mevcut,
  kanonik,
  onSaved,
}: {
  mevcut: string | null;
  kanonik: boolean;
  onSaved: () => void;
}) {
  const push = useToast((state) => state.push);
  const [adres, setAdres] = useState(mevcut ?? '');
  const [parola, setParola] = useState('');
  const [hatalar, setHatalar] = useState<Record<string, string | undefined>>({});
  const [busy, setBusy] = useState(false);
  const kalkan = useAutofillKalkani();

  const kaydet = async (olay: React.FormEvent) => {
    olay.preventDefault();
    setBusy(true);
    setHatalar({});
    try {
      const sonuc = await settingsApi.updateAppUrl(adres, parola);
      setAdres(sonuc.app_url);
      setParola('');
      push('Uygulama adresi kaydedildi. Bundan sonraki paylaşım bağlantıları bu adresle üretilir.');
      onSaved();
    } catch (caught) {
      if (caught instanceof ApiError) setHatalar(caught.fields);
      push(messageOf(caught), 'error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="card mb-4 p-4" data-testid="uygulama-adresi">
      <h2 className="mb-1 text-sm font-semibold text-ink-2">Uygulama adresi</h2>
      <p className="mb-3 text-xs text-ink-3">
        Paylaşım bağlantıları, QR kodu ve kanal metni bu adresle üretilir. Panelin tarayıcıda göründüğü
        tam adresi yazın — yol, sorgu ya da kullanıcı adı içermemeli.
      </p>

      {kanonik ? null : (
        <p
          className="mb-3 flex items-start gap-2 rounded border border-err/30 bg-err-soft p-2 text-xs text-err"
          role="alert"
          data-testid="uygulama-adresi-uyari"
        >
          <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden />
          <span>
            Uygulama adresi eksik ya da geçersiz. Bu hâliyle paylaşım bağlantısı ve QR kodu ÜRETİLEMEZ —
            listelerinizi paylaşmadan önce adresi girin.
          </span>
        </p>
      )}

      <form onSubmit={(olay) => void kaydet(olay)} className="grid gap-3 sm:grid-cols-2">
        <Field label="Adres" hint="Örn. https://tedarik.firma.com" error={hatalar['app_url']}>
          <input
            {...otomatikDoldurmaKapali('uygulama-adresi')}
            {...kalkan}
            className="field-input"
            inputMode="url"
            value={adres}
            onChange={(olay) => setAdres(olay.target.value)}
            placeholder="https://tedarik.firma.com"
            data-testid="uygulama-adresi-girdi"
          />
        </Field>

        <Field
          label="Parolanız"
          hint="Adres değişikliği parola doğrulaması ister."
          error={hatalar['password']}
        >
          <input
            className="field-input"
            type="password"
            autoComplete="current-password"
            value={parola}
            onChange={(olay) => setParola(olay.target.value)}
            data-testid="uygulama-adresi-parola"
          />
        </Field>

        <div className="sm:col-span-2">
          <button type="submit" className="btn-primary" disabled={busy} data-testid="uygulama-adresi-kaydet">
            {busy ? 'Kaydediliyor…' : 'Adresi kaydet'}
          </button>
        </div>
      </form>
    </section>
  );
}
