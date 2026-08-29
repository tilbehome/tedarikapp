import { useState } from 'react';
import { FileText, RefreshCw } from 'lucide-react';
import { gunluk as gunlukApi } from '../../../api/endpoints';
import { useAsync } from '../../../lib/useAsync';
import { dateTime } from '../../../lib/format';
import { ErrorNote, Skeleton } from '../../../components/ui';

/**
 * AYARLAR > 16 UYGULAMA GÜNLÜĞÜ (V3-B F2).
 *
 * Canlıda loglar veritabanındadır (K33). Bu kart olmadan onlara bakmanın tek
 * yolu cPanel → phpMyAdmin → SQL yazmaktı. Ürün Sahibi bir geliştirici değil;
 * "bir şey ters gitti" dediğinde sunucuya girmek zorunda kalmamalı.
 *
 * VARSAYILAN SÜZGEÇ `warning`: `info` satırları normal işleyişin gürültüsüdür
 * ve arayan kişi bir SORUN arıyordur. Seviye "bu ve üstü" çalışır.
 */
export default function Gunluk() {
  const [seviye, setSeviye] = useState('warning');
  const [ara, setAra] = useState('');
  const [sorgu, setSorgu] = useState({ seviye: 'warning', ara: '' });
  const durum = useAsync(() => gunlukApi.read({ ...sorgu, limit: 100 }), [sorgu]);

  const uygula = (): void => setSorgu({ seviye, ara: ara.trim() });

  return (
    <section className="card p-4" data-testid="gunluk">
      <h2 className="mb-1 flex items-center gap-2 text-sm font-semibold text-ink-2">
        <FileText className="h-4 w-4 text-navy" aria-hidden />
        Uygulama günlüğü
      </h2>
      <p className="mb-3 text-xs text-ink-3">
        Arka planda oluşan hata ve uyarılar. Bir şey beklediğiniz gibi çalışmadıysa buraya bakın;
        destek isterken buradaki satırı paylaşmak en hızlı yoldur.
      </p>

      <div className="mb-3 flex flex-wrap items-end gap-2">
        <label className="text-sm text-ink-2">
          <span className="mb-1 block text-xs text-ink-3">En düşük seviye</span>
          <select className="field-input w-auto" value={seviye} onChange={(o) => setSeviye(o.target.value)}>
            <option value="warning">Uyarı ve üstü</option>
            <option value="error">Hata ve üstü</option>
            <option value="critical">Yalnız kritik</option>
            <option value="info">Bilgi ve üstü (her şey)</option>
          </select>
        </label>
        <label className="min-w-40 flex-1 text-sm text-ink-2">
          <span className="mb-1 block text-xs text-ink-3">Metin ara</span>
          <input
            className="field-input"
            value={ara}
            placeholder="örn. çeviri"
            onChange={(o) => setAra(o.target.value)}
            onKeyDown={(o) => o.key === 'Enter' && uygula()}
          />
        </label>
        <button type="button" className="btn-ghost" onClick={uygula}>
          <RefreshCw className="h-4 w-4" aria-hidden />
          Uygula
        </button>
      </div>

      {durum.loading ? (
        <Skeleton rows={3} />
      ) : durum.error ? (
        <ErrorNote message={durum.error} onRetry={durum.reload} />
      ) : durum.data && !durum.data.kaynak_var ? (
        <p className="text-sm text-ink-3" data-testid="gunluk-kaynak-yok">
          {durum.data.not}
        </p>
      ) : (durum.data?.kayitlar ?? []).length === 0 ? (
        <p className="text-sm text-ink-3">Bu süzgeçle kayıt yok.</p>
      ) : (
        <ul className="divide-y divide-line-soft text-sm">
          {(durum.data?.kayitlar ?? []).map((kayit) => (
            <li key={kayit.id} className="py-2">
              <div className="flex flex-wrap items-baseline gap-2">
                <span
                  className={`rounded px-1.5 py-0.5 text-xs font-semibold ${
                    kayit.seviye.toLowerCase() === 'critical' || kayit.seviye.toLowerCase() === 'error'
                      ? 'bg-err-bg text-err'
                      : 'bg-warn-bg text-warn'
                  }`}
                >
                  {kayit.seviye}
                </span>
                <span className="text-xs text-ink-3">{dateTime(kayit.zaman)}</span>
              </div>
              <p className="mt-0.5 text-ink">{kayit.mesaj}</p>
              {kayit.baglam !== null && kayit.baglam !== '[]' ? (
                <details className="mt-1">
                  <summary className="cursor-pointer text-xs text-ink-3">ayrıntı</summary>
                  <pre className="mt-1 overflow-x-auto rounded bg-g50 p-2 text-xs text-ink-2">{kayit.baglam}</pre>
                </details>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
