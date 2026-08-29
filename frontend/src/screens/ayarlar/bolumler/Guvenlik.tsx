import { useState } from 'react';
import { ShieldCheck } from 'lucide-react';
import { settings as settingsApi } from '../../../api/endpoints';
import { useAsync, messageOf } from '../../../lib/useAsync';
import { ErrorNote } from '../../../components/ui';
import { useToast } from '../../../components/Toast';
import Satir from './Satir';

/**
 * AYARLAR > 14 GÜVENLİK & API (V3-B C1).
 *
 * İki adımlı doğrulama durumu ve eklenti token'ı. Token'ın TAM DEĞERİ yalnız
 * üretim yanıtında bir kez görünür (K34); burada maskeli önizleme durur.
 */
export default function Guvenlik() {
  const settingsState = useAsync(() => settingsApi.read(), []);

  return (
    <section className="card mb-4 p-4">
      <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold text-ink-2">
        <ShieldCheck className="h-4 w-4 text-navy" aria-hidden />
        Güvenlik
      </h2>
      {/* İE#14 C3: veri OKUNURKEN "—" gösterilmez — "—" yalnız gerçekten boş
          alanın işaretidir. Hata olursa kart kendi içinde tekrar denenir. */}
      {settingsState.error ? (
        <ErrorNote message={settingsState.error} onRetry={settingsState.reload} />
      ) : (
        <>
          <dl className="space-y-2 text-sm">
            <Satir
              label="İki adımlı doğrulama"
              value={
                settingsState.loading ? 'okunuyor…' : settingsState.data?.totp_enabled ? 'Etkin' : 'Kapalı'
              }
            />
            <Satir
              label="Eklenti API token'ı"
              value={
                settingsState.loading
                  ? 'okunuyor…'
                  : (settingsState.data?.extension_token_preview ?? 'Henüz üretilmedi')
              }
            />
          </dl>
          <ExtensionTokenActions
            preview={settingsState.data?.extension_token_preview ?? null}
            onChanged={settingsState.reload}
          />
        </>
      )}
    </section>
  );
}

/**
 * İE#11 — eklenti token'ı üret/iptal: tam token YALNIZ üretim yanıtında bir kez
 * görünür (K34; DB'de hash). Tek kullanıcı çok cihaz: aynı token her tarayıcıya
 * girilebilir; yenileme/iptal hepsini birden düşürür.
 */
function ExtensionTokenActions({ preview, onChanged }: { preview: string | null; onChanged: () => void }) {
  const push = useToast((state) => state.push);
  const [busy, setBusy] = useState(false);
  const [token, setToken] = useState<string | null>(null);

  const create = async () => {
    setBusy(true);
    try {
      const result = await settingsApi.extensionTokenCreate();
      setToken(result.token);
      onChanged();
      push('Token üretildi — yalnız şimdi görünür, eklentiye şimdi yapıştırın.');
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setBusy(false);
    }
  };

  const revoke = async () => {
    setBusy(true);
    try {
      await settingsApi.extensionTokenRevoke();
      setToken(null);
      onChanged();
      push('Token iptal edildi — tüm cihazlardaki eklentiler düştü.');
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="mt-3">
      {token ? (
        <>
          <p className="break-all rounded-lg bg-g50 p-2 font-mono text-xs">{token}</p>
          <p className="mt-1 text-xs text-warn">
            Bu token yalnız şimdi görünür (güvenlik gereği kaydedilmez) — eklentinin ayar ekranına yapıştırın.
          </p>
          <button type="button" className="btn-primary mt-2" onClick={() => void navigator.clipboard.writeText(token).then(() => push('Token kopyalandı.'))}>
            Kopyala
          </button>
        </>
      ) : (
        <div className="flex flex-wrap gap-2">
          <button type="button" className="btn-primary" disabled={busy} onClick={() => void create()}>
            {preview ? 'Token yenile' : "Eklenti token'ı üret"}
          </button>
          {preview ? (
            <button type="button" className="btn-ghost" disabled={busy} onClick={() => void revoke()}>
              Token iptal et
            </button>
          ) : null}
        </div>
      )}
      <p className="mt-2 text-xs text-ink-3">
        Chrome eklentisi bu token ile panele bağlanır. Yenileme/iptal, token'ı kullanan TÜM cihazları düşürür.
      </p>
    </div>
  );
}
