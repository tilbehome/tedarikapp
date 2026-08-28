import { useEffect, useState } from 'react';
import { urunAdi } from '../../lib/urunAdi';
import { Link } from 'react-router-dom';
import { ExternalLink, Languages, Loader2, Pencil, X } from 'lucide-react';
import { ceviri as urunCevirApi, products as productsApi } from '../../api/endpoints';
import type { FiyatKademesi, IlanGorunumu, Product, UrunCekmecesiVerisi } from '../../api/types';
import { messageOf, useAsync } from '../../lib/useAsync';
import { useToast } from '../../components/Toast';
import { count, money } from '../../lib/format';
import { productStatusLabels } from '../../locales/tr';
import { ErrorNote, Skeleton } from '../../components/ui';
import { eksikEtiketleri } from '../../lib/eksikler';

/**
 * ÜRÜN ÇEKMECESİ (İE#21 B3 · referans: `urun-duzenleme-alani.png`).
 *
 * Liste ekranındaki söz şuydu: "bir ürüne tıklayın; galeri, varyasyonlar, fiyat
 * kademeleri, yorum özeti, skor ve notlar sağ çekmecede açılır." Çekmece bu sözü
 * TEK İSTEKTE tutar (`/cekmece`) — üç ayrı tur, çekmeceyi tıklanmaz kılardı.
 *
 * ÇEKMECE OKUMA YÜZÜDÜR, düzenleme ekranının yerine geçmez: burada ürünün tüm
 * hikâyesi görünür, "Düzenle" tam forma götürür. Bir çekmecenin içine tam form
 * sığdırmak, 20 alanlı bir formu 380 pikselde sıkıştırmak demekti.
 *
 * VERİ YOKSA "—" (K67): ilan kaydı olmayan elle girilmiş üründe ilan bölümü
 * "kaynak bilgisi yok" der. Yurt içi kıyasın bugün veri kaynağı YOK; bölüm bunu
 * açıkça söyler ve "yakında" gibi bir vaat vermez (C1).
 */

interface Props {
  urunId: number;
  onKapat: () => void;
  /** D12: çeviri bitince liste de aynı taze veriyi göstersin (D11 tek kaynak). */
  onTazele?: () => void;
}

