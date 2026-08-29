import { useState, type ChangeEvent } from 'react';
import { Download, Upload } from 'lucide-react';
import { sozluk as sozlukApi } from '../../../api/endpoints';
import { messageOf } from '../../../lib/useAsync';
import { useToast } from '../../../components/Toast';

/**
 * AYARLAR > 9 DİLLER & SÖZLÜK (V3-B C3 · PNL-50/51).
 *
 * Sözlük K56'nın 1. katmanıdır: belirlenimci, ağsız, kotasız. Terimleri
 * panelde tek tek düzenlemek küçük sözlükler için yeter; yüzlerce terimi olan
 * bir kullanıcı için yetmez — CSV'yi Excel'de düzenleyip geri yüklemek
 * gerçek iş akışıdır.
 *
 * ÇAKIŞMA KURALI EKRANDA YAZILIDIR. "Kullanıcı terimi kazanır" sessiz bir
 * kural olsaydı, yüklediği dosyanın neden kısmen uygulandığını kimse
 * anlamazdı; sonuç raporu da bunu sayıyla söyler.
 */
export default function Sozluk() {
  const push = useToast((state) => state.push);
  const [dil, setDil] = useState('zh');
  const [yukleniyor, setYukleniyor] = useState(false);

  const dosyaSecildi = async (olay: ChangeEvent<HTMLInputElement>): Promise<void> => {
    const dosya = olay.target.files?.[0];
    // Aynı dosyayı ikinci kez seçebilmek için alan her hâlükârda sıfırlanır.
    olay.target.value = '';
    if (!dosya) return;

    setYukleniyor(true);
    try {
      const sonuc = await sozlukApi.iceAktar(dil, await dosya.text());
      push(
        `${sonuc.eklenen} terim eklendi. ` +
          `${sonuc.atlanan} terim zaten sözlükte olduğu için korundu` +
          (sonuc.bozuk > 0 ? `, ${sonuc.bozuk} satır okunamadı` : '') +
          `. Toplam ${sonuc.toplam} terim.`,
      );
    } catch (hata) {
      push(messageOf(hata), 'error');
    } finally {
      setYukleniyor(false);
    }
  };

  return (
    <section className="card p-4">
      <h2 className="mb-1 text-sm font-semibold text-ink-2">Sözlük (CSV)</h2>
      <p className="mb-3 text-xs text-ink-3">
        Sözlük, çevirinin ilk katmanıdır: buradaki terimler her yerde aynı karşılığı alır ve hiçbir
        servise sorulmaz. <strong>İçe aktarımda sizin mevcut teriminiz korunur</strong> — dosyadan
        gelen satır yalnız o terim sözlükte yoksa eklenir.
      </p>

      <div className="mb-3 flex flex-wrap items-center gap-2">
        <label className="text-sm text-ink-2" htmlFor="sozluk-dil">
          Kaynak dil
        </label>
        <select
          id="sozluk-dil"
          className="field-input w-auto"
          value={dil}
          onChange={(olay) => setDil(olay.target.value)}
        >
          <option value="zh">Çince → Türkçe</option>
          <option value="en">İngilizce → Türkçe</option>
        </select>
      </div>

      <div className="flex flex-wrap gap-2">
        {/* İndirme bir GET'tir; oturum çerezi zaten gider. Ayrı bir imzalı
            adres gerekmez — bu uç panel oturumunun arkasındadır. */}
        <a className="btn-ghost" href={sozlukApi.disaAktarUrl(dil)} download data-testid="sozluk-disa-aktar">
          <Download className="h-4 w-4" aria-hidden />
          CSV olarak indir
        </a>

        <label className="btn-primary cursor-pointer" data-testid="sozluk-ice-aktar">
          <Upload className="h-4 w-4" aria-hidden />
          {yukleniyor ? 'Yükleniyor…' : 'CSV yükle'}
          <input
            type="file"
            accept=".csv,text/csv"
            className="sr-only"
            onChange={(olay) => void dosyaSecildi(olay)}
            disabled={yukleniyor}
          />
        </label>
      </div>

      <p className="mt-2 text-xs text-ink-3">
        Dosya biçimi: iki sütun (<code>kaynak;turkce</code>), noktalı virgül ya da virgül ayraçlı.
        Excel'de açıp düzenleyip aynı biçimde kaydedebilirsiniz.
      </p>
    </section>
  );
}
