import { useState, type FormEvent } from 'react';
import { KeyRound, ShieldCheck } from 'lucide-react';
import { useSession } from '../store/session';
import { ApiError } from '../api/client';
import { messageOf } from '../lib/useAsync';
import { Field } from '../components/ui';

/**
 * E1 — Giriş.
 *
 * Akış backend'inkiyle aynı: e-posta+şifre → TOTP kodu → (isteğe bağlı) kurtarma kodu.
 * Kilit/backoff (`LOCKED`, `RATE_LIMITED`) mesajları sunucudan geldiği gibi gösterilir.
 */
export default function LoginScreen() {
  const { stage, login, submitTotp, submitRecovery } = useSession();

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

  return (
    <div className="flex min-h-dvh items-center justify-center px-4 py-10">
      <div className="w-full max-w-sm">
        <div className="mb-6 text-center">
          <h1 className="text-2xl font-bold tracking-tight">tedarikapp</h1>
          <p className="mt-1 text-sm text-slate-500">Tilbe Home tedarik yönetimi</p>
        </div>

        <div className="card p-5">
          {stage === 'awaiting-totp' ? (
            <form onSubmit={onSecondFactor} className="space-y-4">
              <div className="flex items-center gap-2 text-sm font-semibold text-slate-700">
                <ShieldCheck className="h-5 w-5 text-brand-600" aria-hidden />
                {useRecovery ? 'Kurtarma kodu' : 'İki adımlı doğrulama'}
              </div>

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

              {error && !fields['code'] && <p className="text-sm font-medium text-rose-700">{error}</p>}

              <button type="submit" className="btn-primary w-full" disabled={busy}>
                {busy ? 'Doğrulanıyor…' : 'Doğrula'}
              </button>

              <button
                type="button"
                className="w-full text-sm font-medium text-brand-700 underline-offset-2 hover:underline"
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
            <form onSubmit={onPassword} className="space-y-4">
              <div className="flex items-center gap-2 text-sm font-semibold text-slate-700">
                <KeyRound className="h-5 w-5 text-brand-600" aria-hidden />
                Giriş yap
              </div>

              <Field label="E-posta" error={fields['email']}>
                <input
                  className="field-input"
                  type="email"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  autoComplete="username"
                  autoFocus
                  required
                />
              </Field>

              <Field label="Şifre" error={fields['password']}>
                <input
                  className="field-input"
                  type="password"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  autoComplete="current-password"
                  required
                />
              </Field>

              <label className="flex min-h-11 items-center gap-2 text-sm text-slate-600">
                <input
                  type="checkbox"
                  className="h-5 w-5 rounded border-slate-300"
                  checked={remember}
                  onChange={(event) => setRemember(event.target.checked)}
                />
                Bu cihazda beni hatırla
              </label>

              {error && <p className="text-sm font-medium text-rose-700">{error}</p>}

              <button type="submit" className="btn-primary w-full" disabled={busy}>
                {busy ? 'Kontrol ediliyor…' : 'Devam et'}
              </button>
            </form>
          )}
        </div>
      </div>
    </div>
  );
}