export default function UrunCekmecesi({ urunId, onKapat, onTazele }: Props) {
  const durum = useAsync((signal) => productsApi.cekmece(urunId, signal), [urunId]);

  /**
   * D12 — "ÇEVİR" DÜĞMESİ: BU ÜRÜN, ŞİMDİ.
   *
   * Ürünün TÜM alanları (ad, özellik değerleri, varyant/renk adları, marka,
   * not) eksik dillere çevrilir. İstek SENKRONDUR: yanıt döndüğünde iş bitmiştir.
   * Bitince hem çekmece hem liste tazelenir — ikisi aynı kaynaktan okur, birinde
   * güncel diğerinde eski veri kalmaz (D11).
   *
   * K54: kullanıcının onayladığı elle düzeltmeler EZİLMEZ; sunucu yalnız eksik
   * dilleri ister.
   */
  const [ceviriyor, setCeviriyor] = useState(false);
  /** İE#22 B2: bütçeye sığmayan diller — satırda canlı gösterilir. */
  const [kalanDiller, setKalanDiller] = useState<string[]>([]);
  const push = useToast((state) => state.push);

  const cevir = async (): Promise<void> => {
    setCeviriyor(true);
    try {
      const sonuc = await urunCevirApi.urunuCevir(urunId);
      durum.reload();
      onTazele?.();
      // DÖRT DURUM AYRI SÖYLENİR (İE#22 B2 · D12 saha kanıtı).
      //
      // Gerçek LLM'de üç dil 20-40 sn sürüyor; uç artık bütçesini doldurunca
      // kalanı kuyruğa yazıp DURUM döndürüyor. Arayüz spinner'ı sonsuza kadar
      // döndürmez — ne olduğunu söyler ve kalanı satırda canlı gösterir.
      setKalanDiller(sonuc.kalan);
      if (sonuc.eksikti.length === 0) {
        push('Bu ürünün üç dili zaten tamamdı.');
      } else if (sonuc.durum === 'tamamlandi') {
        push(`Çevrildi: ${sonuc.cevrilen.map((dil) => dil.toUpperCase()).join(' + ')}`);
      } else if (sonuc.durum === 'kismen') {
        push(
          `${sonuc.cevrilen.map((dil) => dil.toUpperCase()).join(' + ')} çevrildi; ` +
            `kalan ${sonuc.kalan.map((dil) => dil.toUpperCase()).join(', ')} arka planda tamamlanacak.`,
        );
      } else {
        push(sonuc.engel ?? 'Çeviri sıraya alındı; panele girdikçe tamamlanacak.', sonuc.engel !== null ? 'error' : 'success');
      }
    } catch (hata) {
      push(messageOf(hata), 'error');
    } finally {
      setCeviriyor(false);
    }
  };

  // Esc kapatır: çekmece odağı yakalar ve klavyeyle çıkış her zaman mümkündür.
  useEffect(() => {
    const dinle = (olay: KeyboardEvent) => {
      if (olay.key === 'Escape') onKapat();
    };
    window.addEventListener('keydown', dinle);

    return () => window.removeEventListener('keydown', dinle);
  }, [onKapat]);

  return (
    <>
      {/* Örtü: dışa tıklayınca kapanır. */}
      <div className="fixed inset-0 z-30 bg-ink/20" onClick={onKapat} aria-hidden />

      <aside
        className="fixed right-0 top-0 z-40 flex h-full w-full max-w-md flex-col overflow-y-auto border-l border-line bg-surface shadow-2xl"
        role="dialog"
        aria-label="Ürün çekmecesi"
        aria-modal="true"
        data-testid="urun-cekmecesi"
      >
        <header className="sticky top-0 flex items-center justify-between gap-2 border-b border-line bg-surface px-4 py-3">
          <h2 className="truncate text-sm font-semibold text-ink">
            {durum.data?.urun === undefined ? 'Ürün' : urunAdi(durum.data.urun)}
          </h2>
          <div className="flex items-center gap-1">
            {durum.data ? (
              <button
                type="button"
                className="btn-ghost !min-h-9 !px-2 !text-xs"
                onClick={() => void cevir()}
                disabled={ceviriyor}
                data-testid="cekmece-cevir"
              >
                {ceviriyor ? (
                  <Loader2 className="h-3.5 w-3.5 animate-spin" aria-hidden />
                ) : (
                  <Languages className="h-3.5 w-3.5" aria-hidden />
                )}
                {ceviriyor ? 'Çevriliyor…' : 'Çevir'}
              </button>
            ) : null}
            {kalanDiller.length > 0 && !ceviriyor ? (
              <span className="text-xs text-warn" data-testid="cekmece-ceviri-suruyor">
                çeviri sürüyor — kalan: {kalanDiller.map((dil) => dil.toUpperCase()).join(', ')}
              </span>
            ) : null}
            {durum.data ? (
              <Link
                to={`/listeler/${durum.data.urun.list_id}/urun/${durum.data.urun.id}`}
                className="btn-ghost !min-h-9 !px-2 !text-xs"
                data-testid="cekmece-duzenle"
              >
                <Pencil className="h-3.5 w-3.5" aria-hidden />
                Düzenle
              </Link>
            ) : null}
            <button
              type="button"
              className="btn-ghost !min-h-9 !px-2"
              onClick={onKapat}
              aria-label="Çekmeceyi kapat"
              data-testid="cekmece-kapat"
            >
              <X className="h-4 w-4" aria-hidden />
            </button>
          </div>
        </header>

        <div className="flex-1 space-y-4 p-4">
          {durum.loading ? (
            <Skeleton rows={4} />
          ) : durum.error ? (
            <ErrorNote message={durum.error} onRetry={durum.reload} />
          ) : durum.data ? (
            <Icerik veri={durum.data} />
          ) : null}
        </div>
      </aside>
    </>
  );
}

