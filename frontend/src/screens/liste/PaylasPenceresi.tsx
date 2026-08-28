import { useState } from 'react';
import { Copy, Link2, Mail, MessageCircle, X } from 'lucide-react';
import { share as shareApi } from '../../api/endpoints';
import { messageOf, useAsync } from '../../lib/useAsync';
import { useToast } from '../../components/Toast';
import { paylasimAdresiAnahtari } from './CiktiSecenekleri';

/**
 * PAYLAŞ PENCERESİ (İE#21 B6).
 *
 * Paylaşmak tek bir işlem değil, ÜÇ parçalı bir iştir ve panelde üçü bir arada
 * durmalıdır:
 *
 *   1. **Bağlantı** — üret / yenile / iptal, tam adres yalnız üretim anında görünür,
 *   2. **Erişim anahtarı** — 6 hane, AYRI KANALDAN gider (K62/K51),
 *   3. **Kanal metni** — WhatsApp/e-posta için hazır cümle, firmanın dilinde.
 *
 * BİTİŞ TARİHİ ARTIK PANELDE: API `expires_at` alanını baştan beri kabul ediyordu
 * ama panelde girişi yoktu — yani "bu link 30 Eylül'de kapanır" diyebilmek için
 * API'yi elle çağırmak gerekiyordu. Sahada kullanılmayan bir güvenlik ayarı, var
 * olmayan bir ayardır.
 *
 * KANAL METNİ SUNUCUDAN GELİR (`/share-text?lang=`): üç dilin şablonu tek yerde
 * (`ShareTexts`) durur. Sunucu `{link}` yer tutucusunu olduğu gibi döner, panel
 * onu belleğindeki adresle değiştirir — tam token istek satırına düşmez (K51).
 */

interface Props {
  listId: number;
  tokenPrefix: string | null;
  /** Panelde tutulan tam adres (üretim anında alınır, kalıcı saklanmaz). */
  adres: string | null;
  onAdres: (adres: string | null) => void;
  onDegisti: () => void;
  onKapat: () => void;
  /** Erişim anahtarı bloğu — ekrandaki bileşen olduğu gibi geçirilir. */
  anahtarBlogu: React.ReactNode;
}

const DILLER = [
  { kod: 'tr', ad: 'Türkçe' },
  { kod: 'en', ad: 'English' },
  { kod: 'zh', ad: '中文' },
] as const;

