import { useCallback, useEffect, useRef, useState } from 'react';
import { messageOf } from './useAsync';

/**
 * UZUN SÜREN İŞLEM DESENİ (İE#14 C2) — tek kaynak.
 *
 * SORUN: görsel arşivi taşıma, yedekleme, migration, export ve çeviri gibi işler
 * saniyelerce sürüyor; ekran sessiz kalınca kullanıcı "takıldı mı?" diye düğmeye
 * tekrar basıyordu. Aynı işi iki kez başlatmak yedeklemede ve taşımada gerçek
 * zarardır.
 *
 * DESEN (her uzun işlemde AYNI):
 *   • Düğme işlem boyunca KAPALI, üzerinde fiil yazar ("Taşınıyor…").
 *   • Bilgi şeridi ne olduğunu ve geçen süreyi söyler.
 *   • Sayılabilen işlerde GERÇEK ilerleme yazılır ("12/40 taşındı") — sahte
 *     yüzde çubuğu YOK; bilmediğimiz şeyi biliyormuş gibi göstermeyiz.
 *   • 60 saniyeyi geçerse "beklenenden uzun sürüyor" uyarısı + İptal görünür.
 *   • Bitince sonuç kartı kalır (başarılı/başarısız + ne oldu), tekrar denenebilir.
 *
 * İPTAL: parti parti çalışan işlerde `iptalIstendi()` sıradaki partiden önce
 * denetlenir — yarım kalan parti geri alınmaz, ÇÜNKÜ her parti kendi başına
 * tamamlanmış bir iştir (taşınan görsel taşınmış kalır). Tek atımlık isteklerde
 * (yedek/migration) iptal isteği kaydedilir ve yanıt gelince sonuç "iptal edildi"
 * olarak işaretlenir; sunucu işi yarıda bırakılmaz — yarım migration tehlikelidir.
 */

/** 60 sn: bu eşiği geçen iş "uzun" sayılır ve kullanıcıya çıkış yolu gösterilir. */
const UZUN_ESIK_SN = 60;

export interface IslemSonucu {
  basarili: boolean;
  metin: string;
}

export interface UzunIslem {
  calisiyor: boolean;
  /** İşin başlamasından bu yana geçen saniye. */
  gecenSaniye: number;
  /** 60 sn eşiği aşıldı mı? */
  uzunSuruyor: boolean;
  /** Sayılabilen işlerde gerçek ilerleme metni. */
  ilerleme: string | null;
  sonuc: IslemSonucu | null;
  /** İşin kendisi; `rapor` ile ilerleme yazar, `iptalIstendi` ile iptali yoklar. */
  baslat: (
    is: (rapor: (metin: string) => void, iptalIstendi: () => boolean) => Promise<string>,
  ) => Promise<void>;
  iptalEt: () => void;
  temizle: () => void;
}

export function useUzunIslem(): UzunIslem {
  const [calisiyor, setCalisiyor] = useState(false);
  const [gecenSaniye, setGecenSaniye] = useState(0);
  const [ilerleme, setIlerleme] = useState<string | null>(null);
  const [sonuc, setSonuc] = useState<IslemSonucu | null>(null);

  // Çift tıklama koruması state ile değil ref ile: state güncellemesi asenkron
  // olduğundan hızlı iki tık aynı `calisiyor:false` değerini görebilir.
  const kilit = useRef(false);
  const iptal = useRef(false);

  useEffect(() => {
    if (!calisiyor) return;
    const sayac = window.setInterval(() => setGecenSaniye((deger) => deger + 1), 1000);

    return () => window.clearInterval(sayac);
  }, [calisiyor]);

  const baslat = useCallback(
    async (is: (rapor: (metin: string) => void, iptalIstendi: () => boolean) => Promise<string>) => {
      if (kilit.current) return; // çift tıklama: ikinci çağrı sessizce düşer
      kilit.current = true;
      iptal.current = false;
      setCalisiyor(true);
      setGecenSaniye(0);
      setIlerleme(null);
      setSonuc(null);

      try {
        const metin = await is(
          (durum: string) => setIlerleme(durum),
          () => iptal.current,
        );
        setSonuc({ basarili: !iptal.current, metin: iptal.current ? `İptal edildi. ${metin}` : metin });
      } catch (caught) {
        setSonuc({ basarili: false, metin: messageOf(caught) });
      } finally {
        kilit.current = false;
        setCalisiyor(false);
        setIlerleme(null);
      }
    },
    [],
  );

  const iptalEt = useCallback(() => {
    iptal.current = true;
  }, []);

  const temizle = useCallback(() => setSonuc(null), []);

  return {
    calisiyor,
    gecenSaniye,
    uzunSuruyor: calisiyor && gecenSaniye >= UZUN_ESIK_SN,
    ilerleme,
    sonuc,
    baslat,
    iptalEt,
    temizle,
  };
}