function Icerik({ veri }: { veri: UrunCekmecesiVerisi }) {
  const { urun, ilan, kademeler, yorum_ozeti: yorum } = veri;

  return (
    <>
      <Galeri urun={urun} />

      <Bolum baslik="Ürün bilgileri" testId="bolum-urun">
        <Satir etiket="Miktar" deger={count(urun.qty)} />
        <Satir etiket="Birim fiyat" deger={`¥${money(urun.price_yuan)}`} />
        <Satir etiket="Satır toplamı" deger={`₺${money(urun.line_total_yuan_tl)}`} />
        <Satir etiket="Koli içi" deger={urun.units_per_carton === null ? '—' : count(urun.units_per_carton)} />
        <Satir etiket="Durum" deger={productStatusLabels[urun.status]} />
        <Satir etiket="Orijinal başlık" deger={urun.name_original ?? '—'} />
      </Bolum>

      <Eksikler urun={urun} />

      <Varyasyonlar urun={urun} />

      <Bolum baslik="Fiyat kademeleri" testId="bolum-kademeler">
        {kademeler.length === 0 ? (
          <p className="text-xs text-ink-3">Bu ilanda kademeli fiyat bildirilmemiş.</p>
        ) : (
          <ul className="space-y-1" data-testid="kademe-listesi">
            {kademeler.map((kademe: FiyatKademesi) => (
              <li key={kademe.min_adet} className="flex justify-between text-sm">
                <span className="text-ink-3">{count(kademe.min_adet)}+ adet</span>
                <span className="font-medium">¥{money(kademe.birim_fiyat)}</span>
              </li>
            ))}
          </ul>
        )}
      </Bolum>

      <SkorBolumu ilan={ilan} />

      <Bolum baslik="Yorum özeti" testId="bolum-yorum">
        {yorum === null ? (
          <p className="text-xs text-ink-3">Değerlendirme verisi yok.</p>
        ) : (
          <>
            <Satir etiket="Değerlendirme" deger={yorum.adet === null ? '—' : count(yorum.adet)} />
            <Satir etiket="Puan" deger={yorum.puan ?? '—'} />
          </>
        )}
      </Bolum>

      <KaynakBolumu ilan={ilan} />

      <Bolum baslik="Yurt içi kıyas" testId="bolum-yurtici">
        {/* Vaat vermiyoruz: bu verinin kaynağı henüz YOK (V3-C kapsamı). */}
        <p className="text-xs text-ink-3">
          Yurt içi fiyat kıyası için bir veri kaynağı bağlı değil; bu bölüm boş kalır.
        </p>
      </Bolum>

      <Bolum baslik="Not" testId="bolum-not">
        <p className="whitespace-pre-wrap text-sm text-ink-2">{urun.note ?? '—'}</p>
      </Bolum>
    </>
  );
}

interface GaleriKaresi {
  adres: string;
  uzak: boolean;
}

