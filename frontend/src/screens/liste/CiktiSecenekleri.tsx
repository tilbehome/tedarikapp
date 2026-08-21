import { useEffect, useRef, useState } from 'react';
import { Download, FileSpreadsheet, Settings2 } from 'lucide-react';
import { exports as exportsApi, type ExportOptions } from '../../api/endpoints';
import IslemDurumu from '../../components/IslemDurumu';
import { useUzunIslem } from '../../lib/useUzunIslem';
import { productStatusLabels } from '../../locales/tr';
import type { ProductStatus } from '../../api/types';

/**
 * Çıktı üretimi + seçenekleri (İE#13 F2/F5/F6).
 *
 * • KOPYA TÜRÜ (F5): "Firma kopyası" varsayılandır. "İç kopya" hedef satış ve kâr
 *   sütunlarını ekler — bu sütunlar firma kopyasında DOSYAYA HİÇ GİRMEZ.
 * • DURUM FİLTRESİ (F2): seçilmezse tüm ürünler basılır.
 * • QR (F6): listenin AKTİF paylaşım adresi bu oturumda üretildiyse (SharePanel onu
 *   sessionStorage'a koyar) belgeye QR olarak gömülür. Adres sunucuda SAKLANMAZ (K51),
 *   bu yüzden link elde yoksa QR basılmaz.
 */
const DURUMLAR: ProductStatus[] = ['to_order', 'ordered', 'in_transit', 'received', 'cancelled'];

export function paylasimAdresiAnahtari(listId: number): string {
  return `tdk-share-url-${listId}`;
}

export default function CiktiSecenekleri({ listId, onDone }: { listId: number; onDone: () => void }) {
  const [acik, setAcik] = useState(false);
  const [kopya, setKopya] = useState<'firma' | 'ic'>('firma');
  const [durumlar, setDurumlar] = useState<ProductStatus[]>([]);
  const [qrEkle, setQrEkle] = useState(true);
  // İE#14 C2: belge üretimi büyük listelerde uzun sürer; çift tıklama iki kayıt açardı.
  const uretim = useUzunIslem();
  const busy = uretim.calisiyor;

  const paylasimAdresi = sessionStorage.getItem(paylasimAdresiAnahtari(listId));
  const otomatikCalisti = useRef(false);

  const uret = (format: 'xlsx' | 'pdf') =>
    uretim.baslat(async () => {
      const options: ExportOptions = { copy: kopya };
      if (durumlar.length > 0) options.statuses = durumlar;
      if (qrEkle && paylasimAdresi) options.share_url = paylasimAdresi;

      await exportsApi.create(listId, format, options);
      onDone();

      return `${format === 'xlsx' ? 'Excel' : 'PDF'} belgesi üretildi ve indirildi.`;
    });

  /**
   * Paylaşım sayfasındaki Excel/PDF düğmeleri buraya yönlendirir
   * (`?cikti=xlsx`): uçlar oturum + CSRF ister, o yüzden üretim PANELDE olur.
   */
  useEffect(() => {
    if (otomatikCalisti.current) return;
    const istenen = new URLSearchParams(window.location.search).get('cikti');
    if (istenen !== 'xlsx' && istenen !== 'pdf') return;
    otomatikCalisti.current = true;
    window.history.replaceState({}, '', window.location.pathname);
    // Dış sistemden (URL parametresi) gelen komut yürütülüyor; tek atımlık koşum
    // `otomatikCalisti` bayrağıyla korunur. İE#14 C2 sonrası state güncellemesi
    // `useUzunIslem` içinde olduğundan efekt artık doğrudan setState çağırmıyor.
    void uret(istenen);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const durumDegistir = (status: ProductStatus) =>
    setDurumlar((current) =>
      current.includes(status) ? current.filter((x) => x !== status) : [...current, status],
    );

  return (
    <>
      <button type="button" className="btn-ghost" disabled={busy} onClick={() => void uret('xlsx')}>
        <FileSpreadsheet className="h-4 w-4" aria-hidden />
        {busy ? 'Üretiliyor…' : 'Excel'}
      </button>
      <button type="button" className="btn-ghost" disabled={busy} onClick={() => void uret('pdf')}>
        <Download className="h-4 w-4" aria-hidden />
        PDF
      </button>
      <button
        type="button"
        className="btn-ghost"
        aria-expanded={acik}
        onClick={() => setAcik((value) => !value)}
        title="Çıktı seçenekleri"
      >
        <Settings2 className="h-4 w-4" aria-hidden />
        {kopya === 'ic' ? 'İç kopya' : 'Seçenekler'}
      </button>

      {acik ? (
        <div className="card mt-2 w-full space-y-3 p-3 text-sm">
          <div className="flex flex-wrap items-center gap-4">
            <span className="text-ink-2">Kopya:</span>
            <label className="flex items-center gap-2">
              <input type="radio" name="kopya" checked={kopya === 'firma'} onChange={() => setKopya('firma')} />
              Firma kopyası
            </label>
            <label className="flex items-center gap-2">
              <input type="radio" name="kopya" checked={kopya === 'ic'} onChange={() => setKopya('ic')} />
              İç kopya <span className="text-xs text-warn">(hedef satış + kâr sütunları)</span>
            </label>
          </div>

          <div className="flex flex-wrap items-center gap-3 border-t border-line-soft pt-3">
            <span className="text-ink-2">Durum filtresi:</span>
            {DURUMLAR.map((status) => (
              <label key={status} className="flex items-center gap-1.5 text-xs">
                <input type="checkbox" checked={durumlar.includes(status)} onChange={() => durumDegistir(status)} />
                {productStatusLabels[status]}
              </label>
            ))}
            {durumlar.length === 0 ? <span className="text-xs text-ink-3">seçilmezse tümü basılır</span> : null}
          </div>

          <div className="border-t border-line-soft pt-3">
            {paylasimAdresi ? (
              <label className="flex items-center gap-2 text-xs text-ink-2">
                <input type="checkbox" checked={qrEkle} onChange={(event) => setQrEkle(event.target.checked)} />
                Paylaşım linkini QR olarak belgeye ekle
              </label>
            ) : (
              <p className="text-xs text-ink-3">
                QR için paylaşım linki gerekir. Link güvenlik gereği sunucuda saklanmaz (K51) — "Paylaş" ile
                bu oturumda üretirseniz belgeye QR eklenebilir.
              </p>
            )}
          </div>

          {kopya === 'ic' ? (
            <p className="rounded-lg bg-warn-soft px-3 py-2 text-xs text-warn">
              İç kopya kâr bilgisi taşır — firmaya GÖNDERMEYİN. Dosya adında ve alt bilgisinde "İÇ KOPYA" yazar.
            </p>
          ) : null}
        </div>
      ) : null}

      {/* İE#14 C2: üretim uzun sürerse şerit, bitince sonuç kartı — araç çubuğunun
          altında tam genişlikte durur ki düğmelerin yerini oynatmasın. */}
      <div className="w-full">
        <IslemDurumu islem={uretim} fiil="Belge üretiliyor" />
      </div>
    </>
  );
}
