import { useMemo, useState, type FormEvent } from 'react';
import { ShieldCheck, Smartphone } from 'lucide-react';
import { useSession } from '../store/session';
import { ApiError } from '../api/client';
import { messageOf } from '../lib/useAsync';
import { girisVitriniOku } from '../lib/girisVitrini';
import { Field } from '../components/ui';
import MarkaPaneli from './login/MarkaPaneli';
import { AnahtarDugmesi, GirisAlani, PaneleGirDugmesi, SifreAlani } from './login/GirisAlani';

/**
 * E1 — Giriş (İE#13 EK-B ile premium yenileme).
 *
 * AKIŞ AYNEN KORUNDU: e-posta+şifre → (2FA açıksa) TOTP kodu → (isteğe bağlı)
 * kurtarma kodu. Kilit/backoff mesajları sunucudan geldiği gibi gösterilir.
 * EK-B yalnız GÖRÜNÜMÜ değiştirir — kimlik doğrulama, oturum ve 2FA mantığına
 * tek satır dokunulmamıştır.
 *
 * Vitrin rakamları sunucudan meta etiketiyle gelir (girişsiz uç açılmaz);
 * "Şifremi unuttum" bağlantısı YOKTUR: e-posta ile kurtarma kapalıdır (K8),
 * telefon kaybında yol kurtarma kodudur ve o ikinci adımda sunulur.
 */
export default function LoginScreen() {
  const { stage, login, submitTotp, submitRecovery } = useSession();
  const vitrin = useMemo(() => girisVitriniOku(), []);

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [remember, setRemember] = useState(false);
  const [code, setCode] = useState('');
  const [useRecovery, setUseRecovery] = useState(false);

  const [error, setError] = useState<string | null>(null);
  const [fields, setFields] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);

  const run = async (action: () => Promise<unknown>) => {
    setBusy(true);
    setError(null);
    setFields({});
    try {
      await action();
    } catch (caught) {
      setError(messageOf(caught));
      if (caught instanceof ApiError) setFields(caught.fields);
    } finally {
      setBusy(false);
    }
  };

  const onPassword = (event: FormEvent) => {
    event.preventDefault();
    void run(() => login(email.trim(), password, remember));
  };

  const onSecondFactor = (event: FormEvent) => {
    event.preventDefault();
    void run(async () => {
      if (useRecovery) {
        const remaining = await submitRecovery(code.trim());
        window.alert(`Kurtarma kodu kullanıldı. Kalan kod sayınız: ${remaining}`);
      } else {
        await submitTotp(code.trim());
      }
      setCode('');
    });
  };

  const epostaGecerli = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());

  return (
    <div className="flex min-h-dvh items-center justify-center bg-g100 px-4 py-6 sm:py-10">
      <div className="grid w-full max-w-4xl overflow-hidden rounded-3xl bg-surface shadow-2xl shadow-slate-900/10 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
        <MarkaPaneli vitrin={vitrin} />

        <section className="px-6 py-8 sm:px-10 sm:py-10">
          {stage === 'awaiting-totp' ? (
            <form onSubmit={onSecondFactor} className="space-y-5">
              <header>
                <h1 className="text-2xl font-bold tracking-tight text-ink">
                  {useRecovery ? 'Kurtarma kodu' : 'İki adımlı doğrulama'}
                </h1>
                <p className="mt-1 text-sm text-ink-3">
                  {useRecovery
                    ? 'Kaydettiğiniz kodlardan birini girin.'
                    : 'Doğrulama uygulamanızdaki 6 haneli kodu girin.'}
                </p>
              </header>

              <Field
                label={useRecovery ? 'Kurtarma kodunuz' : 'Uygulamadaki 6 haneli kod'}
                error={fields['code']}
                hint={useRecovery ? 'Her kod yalnızca bir kez kullanılabilir.' : undefined}
              >
                <input
                  className="field-input tracking-widest"
                  value={code}
                  onChange={(event) => setCode(event.target.value)}
                  autoComplete="one-time-code"
                  inputMode={useRecovery ? 'text' : 'numeric'}
                  autoFocus
                  required
                />
              </Field>

              {error && !fields['code'] && <p className="text-sm font-medium text-err">{error}</p>}

              <PaneleGirDugmesi busy={busy} label={busy ? 'Doğrulanıyor…' : 'Doğrula'} />

              <button
                type="button"
                className="w-full text-sm font-medium text-navy underline-offset-2 hover:underline"
                onClick={() => {
                  setUseRecovery((value) => !value);
                  setCode('');
                  setError(null);
                  setFields({});
                }}
              >
                {useRecovery ? 'Doğrulama uygulamasını kullan' : 'Telefonuma erişemiyorum, kurtarma kodu gireceğim'}
              </button>
            </form>
          ) : (
            <form onSubmit={onPassword} className="space-y-5">
              <header>
                <h1 className="text-2xl font-bold tracking-tight text-ink">Giriş yap</h1>
                <p className="mt-1 text-sm text-ink-3">Yönetim paneline devam et.</p>
              </header>

              <GirisAlani
                label="E-posta"
                type="email"
                value={email}
                onChange={setEmail}
                autoComplete="username"
                autoFocus
                gecerli={epostaGecerli}
                hata={fields['email']}
              />

              <SifreAlani value={password} onChange={setPassword} hata={fields['password']} />

              <AnahtarDugmesi checked={remember} onChange={setRemember}>
                Bu cihazda beni hatırla
              </AnahtarDugmesi>

              {error && <p className="text-sm font-medium text-err">{error}</p>}

              <PaneleGirDugmesi busy={busy} label={busy ? 'Kontrol ediliyor…' : 'Panele gir'} />

              {vitrin.twoFactor ? (
                <>
                  <p className="flex items-center gap-3 text-[10px] font-bold uppercase tracking-[0.18em] text-g300">
                    <span className="h-px flex-1 bg-g200" aria-hidden />
                    Güvenlik
                    <span className="h-px flex-1 bg-g200" aria-hidden />
                  </p>
                  <div className="flex items-start gap-3 rounded-xl border border-line bg-g50 px-3.5 py-3">
                    <Smartphone className="mt-0.5 h-4 w-4 shrink-0 text-navy" aria-hidden />
                    <p className="flex-1 text-xs leading-relaxed text-ink-2">
                      İki adımlı doğrulama açık — girişten sonra kod sorulacak.
                    </p>
                    <span className="badge bg-ok-soft text-ok ring-ok/20">Aktif</span>
                  </div>
                </>
              ) : null}

              <p className="flex items-center justify-center gap-1.5 pt-1 text-[11px] text-ink-3 lg:hidden">
                <ShieldCheck className="h-3.5 w-3.5" aria-hidden />
                Uçtan uca şifreli{vitrin.version ? ` · v${vitrin.version}` : ''}
              </p>
            </form>
          )}
        </section>
      </div>
    </div>
  );
}
