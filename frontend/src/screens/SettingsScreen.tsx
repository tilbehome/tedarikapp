import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { ChevronRight, CircleDashed } from 'lucide-react';
import { system as systemApi, ceviri as ceviriApi } from '../api/endpoints';
import { PageHeader } from '../components/ui';
import { AYAR_BOLUMLERI, AYAR_GRUPLARI, bolumuCoz, type AyarBolumu } from './ayarlar/sekmeler';
import AyarGezinme from './ayarlar/AyarGezinme';
import BolumBasligi, { type DurumTonu } from './ayarlar/BolumBasligi';
import KpiSerit from './ayarlar/KpiSerit';
import { useCeviriKpi, useKurKpi, useSistemKpi } from './ayarlar/kpiVerisi';
import CiktilarAntet from './ayarlar/bolumler/CiktilarAntet';
import PaylasimSekmesi from './ayarlar/bolumler/PaylasimSekmesi';
import CeviriAyarlari from './ayarlar/CeviriAyarlari';
import KuyrukDurumu from './ayarlar/KuyrukDurumu';
import Kurlar from './ayarlar/bolumler/Kurlar';
import Guvenlik from './ayarlar/bolumler/Guvenlik';
import VeriBakim from './ayarlar/bolumler/VeriBakim';
import Gunluk from './ayarlar/bolumler/Gunluk';
import SistemDurumu from './ayarlar/bolumler/SistemDurumu';
import Sozluk from './ayarlar/bolumler/Sozluk';
import BildirimAyarlari from './ayarlar/bolumler/BildirimAyarlari';
import GenelAyarlar from './ayarlar/bolumler/GenelAyarlar';
import GorunumPanorama from './ayarlar/bolumler/GorunumPanorama';

/**
 * AYARLAR — SOL DİKEY GEZİNME + SEÇİLİ BÖLÜM (V3-B yeniden tasarım).
 *
 * ÖNCEKİ HÂLİ 16 SEKMELİK YATAY ŞERİTTİ ve kabul turunda reddedildi: on altı
 * madde şeride sığmıyor, kullanıcı neyin var olduğunu tek bakışta göremiyordu.
 *
 * SEKME KODU URL'DE KALIR (`?sekme=kur`) — yer imine eklenebilir, geri düğmesi
 * çalışır, destek isterken adres paylaşılabilir. Eski kodlar (`veri`,
 * `panorama`) yeni bölümlere EŞLENİR; sessizce ilk bölüme düşmek, yer imine
 * eklenmiş bir adresi yanlış ekrana götürürdü.
 *
 * ROZET (madde 7): dikkat isteyen bölümde turuncu nokta. Kaynağı GERÇEK
 * VERİDİR — sistem durumu ve çeviri ayarı; uydurma bir "3" rozeti yok.
 */
export default function SettingsScreen() {
  const [sorgu, setSorgu] = useSearchParams();
  const [arama, setArama] = useState('');
  const aktif = bolumuCoz(sorgu.get('sekme'));
  const rozetler = useDikkatRozetleri();

  const sec = (bolum: AyarBolumu): void => setSorgu({ sekme: bolum.kod });

  return (
    <>
      <PageHeader title="Ayarlar" subtitle="Uygulamanın çalışma biçimi" />

      {/* Dar ekran (<900px): sol sütun üstte açılır listeye döner. */}
      <div className="mb-4 lg:hidden" data-testid="ayar-gezinme-mobil">
        <label className="block text-xs text-ink-3" htmlFor="ayar-bolum-secici">
          Bölüm
        </label>
        <select
          id="ayar-bolum-secici"
          className="field-input mt-1"
          value={aktif.kod}
          onChange={(olay) => {
            const bulunan = AYAR_BOLUMLERI.find((bolum) => bolum.kod === olay.target.value);
            if (bulunan) sec(bulunan);
          }}
        >
          {AYAR_GRUPLARI.map((grup) => (
            <optgroup key={grup.baslik} label={grup.baslik}>
              {grup.bolumler.map((bolum) => (
                <option key={bolum.kod} value={bolum.kod}>
                  {bolum.ad}
                  {rozetler.has(bolum.kod) ? ' •' : ''}
                </option>
              ))}
            </optgroup>
          ))}
        </select>
      </div>

      <div className="grid gap-5 lg:grid-cols-[17rem_minmax(0,1fr)]">
        <div className="hidden lg:block">
          <AyarGezinme aktifKod={aktif.kod} sorgu={arama} onSorgu={setArama} onSec={sec} rozetler={rozetler} />
        </div>

        <div className="min-w-0">
          <BolumBasligi
            ikon={aktif.ikon}
            ad={aktif.ad}
            kapsam={aktif.kapsam}
            cip={aktif.dolu ? null : { metin: 'Hazırlanıyor', ton: 'notr' as DurumTonu }}
          />
          <BolumIcerigi bolum={aktif} />
        </div>
      </div>
    </>
  );
}

