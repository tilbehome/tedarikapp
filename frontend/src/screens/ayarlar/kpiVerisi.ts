import { useEffect, useState } from 'react';
import {
  ceviri as ceviriApi,
  gunluk as gunlukApi,
  settings as settingsApi,
  system as systemApi,
} from '../../api/endpoints';
import type { KpiKarti } from './KpiSerit';

/**
 * KPI VERİ KAYNAKLARI (V3-B madde 4).
 *
 * KURAL: YENİ UÇ AÇILMAZ, mevcut GET'ler kullanılır. Bir ölçü mevcut
 * uçlardan güvenilir okunamıyorsa KART ÜRETİLMEZ (null döner) — tahmini bir
 * sayı basmak yasak (PM sapma #2, K67).
 *
 * Kaynak tablosu (rapora birebir girer):
 *
 * | Kart                  | Kaynak                                        |
 * |-----------------------|-----------------------------------------------|
 * | Son yedek yaşı        | `GET /api/system/backups` → `last_age_seconds` |
 * | Yedek zamanlaması     | `GET /api/system/backups` → `cron`             |
 * | Son 24s hata          | `GET /api/gunluk?seviye=error` → sayım         |
 * | Bekleyen migration    | `GET /api/system/status` → `migrations`        |
 * | Çeviri anahtarı       | `GET /api/settings/translation` → `anahtar_tanimli` |
 * | Etkin çeviri modeli   | `GET /api/settings/translation` → `model`      |
 * | Hedef dil sayısı      | `GET /api/settings/translation` → `hedef_diller` |
 * | Aktif kur yaşı        | `GET /api/settings/rates/history` → aktif satır |
 *
 * ÖLÇÜLEMEYENLER (kart YOK, gerekçesiyle):
 *   · Medya arşivi dosya sayısı — yalnız `POST /api/system/media-check`
 *     TARAMASIYLA bulunuyor; her ayar ziyaretinde tarama başlatmak yanlış.
 *   · Çeviri önbellek satırı / son 24s çağrı / bağlantı testi sonucu —
 *     mevcut uçların yanıtında yok; API sözleşmesi bu emrin kapsamı dışı.
 *   · Disk kullanımı ("Depolama" kartı) — paylaşımlı hostingte güvenilir
 *     okunamıyor (PM sapma #2).
 */

/** Saniyeyi insan diline çevirir; null ise null döner (uydurma yok). */
function yas(saniye: number | null | undefined): string | null {
  if (saniye === null || saniye === undefined) return null;
  const saat = Math.floor(saniye / 3600);
  if (saat < 1) return `${Math.max(1, Math.floor(saniye / 60))} dk`;
  if (saat < 48) return `${saat} saat`;

  return `${Math.floor(saat / 24)} gün`;
}

