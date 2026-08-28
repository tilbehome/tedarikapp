import { Link, useSearchParams } from 'react-router-dom';
import { ChevronRight, CircleDashed } from 'lucide-react';
import { PageHeader } from '../components/ui';
import { AYAR_SEKMELERI, sekmeyiCoz } from './ayarlar/sekmeler';
import CiktilarAntet from './ayarlar/bolumler/CiktilarAntet';
import PaylasimSekmesi from './ayarlar/bolumler/PaylasimSekmesi';
import CeviriAyarlari from './ayarlar/CeviriAyarlari';
import KuyrukDurumu from './ayarlar/KuyrukDurumu';
import Kurlar from './ayarlar/bolumler/Kurlar';
import Guvenlik from './ayarlar/bolumler/Guvenlik';
import VeriBakim from './ayarlar/bolumler/VeriBakim';
import Sozluk from './ayarlar/bolumler/Sozluk';
import BildirimAyarlari from './ayarlar/bolumler/BildirimAyarlari';
import GenelAyarlar from './ayarlar/bolumler/GenelAyarlar';

/**
 * AYARLAR — 16 SEKME (V3-B Blok C).
 *
 * ÖNCEKİ HÂLİ: 645 satırlık tek sayfa; kur, çeviri, güvenlik, yedek ve sistem
 * durumu alt alta akıyordu. Bir ayarı bulmak için sayfayı taramak gerekiyordu
 * ve yeni her ayar sayfayı bir kart daha uzatıyordu.
 *
 * SEKME KODU URL'DEDİR (`/ayarlar?sekme=kur`): kullanıcı bir sekmeyi
 * yer imine ekleyebilir, tarayıcının geri düğmesi çalışır, destek isterken
 * "şu adrese bak" diyebilir. Sekmeyi bileşen durumunda tutmak bu üçünü de
 * kaybettirirdi.
 *
 * TAŞIMADA KORUNANLAR (C2 koruma listesi) — her biri kendi bileşeninde
 * gerekçesiyle yazılıdır: LLM anahtar uyarısı (8), arka plan işleri kartının
 * iki sinyali (15), kur geçmişinin yeni alanları (7), medya yedeği alanları
 * (16). `damgayi_esitle` BURAYA TAŞINMADI ve taşınmamalıdır: o bir teşhis
 * eylemidir, sekiz durumlu kurulum sözleşmesine (D2-REV) aittir.
 */
export default function SettingsScreen() {
  const [sorgu, setSorgu] = useSearchParams();
  const aktif = sekmeyiCoz(sorgu.get('sekme'));

  return (
    <>
      <PageHeader title="Ayarlar" subtitle={aktif.kapsam} />

      <nav
        className="mb-4 flex gap-1 overflow-x-auto border-b border-line pb-px"
        aria-label="Ayar sekmeleri"
        data-testid="ayar-sekmeleri"
      >
        {AYAR_SEKMELERI.map((sekme) => (
          <button
            key={sekme.kod}
            type="button"
            className={`shrink-0 whitespace-nowrap rounded-t-lg px-3 py-2 text-sm transition-colors ${
              sekme.kod === aktif.kod
                ? 'border-b-2 border-blue font-semibold text-ink'
                : 'text-ink-3 hover:bg-g50 hover:text-ink-2'
            }`}
            onClick={() => setSorgu({ sekme: sekme.kod })}
            aria-current={sekme.kod === aktif.kod ? 'page' : undefined}
            data-testid={`ayar-sekme-${sekme.kod}`}
          >
            {sekme.ad}
            {!sekme.dolu ? (
              <CircleDashed size={12} className="ml-1 inline text-ink-3" aria-label="henüz boş" />
            ) : null}
          </button>
        ))}
      </nav>

      <SekmeIcerigi kod={aktif.kod} />
    </>
  );
}

function SekmeIcerigi({ kod }: { kod: string }) {
  const sekme = sekmeyiCoz(kod);

  if (!sekme.dolu) {
    // BOŞ SEKME GİZLENMEZ, AÇIKLANIR. Panorama'daki "henüz ölçülmüyor"
    // ayrımının aynısı: kullanıcı neyin var olmadığını da bilmeli.
    return (
      <section className="card p-6 text-center" data-testid="ayar-sekme-bos">
        <CircleDashed size={20} className="mx-auto mb-2 text-ink-3" aria-hidden />
        <p className="text-sm font-medium text-ink-2">Bu sekmede henüz ayar yok.</p>
        <p className="mx-auto mt-1 max-w-md text-sm text-ink-3">{sekme.bekleyen}</p>
      </section>
    );
  }

  switch (kod) {
    case 'genel':
      return <GenelAyarlar />;
    case 'listeler':
      return (
        <Link to="/ayarlar/kategoriler" className="card flex items-center justify-between p-4 hover:bg-g50">
          <span>
            <span className="block font-semibold">Kategoriler</span>
            <span className="block text-xs text-ink-3">Ürün kategorilerini düzenle</span>
          </span>
          <ChevronRight className="h-5 w-5 text-ink-3" aria-hidden />
        </Link>
      );
    case 'kur':
      return <Kurlar />;
    case 'ceviri':
      return <CeviriAyarlari />;
    case 'diller':
      return <Sozluk />;
    case 'ciktilar':
      return <CiktilarAntet />;
    case 'paylasim':
      return <PaylasimSekmesi />;
    case 'bildirimler':
      return <BildirimAyarlari />;
    case 'guvenlik':
      return <Guvenlik />;
    case 'kuyruk':
      return <KuyrukDurumu />;
    case 'veri':
      return <VeriBakim />;
    default:
      return null;
  }
}