function Galeri({ urun }: { urun: Product }) {
  const gorseller: GaleriKaresi[] = [
    ...(typeof urun.main_image === 'string' && urun.main_image !== ''
      ? [{ adres: urun.main_image, uzak: urun.main_image.startsWith('http') }]
      : []),
    ...urun.images
      .filter((gorsel) => gorsel.url !== '')
      .map((gorsel) => ({ adres: gorsel.url, uzak: gorsel.uzak === true })),
  ];
  const [secili, setSecili] = useState(0);
  // D11a: yüklenemeyen görsel SESSİZ KALMAZ; kare "yüklenemedi" der.
  const [hatali, setHatali] = useState<Record<string, boolean>>({});
  const gosterilen = gorseller[secili] ?? gorseller[0] ?? null;

  if (gosterilen === null) {
    return (
      <div className="flex h-40 items-center justify-center rounded-xl bg-g100 text-sm text-ink-3" data-testid="galeri-bos">
        Görsel yok
      </div>
    );
  }

  const bekleyen = gorseller.filter((kare) => kare.uzak).length;

  return (
    <div data-testid="galeri">
      {hatali[gosterilen.adres] === true ? (
        <div
          className="flex h-48 items-center justify-center rounded-xl border border-line bg-g100 text-sm text-ink-3"
          data-testid="galeri-hatali"
        >
          Görsel yüklenemedi
        </div>
      ) : (
        <img
          src={gosterilen.adres}
          alt=""
          onError={() => setHatali((onceki) => ({ ...onceki, [gosterilen.adres]: true }))}
          className="h-48 w-full rounded-xl border border-line object-contain"
        />
      )}
      {gorseller.length > 1 ? (
        <div className="mt-2 flex gap-2 overflow-x-auto">
          {gorseller.map((kare, sira) => (
            <button
              key={kare.adres}
              type="button"
              onClick={() => setSecili(sira)}
              aria-label={`Görsel ${sira + 1}${kare.uzak ? ' (arşive alınıyor)' : ''}`}
              aria-pressed={sira === secili}
              data-uzak={kare.uzak ? 'evet' : undefined}
              className={`relative h-12 w-12 shrink-0 overflow-hidden rounded-lg border ${
                sira === secili ? 'border-navy ring-2 ring-navy/20' : 'border-line'
              }`}
            >
              {hatali[kare.adres] === true ? (
                <span className="flex h-full w-full items-center justify-center bg-g100 text-[10px] text-ink-3">
                  yok
                </span>
              ) : (
                <img
                  src={kare.adres}
                  alt=""
                  onError={() => setHatali((onceki) => ({ ...onceki, [kare.adres]: true }))}
                  className="h-full w-full object-cover"
                />
              )}
            </button>
          ))}
        </div>
      ) : null}
      <p className="mt-1 text-xs text-ink-3">
        {count(gorseller.length)} görsel{urun.video_url ? ' · video var' : ' · video yok'}
      </p>
      {bekleyen > 0 ? (
        <p className="mt-1 text-xs text-warn" data-testid="galeri-uzak">
          {count(bekleyen)} görsel henüz arşive alınmadı — kaynak siteden gösteriliyor, birkaç
          dakika içinde indirilecek.
        </p>
      ) : null}
    </div>
  );
}

function Eksikler({ urun }: { urun: Product }) {
  const eksik = eksikEtiketleri(urun);

  return (
    <Bolum baslik="Ürün sağlığı" testId="bolum-saglik">
      {eksik.length === 0 ? (
        <p className="text-sm text-ok" data-testid="saglik-tam">
          {urun.hazir ? 'HAZIR işaretli — eksik alan yok.' : 'Eksik alan yok; HAZIR işaretlenebilir.'}
        </p>
      ) : (
        <ul className="space-y-1" data-testid="saglik-eksikler">
          {eksik.map((etiket) => (
            <li key={etiket} className="text-sm text-warn">
              • {etiket} eksik
            </li>
          ))}
        </ul>
      )}
    </Bolum>
  );
}

