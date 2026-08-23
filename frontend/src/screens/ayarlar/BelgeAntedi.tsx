import { useState } from 'react';
import { documentHeader as antetApi, type DocumentHeader } from '../../api/endpoints';
import { messageOf } from '../../lib/useAsync';
import { useToast } from '../../components/Toast';
import { Field } from '../../components/ui';
import { ApiError } from '../../api/client';
import { otomatikDoldurmaKapali, useAutofillKalkani } from '../../lib/autofill';

/**
 * Ayarlar > Belge Antedi (İE#13 F1).
 *
 * Bu alanlar Excel/PDF çıktılarının ve paylaşım sayfasının ÜST BANDINDA görünür.
 * BOŞ ALAN BASILMAZ: doldurulmayan parça antet satırından tamamen düşer, yerine
 * boşluk veya "—" konmaz.
 *
 * "Hazırlayan" yalnız belgelerin imza satırında kullanılır (paylaşım sayfasında yok).
 */
export default function BelgeAntedi({ mevcut, onSaved }: { mevcut: DocumentHeader; onSaved: () => void }) {
  const push = useToast((state) => state.push);
  const [form, setForm] = useState({
    company: mevcut.company ?? '',
    web: mevcut.web ?? '',
    email: mevcut.email ?? '',
    prepared_by: mevcut.prepared_by ?? '',
  });
  const [fields, setFields] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  // D3: serbest metin kutuları tarayıcı doldurmasına kapalı (lib/autofill.ts).
  const firmaKalkani = useAutofillKalkani();
  const webKalkani = useAutofillKalkani();

  const set = (key: keyof typeof form, value: string) => setForm((current) => ({ ...current, [key]: value }));

  const kaydet = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setFields({});
    try {
      await antetApi.update(form);
      push('Belge antedi kaydedildi — yeni çıktılar bu bilgilerle basılır.');
      onSaved();
    } catch (caught) {
      if (caught instanceof ApiError) setFields(caught.fields);
      push(messageOf(caught), 'error');
    } finally {
      setBusy(false);
    }
  };

  const onizleme = [form.company, form.web, form.email].map((deger) => deger.trim()).filter(Boolean).join(' · ');

  return (
    <section className="card mb-4 p-4">
      <h2 className="mb-1 text-sm font-semibold text-ink-2">Belge antedi</h2>
      <p className="mb-3 text-xs text-ink-3">
        Excel/PDF çıktılarının ve paylaşım sayfasının üst bandında görünür. Boş bıraktığınız alan basılmaz.
      </p>

      <form onSubmit={(event) => void kaydet(event)} className="grid gap-3 sm:grid-cols-2">
        <Field label="Firma adı" error={fields['company']}>
          <input
            {...otomatikDoldurmaKapali('antet-firma')}
            {...firmaKalkani}
            className="field-input"
            value={form.company}
            onChange={(e) => set('company', e.target.value)}
            placeholder="Tilbe Home"
          />
        </Field>
        <Field label="Web adresi" error={fields['web']}>
          <input
            {...otomatikDoldurmaKapali('antet-web')}
            {...webKalkani}
            className="field-input"
            value={form.web}
            onChange={(e) => set('web', e.target.value)}
            placeholder="tilbehome.com"
          />
        </Field>
        <Field label="E-posta" error={fields['email']}>
          <input
            className="field-input"
            type="email"
            name="antet-eposta"
            autoComplete="email"
            autoCapitalize="none"
            spellCheck={false}
            value={form.email}
            onChange={(e) => set('email', e.target.value)}
            placeholder="info@tilbehome.com"
          />
        </Field>
        <Field label="Hazırlayan" hint="Belgelerin imza satırında görünür" error={fields['prepared_by']}>
          <input
            className="field-input"
            value={form.prepared_by}
            onChange={(e) => set('prepared_by', e.target.value)}
            placeholder="Ad Soyad"
          />
        </Field>

        <div className="sm:col-span-2">
          <p className="rounded-lg bg-g50 px-3 py-2 text-xs text-ink-2">
            Önizleme: <span className="font-medium">{onizleme === '' ? '(antet boş — yalnız liste bilgisi basılır)' : onizleme}</span>
          </p>
        </div>

        <div className="sm:col-span-2">
          <button type="submit" className="btn-primary" disabled={busy}>
            {busy ? 'Kaydediliyor…' : 'Anteti kaydet'}
          </button>
        </div>
      </form>
    </section>
  );
}