export function useSistemKpi(): (KpiKarti | null)[] {
  const [kartlar, setKartlar] = useState<(KpiKarti | null)[]>([]);

  useEffect(() => {
    let iptal = false;

    void Promise.allSettled([
      systemApi.backupList(),
      systemApi.status(),
      gunlukApi.read({ seviye: 'error', limit: 200 }),
    ]).then(([yedek, durum, log]) => {
      if (iptal) return;

      const y = yedek.status === 'fulfilled' ? yedek.value : null;
      const d = durum.status === 'fulfilled' ? durum.value : null;
      const g = log.status === 'fulfilled' ? log.value : null;

      const sonYedek = yas(y?.last_age_seconds);
      const dun = Date.now() - 24 * 3600 * 1000;
      // Uç en çok 200 satır döner; 200'e dayanırsa "200+" yazılır. Kesin
      // sayıymış gibi göstermek, tavana dayanmış bir ölçüyü gizlerdi.
      const hatalar = g?.kaynak_var === true
        ? (g.kayitlar ?? []).filter((k) => new Date(k.zaman).getTime() >= dun).length
        : null;
      const tavanda = (g?.kayitlar ?? []).length >= 200;

      setKartlar([
        sonYedek === null
          ? null
          : {
              etiket: 'Son yedek',
              deger: sonYedek,
              alt: y?.gecikti === true ? '30 saati aştı' : 'zamanında',
              ton: y?.gecikti === true ? 'uyari' : 'iyi',
            },
        y?.cron === null || y?.cron === undefined
          ? { etiket: 'Zamanlama', deger: 'Kapalı', alt: 'cron izi yok', ton: 'uyari' }
          : { etiket: 'Zamanlama', deger: y.cron.ok ? 'Çalışıyor' : 'Hatalı', alt: yas(y.cron.age_seconds), ton: y.cron.ok ? 'iyi' : 'uyari' },
        hatalar === null
          ? null
          : {
              etiket: 'Son 24 saat hata',
              deger: tavanda ? '200+' : String(hatalar),
              alt: hatalar > 0 ? 'Günlük bölümünde' : 'temiz',
              ton: hatalar > 0 ? 'uyari' : 'iyi',
            },
        d === null
          ? null
          : {
              etiket: 'Bekleyen migration',
              deger: String(d.migrations.pending_count),
              alt: d.migrations.pending_count > 0 ? 'kurulum yarım' : 'güncel',
              ton: d.migrations.pending_count > 0 ? 'uyari' : 'iyi',
            },
      ]);
    });

    return () => {
      iptal = true;
    };
  }, []);

  return kartlar;
}

export function useCeviriKpi(): (KpiKarti | null)[] {
  const [kartlar, setKartlar] = useState<(KpiKarti | null)[]>([]);

  useEffect(() => {
    let iptal = false;

    ceviriApi
      .ayarlar()
      .then((veri) => {
        if (iptal) return;
        setKartlar([
          {
            etiket: 'Sağlayıcı anahtarı',
            deger: veri.anahtar_tanimli ? 'Tanımlı' : 'Eksik',
            alt: veri.anahtar_onizleme,
            ton: veri.anahtar_tanimli ? 'iyi' : 'uyari',
          },
          { etiket: 'Etkin model', deger: veri.model, alt: veri.saglayici, ton: 'notr' },
          {
            etiket: 'Hedef dil',
            deger: String(veri.hedef_diller.length),
            alt: veri.hedef_diller.map((d) => d.toUpperCase()).join(' · '),
            ton: 'notr',
          },
        ]);
      })
      .catch(() => undefined);

    return () => {
      iptal = true;
    };
  }, []);

  return kartlar;
}

export function useKurKpi(): (KpiKarti | null)[] {
  const [kartlar, setKartlar] = useState<(KpiKarti | null)[]>([]);

  useEffect(() => {
    let iptal = false;

    settingsApi
      .rateHistory('CNY')
      .then((satirlar) => {
        if (iptal) return;
        const aktif = satirlar.find((satir) => satir.aktif);
        if (aktif === undefined) {
          // Hiç snapshot yoksa YAŞ HESAPLANMAZ. "0 saat" demek, kuru az önce
          // onaylanmış göstermek olurdu (K67: bilinmeyen ≠ sıfır).
          setKartlar([]);

          return;
        }

        const saat = Math.floor((Date.now() - new Date(aktif.set_at).getTime()) / 3600000);
        setKartlar([
          {
            etiket: 'Aktif kur yaşı',
            deger: saat < 48 ? `${saat} saat` : `${Math.floor(saat / 24)} gün`,
            alt: saat >= 24 ? 'kontrol edin' : 'güncel',
            ton: saat >= 24 ? 'uyari' : 'iyi',
          },
          {
            etiket: 'Yuan → TL',
            deger: aktif.rate,
            alt: aktif.kaynak === 'tcmb' ? 'TCMB önerisi' : 'elle onay',
            ton: 'notr',
          },
        ]);
      })
      .catch(() => undefined);

    return () => {
      iptal = true;
    };
  }, []);

  return kartlar;
}
