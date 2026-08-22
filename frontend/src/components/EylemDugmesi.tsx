import { Loader2 } from 'lucide-react';
import { useCallback, useRef, useState, type ReactNode } from 'react';

/**
 * B11 — HİÇBİR EYLEM SESSİZ ÇALIŞMAZ (İE#20 C10).
 *
 * Panel genelinde geçerli kural: her async eylemin ÜÇ durumu da görünür olur —
 * **meşgul**, **başarı**, **hata**. Bu bileşen kuralın "meşgul" ayağını tek yerde
 * uygular ve iki somut hatayı yapısal olarak imkânsız kılar:
 *
 *  1. **Çift tık mükerrer istek üretemez.** Sadece `disabled` koymak yetmez:
 *     React durumu bir sonraki render'da güncellenir, iki hızlı tık ARADA kalır
 *     ve iki istek gider. Bu yüzden koruma bir `ref` ile senkron yapılır — tık
 *     anında kapanır, render beklemez. Gelen Kutusu'nda bu, aynı yakalamanın iki
 *     kez taşınmasına yol açıyordu (sunucu tarafı G6 ile de kapatıldı; burada
 *     kullanıcı hiç ikinci isteği göndermez).
 *
 *  2. **Kullanıcı bir şey olduğunu görür.** Düğme metni eylem sürerken
 *     "Taşınıyor…" gibi bir FİİL alır ve dönen simge eşlik eder; `aria-busy` ile
 *     ekran okuyucuya da bildirilir. Sessiz bekleme, kullanıcıya "tıklamadım
 *     galiba" dedirtip tekrar tıklatan şeydir.
 *
 * Başarı/hata ayağı ÇAĞIRANIN işidir (toast + satırların düşmesi + rozet
 * güncellemesi) — panelin mevcut deseni budur: `assign`/`remove` kendi
 * `try/catch`inde hatayı yakalar ve kullanıcıya gösterir.
 *
 * Peki `onEylem` yine de reddederse? Bileşen bunu SESSİZCE YUTMAZ ama
 * yakalanmamış bir söz de bırakmaz (yakalanmamış reddetme hiçbir yere
 * görünmez — en kötü "sessiz yutma" biçimidir). Hata `onHata` geri çağrısına
 * iletilir; verilmemişse bileşen düğmeyi yeniden kullanılabilir yapar ve hatayı
 * çağıranın raporladığını VARSAYAR. Bu varsayım sözleşmedir: `onEylem` kendi
 * hatasını raporlamıyorsa `onHata` vermek ZORUNLUDUR.
 */
export interface EylemDugmesiProps {
  /** Boştayken görünen metin. */
  children: ReactNode;
  /** Çalışırken görünen FİİL: "Taşınıyor", "Siliniyor" (üç nokta eklenir). */
  mesgulEtiketi: string;
  onEylem: () => Promise<unknown>;
  /** `onEylem` kendi hatasını raporlamıyorsa ZORUNLU: hata buraya iletilir. */
  onHata?: (hata: unknown) => void;
  className?: string;
  disabled?: boolean;
  title?: string;
  'aria-label'?: string;
}

export default function EylemDugmesi({
  children,
  mesgulEtiketi,
  onEylem,
  onHata,
  className = 'btn-primary',
  disabled = false,
  title,
  'aria-label': ariaLabel,
}: EylemDugmesiProps) {
  const [mesgul, setMesgul] = useState(false);
  // Senkron kilit: render beklemeden kapanır (çift tık koruması).
  const kilit = useRef(false);

  const tikla = useCallback(async () => {
    if (kilit.current || disabled) return;
    kilit.current = true;
    setMesgul(true);
    try {
      await onEylem();
    } catch (hata) {
      onHata?.(hata);
    } finally {
      kilit.current = false;
      setMesgul(false);
    }
  }, [disabled, onEylem, onHata]);

  return (
    <button
      type="button"
      className={className}
      disabled={disabled || mesgul}
      aria-busy={mesgul}
      title={title}
      aria-label={ariaLabel}
      onClick={() => void tikla()}
    >
      {mesgul ? (
        <span className="inline-flex items-center gap-2">
          <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
          {mesgulEtiketi}…
        </span>
      ) : (
        children
      )}
    </button>
  );
}
