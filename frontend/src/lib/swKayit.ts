/**
 * SERVICE WORKER KAYDI (V3-B E2).
 *
 * KAPSAM AÇIKÇA YAZILIR (`scope: '/panel/'`). Tarayıcı kapsamı dosyanın
 * konumundan da türetirdi ama açık yazmak bir SÖZLEŞMEDİR: dosya bir gün
 * başka bir yere taşınırsa kayıt sessizce genişlemek yerine BAŞARISIZ olur.
 * Paylaşım sayfasının (`/liste/...`) yanlışlıkla kapsama girmesi, oturumsuz
 * bir sayfanın tarayıcıda kalıcılaşması demekti.
 *
 * Kayıt YARDIMCIDIR: başarısız olursa panel aynen çalışır. PWA bir kolaylıktır,
 * uygulamanın koşulu değil.
 */
export function serviceWorkeriKaydet(): void {
  if (!('serviceWorker' in navigator)) return;

  // Geliştirmede kayıt YAPILMAZ: Vite'ın sıcak yenilemesi önbelleklenmiş bir
  // kabukla çakışır ve "kod değişti ama ekran değişmiyor" tuzağı doğar.
  if (import.meta.env.DEV) return;

  window.addEventListener('load', () => {
    void navigator.serviceWorker.register('/panel/sw.js', { scope: '/panel/' }).catch(() => undefined);
  });
}
