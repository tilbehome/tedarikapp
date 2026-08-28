import { useState } from 'react';

/**
 * SATIR İÇİ MİKTAR DÜZENLEME (İE#21 B2 · referans: `liste-ici.png` "Miktar" sütunu).
 *
 * Miktar, bir listede en sık değişen alandır: firmadan gelen koli adedine göre
 * 240 olur, 300 olur. Bunun için ürün çekmecesini açıp kaydetmek üç tıklama ve
 * bir ekran değişimi demekti; şimdi kutuya yazıp Enter'a basmak yetiyor.
 *
 * KURALLAR:
 *  · Enter kaydeder, Esc vazgeçer, odak kaybı da kaydeder (kullanıcı "kaydettim"
 *    sanıp sekmesine geçtiğinde veri kaybolmasın).
 *  · Değer değişmediyse istek GÖNDERİLMEZ — boşuna revizyon ilerlemesin.
 *  · Miktar TAM SAYIDIR ve para değildir; burada aritmetik yapılmaz, sunucunun
 *    döndürdüğü satır toplamı tabloya yeniden basılır (K14/K29).
 *  · Sunucu reddederse (kilitli/donmuş liste) kutu ESKİ değere döner: ekranda
 *    kalan sahte bir sayı, kullanıcıya olmayan bir gerçeği gösterirdi.
 */

interface Props {
  deger: number;
  etiket: string;
  kapali?: boolean;
  onKaydet: (yeni: number) => Promise<void>;
}

export default function MiktarHucresi({ deger, etiket, kapali = false, onKaydet }: Props) {
  const [taslak, setTaslak] = useState(String(deger));
  const [mesgul, setMesgul] = useState(false);

  // Dışarıdan gelen değer değişirse (yenileme, toplu işlem) kutu onu izler.
  // Bu, efektle değil RENDER SIRASINDA türetilir: efektle yapılsaydı kutu bir
  // kare boyunca eski sayıyı gösterir ve React zincirleme render uyarısı verirdi.
  const [sonProp, setSonProp] = useState(deger);
  if (deger !== sonProp) {
    setSonProp(deger);
    setTaslak(String(deger));
  }

  const kaydet = async () => {
    const yeni = Number(taslak);
    if (!Number.isInteger(yeni) || yeni < 1) {
      setTaslak(String(deger));

      return;
    }
    if (yeni === deger) return;

    setMesgul(true);
    try {
      await onKaydet(yeni);
    } catch {
      // Hata mesajını çağıran gösterir; kutu gerçeğe döner.
      setTaslak(String(deger));
    } finally {
      setMesgul(false);
    }
  };

  return (
    <input
      type="number"
      min={1}
      inputMode="numeric"
      className="field-input h-9 w-20 text-right disabled:opacity-60"
      value={taslak}
      disabled={kapali || mesgul}
      aria-label={`${etiket} miktarı`}
      data-testid="miktar-hucresi"
      onChange={(olay) => setTaslak(olay.target.value)}
      onBlur={() => void kaydet()}
      onKeyDown={(olay) => {
        if (olay.key === 'Enter') {
          olay.preventDefault();
          void kaydet();
        }
        if (olay.key === 'Escape') {
          olay.preventDefault();
          setTaslak(String(deger));
        }
      }}
    />
  );
}
