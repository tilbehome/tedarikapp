import { useEffect } from 'react';

/**
 * "KAYDEDİLMEDİ" UYARISI (V3-B F1).
 *
 * Kaydedilmemiş değişikliği olan bir formdan sayfa kapatılmak ya da yenilenmek
 * istendiğinde tarayıcının kendi onay kutusunu açar.
 *
 * NEDEN TARAYICININ KUTUSU, KENDİ MODAL'IMIZ DEĞİL: sekme kapatma ve yenileme
 * JavaScript'ten engellenemez. Kendi kutumuzu açmaya çalışsaydık sekme yine
 * kapanır, kutu hiç görünmezdi. `beforeunload` bunu yapabilen TEK yoldur.
 *
 * PANEL İÇİ GEZİNMEDE KULLANILMAZ: router geçişleri bu olayı tetiklemez ve
 * onları engellemek ayrı bir karardır (bir kullanıcı "vazgeçtim, listeye
 * döneyim" derken engellenmek istemez). Bu kanca yalnız VERİ KAYBININ GERÇEK
 * olduğu durumu — sekmenin kapanmasını — kapsar.
 */
export function useKaydedilmemis(kirli: boolean): void {
  useEffect(() => {
    if (!kirli) return;

    const uyar = (olay: BeforeUnloadEvent): void => {
      // Modern tarayıcılar özel metni GÖSTERMEZ; standart uyarıyı çıkarmak
      // için `preventDefault` yeterlidir.
      olay.preventDefault();
    };

    window.addEventListener('beforeunload', uyar);

    return () => window.removeEventListener('beforeunload', uyar);
  }, [kirli]);
}
