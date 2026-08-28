import { useState } from 'react';
import { ImageOff } from 'lucide-react';

/**
 * Kuyruk görseli (İE#13 B4): alicdn hotlink engeli yüzünden UZAK adres 403 dönebilir —
 * o zaman kırık resim kutusu değil, DÜZGÜN yer tutucu görünür.
 *
 * Not: yakalama anındaki adres uzaktır; ürün listeye taşındığında medya arşiv hattı
 * (K47) devreye girer ve görsel `/media/...` altından servis edilir. Kuyruk aşamasında
 * arşive indirme YAPILMAZ — henüz ürün olmayan kayıt için disk tüketilmez.
 */
export default function InboxThumb({ src, className = '' }: { src: string | null; className?: string }) {
  const [bozuk, setBozuk] = useState(false);
  const kutu = `flex shrink-0 items-center justify-center rounded-xl border border-line bg-g100 ${className}`;

  if (src === null || src === '' || bozuk) {
    return (
      <span className={kutu} title="Görsel gösterilemiyor (kaynak site engeli)">
        <ImageOff className="h-4 w-4 text-ink-3" aria-hidden />
      </span>
    );
  }

  return (
    <img
      src={src}
      alt=""
      loading="lazy"
      referrerPolicy="no-referrer"
      onError={() => setBozuk(true)}
      className={`shrink-0 rounded-xl border border-line object-cover ${className}`}
    />
  );
}