export default function PaylasPenceresi({
  listId,
  tokenPrefix,
  adres,
  onAdres,
  onDegisti,
  onKapat,
  anahtarBlogu,
}: Props) {
  const push = useToast((state) => state.push);
  const [mesgul, setMesgul] = useState(false);
  const [bitis, setBitis] = useState('');
  const [dil, setDil] = useState<'tr' | 'en' | 'zh'>('tr');

  const metin = useAsync(() => shareApi.text(listId, dil), [listId, dil]);
  const mesaj = (metin.data?.mesaj ?? '').replace('{link}', adres ?? '');
  const konu = metin.data?.konu ?? 'Tedarik listesi';

  const uret = async () => {
    setMesgul(true);
    try {
      const sonuc = await shareApi.create(listId, bitis === '' ? {} : { expires_at: bitis });
      onAdres(sonuc.share_url);
      // F6: QR için tam adres GEREKİR ama sunucuda saklanmaz (K51). Sekme ömrü
      // kadar sessionStorage'da tutulur; sekme kapanınca silinir.
      sessionStorage.setItem(paylasimAdresiAnahtari(listId), sonuc.share_url);
      onDegisti();
      push(
        sonuc.share_expires_at === null
          ? 'Paylaşım bağlantısı hazır — bu adres yalnız şimdi görünür, kopyalayın.'
          : 'Paylaşım bağlantısı hazır; bitiş tarihi kaydedildi. Adres yalnız şimdi görünür.',
      );
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setMesgul(false);
    }
  };

  const iptal = async () => {
    setMesgul(true);
    try {
      await shareApi.revoke(listId);
      onAdres(null);
      sessionStorage.removeItem(paylasimAdresiAnahtari(listId));
      onDegisti();
      push('Paylaşım bağlantısı iptal edildi — eski adres artık açılmaz.');
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setMesgul(false);
    }
  };

  const kopyala = (deger: string, bildirim: string) => {
    void navigator.clipboard.writeText(deger).then(() => push(bildirim));
  };

  return (
    <>
      <div className="fixed inset-0 z-30 bg-ink/20" onClick={onKapat} aria-hidden />

      <div
        className="fixed left-1/2 top-1/2 z-40 w-[min(36rem,calc(100vw-2rem))] max-h-[90vh] -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-2xl border border-line bg-surface shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-label="Paylaş"
        data-testid="paylas-penceresi"
      >
        <header className="flex items-center justify-between border-b border-line px-4 py-3">
          <h2 className="text-sm font-semibold text-ink">Firmaya paylaş</h2>
          <button
            type="button"
            className="btn-ghost !min-h-9 !px-2"
            onClick={onKapat}
            aria-label="Paylaş penceresini kapat"
            data-testid="paylas-kapat"
          >
            <X className="h-4 w-4" aria-hidden />
          </button>
        </header>

        <div className="space-y-4 p-4">
          {/* 1 — BAĞLANTI */}
          <section className="rounded-xl border border-line p-3" data-testid="paylas-baglanti">
            <h3 className="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-ink-3">
              <Link2 className="h-3.5 w-3.5" aria-hidden />
              Bağlantı
            </h3>

            {adres !== null ? (
              <>
                <p className="break-all rounded-lg bg-g50 p-2 font-mono text-xs" data-testid="paylas-adres">
                  {adres}
                </p>
                <p className="mt-1 text-xs text-warn">
                  Bu adres yalnız şimdi görünür (güvenlik gereği kaydedilmez) — kopyalamadan pencereyi kapatmayın.
                </p>
                <div className="mt-2 flex flex-wrap gap-2">
                  <button
                    type="button"
                    className="btn-primary !min-h-9 !text-xs"
                    onClick={() => kopyala(adres, 'Bağlantı kopyalandı.')}
                  >
                    <Copy className="h-3.5 w-3.5" aria-hidden />
                    Bağlantıyı kopyala
                  </button>
                  <button
                    type="button"
                    className="btn-ghost !min-h-9 !text-xs"
                    disabled={mesgul}
                    onClick={() => void iptal()}
                    data-testid="paylas-iptal"
                  >
                    Bağlantıyı iptal et
                  </button>
                </div>
              </>
            ) : (
              <>
                <p className="text-xs text-ink-3">
                  {tokenPrefix
                    ? `Aktif bir bağlantı var (${tokenPrefix}…). Yenilemek eski adresi ANINDA öldürür.`
                    : 'Firma için girişsiz, salt-okunur bir sayfa üretilir. Liste güncellendikçe sayfa da güncel kalır.'}
                </p>

                <label className="mt-3 block text-xs text-ink-2">
                  Bitiş tarihi (boş bırakılırsa süre sınırı olmaz)
                  <input
                    type="date"
                    className="field-input mt-1 !h-9 !text-xs"
                    value={bitis}
                    onChange={(olay) => setBitis(olay.target.value)}
                    data-testid="paylas-bitis"
                  />
                </label>

                <div className="mt-3 flex flex-wrap gap-2">
                  <button
                    type="button"
                    className="btn-primary !min-h-9 !text-xs"
                    disabled={mesgul}
                    onClick={() => void uret()}
                    data-testid="paylas-uret"
                  >
                    {tokenPrefix ? 'Bağlantıyı yenile' : 'Bağlantı üret'}
                  </button>
                  {tokenPrefix ? (
                    <button
                      type="button"
                      className="btn-ghost !min-h-9 !text-xs"
                      disabled={mesgul}
                      onClick={() => void iptal()}
                      data-testid="paylas-iptal"
                    >
                      Bağlantıyı iptal et
                    </button>
                  ) : null}
                </div>
              </>
            )}
          </section>

          {/* 2 — ERİŞİM ANAHTARI (K62): ekrandaki blok olduğu gibi taşınır. */}
          <section className="rounded-xl border border-line p-3" data-testid="paylas-anahtar">
            <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-3">Erişim anahtarı</h3>
            {anahtarBlogu}
            <p className="mt-2 text-xs text-ink-3">
              Anahtarı bağlantıyla <strong>aynı kanaldan</strong> göndermeyin; aynı yerden giderse koruma anlamsız kalır.
            </p>
          </section>

          {/* 3 — KANAL METNİ */}
          <section className="rounded-xl border border-line p-3" data-testid="paylas-metin">
            <div className="mb-2 flex items-center justify-between gap-2">
              <h3 className="text-xs font-semibold uppercase tracking-wide text-ink-3">Kanal metni</h3>
              <select
                className="field-input !h-8 !w-auto !text-xs"
                value={dil}
                aria-label="Metin dili"
                data-testid="paylas-dil"
                onChange={(olay) => setDil(olay.target.value as 'tr' | 'en' | 'zh')}
              >
                {DILLER.map((secenek) => (
                  <option key={secenek.kod} value={secenek.kod}>
                    {secenek.ad}
                  </option>
                ))}
              </select>
            </div>

            {adres === null ? (
              <p className="text-xs text-ink-3">Metin, bağlantı üretildikten sonra hazırlanır.</p>
            ) : metin.loading ? (
              <p className="text-xs text-ink-3">Metin hazırlanıyor…</p>
            ) : (
              <>
                <p className="whitespace-pre-wrap rounded-lg bg-g50 p-2 text-xs" data-testid="paylas-mesaj">
                  {mesaj}
                </p>
                <div className="mt-2 flex flex-wrap gap-2">
                  <button
                    type="button"
                    className="btn-ghost !min-h-9 !text-xs"
                    onClick={() => kopyala(mesaj, 'Metin kopyalandı.')}
                  >
                    <Copy className="h-3.5 w-3.5" aria-hidden />
                    Metni kopyala
                  </button>
                  <a
                    className="btn-ghost !min-h-9 !text-xs"
                    href={`https://wa.me/?text=${encodeURIComponent(mesaj)}`}
                    target="_blank"
                    rel="noreferrer noopener"
                    data-testid="paylas-whatsapp"
                  >
                    <MessageCircle className="h-3.5 w-3.5" aria-hidden />
                    WhatsApp
                  </a>
                  <a
                    className="btn-ghost !min-h-9 !text-xs"
                    href={`mailto:?subject=${encodeURIComponent(konu)}&body=${encodeURIComponent(mesaj)}`}
                    data-testid="paylas-eposta"
                  >
                    <Mail className="h-3.5 w-3.5" aria-hidden />
                    E-posta
                  </a>
                </div>
              </>
            )}
          </section>
        </div>
      </div>
    </>
  );
}