/**
 * DİKKAT ROZETLERİ (madde 7).
 *
 * Yalnız GERÇEK sinyaller: LLM anahtarı eksik · yedek 30 saati aştı ·
 * bekleyen migration · katalog eksik · yazılamayan bildirim. Hepsi mevcut
 * uçlardan okunur; hiçbiri tahmin değildir.
 */
function useDikkatRozetleri(): Set<string> {
  const [rozetler, setRozetler] = useState<Set<string>>(new Set());

  useEffect(() => {
    let iptal = false;

    void Promise.allSettled([systemApi.status(), systemApi.backupList(), ceviriApi.ayarlar()]).then(
      ([durum, yedek, ceviri]) => {
        if (iptal) return;
        const yeni = new Set<string>();

        if (durum.status === 'fulfilled') {
          if (durum.value.migrations.pending_count > 0) yeni.add('durum');
          if (durum.value.kataloglar.some((k) => !k.saglikli)) yeni.add('durum');
          if (durum.value.bildirim_hatalari.sayi > 0) yeni.add('bildirimler');
        }
        if (yedek.status === 'fulfilled' && yedek.value.gecikti) yeni.add('sistem');
        if (ceviri.status === 'fulfilled' && !ceviri.value.anahtar_tanimli) yeni.add('ceviri');

        setRozetler(yeni);
      },
    );

    return () => {
      iptal = true;
    };
  }, []);

  return rozetler;
}

function BolumIcerigi({ bolum }: { bolum: AyarBolumu }) {
  const sistemKpi = useSistemKpi();
  const ceviriKpi = useCeviriKpi();
  const kurKpi = useKurKpi();

  if (!bolum.dolu) {
    // BOŞ BÖLÜM GİZLENMEZ, AÇIKLANIR (K98).
    return (
      <section className="card p-6 text-center" data-testid="ayar-sekme-bos">
        <CircleDashed size={20} className="mx-auto mb-2 text-ink-3" aria-hidden />
        <p className="text-sm font-medium text-ink-2">Bu bölümde henüz ayar yok.</p>
        <p className="mx-auto mt-1 max-w-md text-sm text-ink-3">{bolum.bekleyen}</p>
      </section>
    );
  }

  switch (bolum.kod) {
    case 'genel':
      return <GenelAyarlar />;
    case 'gorunum':
      return <GorunumPanorama />;
    case 'bildirimler':
      return <BildirimAyarlari />;
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
      return (
        <>
          <KpiSerit kartlar={kurKpi} />
          <Kurlar />
        </>
      );
    case 'ceviri':
      return (
        <>
          <KpiSerit kartlar={ceviriKpi} />
          <CeviriAyarlari />
        </>
      );
    case 'diller':
      return <Sozluk />;
    case 'ciktilar':
      return <CiktilarAntet />;
    case 'paylasim':
      return <PaylasimSekmesi />;
    case 'guvenlik':
      return <Guvenlik />;
    case 'sistem':
      return (
        <>
          <KpiSerit kartlar={sistemKpi} />
          <VeriBakim />
          <div className="mt-4">
            <KuyrukDurumu />
          </div>
        </>
      );
    case 'gunluk':
      return <Gunluk />;
    case 'durum':
      return <SistemDurumu />;
    default:
      return null;
  }
}
