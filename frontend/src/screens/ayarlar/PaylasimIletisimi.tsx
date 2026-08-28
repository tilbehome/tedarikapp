import { useState } from 'react';
import { share as shareApi } from '../../api/endpoints';
import { messageOf } from '../../lib/useAsync';
import { useToast } from '../../components/Toast';
import { Field } from '../../components/ui';
import { ApiError } from '../../api/client';
import { otomatikDoldurmaKapali, useAutofillKalkani } from '../../lib/autofill';

/**
 * Ayarlar > Paylaşım iletişim numarası (İE#21 EK-4 · B7).
 *
 * Kilit ekranındaki "Yeni anahtar iste" düğmesi bu numaraya WhatsApp köprüsü
 * açar. Firma hazır bir mesajla anahtarı ister; ANAHTAR MESAJDA GİTMEZ ve
 * sistemde anahtar-talep ucu yoktur — anahtarı yine yalnız yetkili üretir.
 *
 * BOŞ BIRAKILABİLİR: numara yoksa kilit ekranında düğme BASILMAZ, yerine
 * "listeyi paylaşan kişiyle iletişime geçin" bilgi satırı kalır. Çalışmayan bir
 * düğme göstermektense hiç göstermemek doğrudur.
 */
export default function PaylasimIletisimi({ mevcut, onSaved }: { mevcut: string | null; onSaved: () => void }) {
  const push = useToast((state) => state.push);
  const [numara, setNumara] = useState(mevcut ?? '');
  const [hata, setHata] = useState<string | undefined>(undefined);
  const [busy, setBusy] = useState(false);
  const kalkan = useAutofillKalkani();

  const kaydet = async (olay: React.FormEvent) => {
    olay.preventDefault();
    setBusy(true);
    setHata(undefined);
    try {
      await shareApi.updateContact(numara);
      push(
        numara.trim() === ''
          ? 'Numara temizlendi — kilit ekranında düğme gösterilmeyecek.'
          : 'Paylaşım iletişim numarası kaydedildi.',
      );
      onSaved();
    } catch (caught) {
      if (caught instanceof ApiError) setHata(caught.fields['share_contact_phone']);
      push(messageOf(caught), 'error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="card mb-4 p-4">
      <h2 className="mb-1 text-sm font-semibold text-ink-2">Paylaşım iletişim numarası</h2>
      <p className="mb-3 text-xs text-ink-3">
        Kilit ekranındaki “Yeni anahtar iste” düğmesi bu numaraya WhatsApp mesajı açar. Boş bırakırsanız
        düğme gösterilmez. Anahtar mesajda gönderilmez; talebi siz karşılarsınız.
      </p>

      <form onSubmit={(olay) => void kaydet(olay)} className="grid gap-3 sm:grid-cols-2">
        <Field label="WhatsApp numarası" hint="Ülke koduyla — örn. +90 532 123 45 67" error={hata}>
          <input
            {...otomatikDoldurmaKapali('paylasim-iletisim')}
            {...kalkan}
            className="field-input"
            inputMode="tel"
            value={numara}
            onChange={(olay) => setNumara(olay.target.value)}
            placeholder="+90 532 123 45 67"
            data-testid="paylasim-iletisim-numarasi"
          />
        </Field>

        <div className="sm:col-span-2">
          <button type="submit" className="btn-primary" disabled={busy}>
            {busy ? 'Kaydediliyor…' : 'Numarayı kaydet'}
          </button>
        </div>
      </form>
    </section>
  );
}
