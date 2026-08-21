import { Link } from 'react-router-dom';
import { ChevronRight, ClipboardList, Trash2 } from 'lucide-react';
import { PageHeader } from '../components/ui';

/**
 * ARŞİV — kapı ekranı (İE#16 D1.5 · kanon §3).
 *
 * Menüde tek satır durur; kapanan listeler, çöp kutusu ve aktivite günlüğü
 * buradan açılır. Kendi başına veri göstermez: amacı menüyü şişirmeden
 * "geçmişe bakma" işlerini tek yerde toplamaktır.
 *
 * "Kapanan listeler" Faz 2'de liste yaşam döngüsüyle gelir (kanon §7.5) —
 * bugün burada YOKTUR, uydurma bir kutu basılmaz.
 */
const kapilar = [
  {
    to: '/cop-kutusu',
    baslik: 'Çöp Kutusu',
    aciklama: 'Silinen liste ve ürünler; saklama süresi dolana dek geri alınabilir.',
    icon: Trash2,
  },
  {
    to: '/aktivite',
    baslik: 'Aktivite Günlüğü',
    aciklama: 'Kim, ne, ne zaman — sistemdeki tüm işlemlerin kaydı.',
    icon: ClipboardList,
  },
];

export default function ArsivScreen() {
  return (
    <>
      <PageHeader title="Arşiv" subtitle="Geçmiş kayıtlar ve günlükler" />

      <div className="grid gap-3 sm:grid-cols-2">
        {kapilar.map((kapi) => (
          <Link
            key={kapi.to}
            to={kapi.to}
            className="card flex items-center gap-3 p-4 transition-colors hover:border-g300"
          >
            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-g100 text-ink-2">
              <kapi.icon size={18} aria-hidden />
            </span>
            <span className="min-w-0 flex-1">
              <span className="block font-semibold text-ink">{kapi.baslik}</span>
              <span className="block text-md text-ink-3">{kapi.aciklama}</span>
            </span>
            <ChevronRight size={18} className="shrink-0 text-ink-3" aria-hidden />
          </Link>
        ))}
      </div>
    </>
  );
}