function Varyasyonlar({ urun }: { urun: Product }) {
  const secim = urun.sku_selection;
  const matris = urun.sku_matrix;
  const secimGirdileri =
    secim !== null && typeof secim === 'object' && !Array.isArray(secim)
      ? Object.entries(secim as Record<string, unknown>)
      : [];
  const matrisAdedi = Array.isArray(matris) ? matris.length : 0;

  return (
    <Bolum baslik="Varyasyonlar" testId="bolum-varyasyon">
      {secimGirdileri.length === 0 ? (
        <p className="text-xs text-ink-3">Varyant seçimi yapılmamış.</p>
      ) : (
        <ul className="space-y-1" data-testid="secili-varyant">
          {secimGirdileri.map(([ad, deger]) => (
            <li key={ad} className="flex justify-between text-sm">
              <span className="text-ink-3">{ad}</span>
              <span className="font-medium">{String(deger)}</span>
            </li>
          ))}
        </ul>
      )}
      <p className="mt-1 text-xs text-ink-3">
        {matrisAdedi > 0 ? `İlanda ${count(matrisAdedi)} varyant kayıtlı.` : 'İlan varyant listesi taşımıyor.'}
      </p>
    </Bolum>
  );
}

function SkorBolumu({ ilan }: { ilan: IlanGorunumu | null }) {
  return (
    <Bolum baslik="Tedarik puanı" testId="bolum-skor">
      {ilan === null || ilan.skor === null ? (
        <p className="text-xs text-ink-3" data-testid="skor-yok">
          Hesaplanamadı — ilan sinyalleri (satış, değerlendirme, satıcı karnesi) eksik.
        </p>
      ) : (
        <>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-semibold text-ink">{count(ilan.skor)}</span>
            <span className="badge bg-g50 text-ink-2 ring-line">{ilan.bant}</span>
          </div>
          {ilan.skor_bilesenleri ? (
            <ul className="mt-2 space-y-1" data-testid="skor-bilesenleri">
              {Object.entries(ilan.skor_bilesenleri).map(([ad, deger]) => (
                <li key={ad} className="flex justify-between text-xs">
                  <span className="text-ink-3">{ad}</span>
                  <span>{count(deger)}</span>
                </li>
              ))}
            </ul>
          ) : null}
        </>
      )}
    </Bolum>
  );
}

function KaynakBolumu({ ilan }: { ilan: IlanGorunumu | null }) {
  return (
    <Bolum baslik="Kaynak ve satıcı" testId="bolum-kaynak">
      {ilan === null ? (
        <p className="text-xs text-ink-3" data-testid="ilan-yok">
          Bu ürün elle eklenmiş; bağlı bir ilan kaydı yok.
        </p>
      ) : (
        <>
          <Satir etiket="Platform" deger={ilan.platform ?? '—'} />
          <Satir etiket="İlan no" deger={ilan.external_id ?? '—'} />
          <Satir etiket="Satıcı" deger={ilan.satici_ad ?? '—'} />
          <Satir etiket="Mağaza yılı" deger={ilan.satici_yil === null ? '—' : `${count(ilan.satici_yil)} yıl`} />
          <Satir etiket="Satıcı puanı" deger={ilan.satici_puan ?? '—'} />
          <Satir etiket="Yanıt oranı" deger={ilan.yanit_orani === null ? '—' : `%${ilan.yanit_orani}`} />
          <Satir etiket="MOQ" deger={ilan.moq === null ? '—' : count(ilan.moq)} />
          {ilan.url ? (
            <a
              href={ilan.url}
              target="_blank"
              rel="noreferrer noopener"
              className="mt-2 inline-flex items-center gap-1 text-sm font-medium text-navy"
              data-testid="ilan-adresi"
            >
              İlanı aç
              <ExternalLink className="h-3.5 w-3.5" aria-hidden />
            </a>
          ) : null}
        </>
      )}
    </Bolum>
  );
}

function Bolum({ baslik, testId, children }: { baslik: string; testId: string; children: React.ReactNode }) {
  return (
    <section className="rounded-xl border border-line p-3" data-testid={testId}>
      <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-3">{baslik}</h3>
      {children}
    </section>
  );
}

function Satir({ etiket, deger }: { etiket: string; deger: string }) {
  return (
    <div className="flex justify-between gap-3 text-sm">
      <span className="shrink-0 text-ink-3">{etiket}</span>
      <span className="truncate text-right font-medium">{deger}</span>
    </div>
  );
}
