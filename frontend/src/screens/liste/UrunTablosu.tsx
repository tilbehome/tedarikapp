import { Trash2 } from 'lucide-react';
import { urunAdi } from '../../lib/urunAdi';
import type { Product, ProductStatus, SupplyList } from '../../api/types';
import { count, money } from '../../lib/format';
import { productStatusLabels } from '../../locales/tr';
import StatusMenu from '../../components/StatusMenu';
import MiktarHucresi from './MiktarHucresi';
import { hucreSinifi, type SutunAnahtari, type TabloTercihi } from '../../lib/tabloTercihi';
import { eksikEtiketleri } from '../../lib/eksikler';

/**
 * ÜRÜN TABLOSU — masaüstü görünümü (İE#21 B2 · referans: `liste-ici.png`).
 *
 * NEDEN AYRI DOSYA: tablo artık üç eksende değişiyor (görünür sütunlar, satır
 * yoğunluğu, gruplama) ve bunların her biri başlık, gövde ve TOPLAM satırını
 * birlikte etkiliyor. Üçünü tek bir ekran bileşeninin içinde tutmak, `<td>`
 * sırasını elle saymak demekti — bir sütun gizlendiğinde TOPLAM satırı kayardı.
 *
 * ÇÖZÜM: sütunlar VERİ olarak tanımlanır (`SUTUN_TANIMLARI`); başlık, hücre ve
 * toplam aynı tanımdan üretilir. Böylece hiza tek yerde durur ve yeni bir sütun
 * eklemek üç ayrı yeri güncellemeyi gerektirmez.
 *
 * TOPLAM SATIRI BACKEND'DEN GELİR (K14/K29): panel hiçbir para değeri toplamaz.
 * Sütun gizlenirse toplamı da gizlenir — ekranda karşılığı olmayan bir toplam,
 * kullanıcının yanlış sütuna bakmasına yol açar.
 */

export interface TabloEylemleri {
  onDurum: (urun: Product, hedef: ProductStatus) => void;
  onMiktar: (urun: Product, yeni: number) => Promise<void>;
  onHazir: (urun: Product) => void;
  onSil: (urun: Product) => void;
}

interface Props {
  liste: SupplyList;
  urunler: Product[];
  tercih: TabloTercihi;
  secili: number[];
  mesgulId: number | null;
  kategoriAdi: (id: number | null) => string;
  gorsel: (urun: Product) => React.ReactNode;
  siralamaBasligi: (anahtar: 'name' | 'qty' | 'price_yuan' | 'line_total_yuan_tl' | 'status', etiket: string, saga?: boolean) => React.ReactNode;
  eylemler: TabloEylemleri;
  onSecili: (ids: number[]) => void;
  /** Ürüne tıklayınca sağ çekmece açılır (İE#21 B3 · referans notu). */
  onAc: (urun: Product) => void;
}

/** Sütun tanımı: başlık, hücre ve toplam TEK yerden. */
interface SutunTanimi {
  anahtar: SutunAnahtari;
  baslik: React.ReactNode;
  saga?: boolean;
  hucre: (urun: Product, ctx: Props) => React.ReactNode;
  toplam?: (liste: SupplyList) => React.ReactNode;
}

export default function UrunTablosu(props: Props) {
  const { urunler, tercih, secili, kategoriAdi, siralamaBasligi, eylemler, onSecili, onAc } = props;
  const hucre = hucreSinifi(tercih.yogunluk);
  const sutunlar = SUTUN_TANIMLARI(siralamaBasligi).filter((sutun) => tercih.sutunlar.includes(sutun.anahtar));
  // Seçim + ürün + satır sonu sil düğmesi her zaman durur; sayım TOPLAM satırının
  // birleştirilmiş ilk hücresi için gerekir.
  const sabitSol = 2;

  const gruplar = grupla(urunler, tercih.grupla, kategoriAdi);

  return (
    <div className="card hidden md:block">
      <div className="table-scroll">
        <table className="w-full text-sm" data-testid="urun-tablosu" data-yogunluk={tercih.yogunluk}>
          <thead className="border-b border-line text-left text-xs uppercase tracking-wide text-ink-3">
            <tr>
              <th className="w-10 px-3 py-3">
                <input
                  type="checkbox"
                  aria-label="Tümünü seç"
                  className="h-4 w-4"
                  checked={secili.length === urunler.length && urunler.length > 0}
                  onChange={(olay) => onSecili(olay.target.checked ? urunler.map((urun) => urun.id) : [])}
                />
              </th>
              {/* Ürün sütunu KAPATILAMAZ: adı olmayan bir satır okunamaz. */}
              <th className="px-3 py-3 text-left">{siralamaBasligi('name', 'Ürün')}</th>
              {sutunlar.map((sutun) => (
                <th key={sutun.anahtar} className={`px-3 py-3 ${sutun.saga ? 'text-right' : 'text-left'}`}>
                  {sutun.baslik}
                </th>
              ))}
              <th className="w-12 px-3 py-3" />
            </tr>
          </thead>

          {gruplar.map((grup) => (
            <tbody key={grup.ad} className="divide-y divide-line-soft">
              {grup.baslikli ? (
                <tr className="bg-g50" data-testid="grup-basligi">
                  <td className="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-ink-2" colSpan={sutunlar.length + sabitSol}>
                    {grup.ad} · {count(grup.urunler.length)} ürün
                  </td>
                </tr>
              ) : null}

              {grup.urunler.map((urun) => (
                <tr key={urun.id} className="hover:bg-g50" data-testid="urun-satiri">
                  <td className={hucre}>
                    <input
                      type="checkbox"
                      className="h-4 w-4"
                      aria-label={`${urunAdi(urun)} seç`}
                      checked={secili.includes(urun.id)}
                      onChange={(olay) =>
                        onSecili(
                          olay.target.checked
                            ? [...secili, urun.id]
                            : secili.filter((deger) => deger !== urun.id),
                        )
                      }
                    />
                  </td>
                  <td className={`max-w-xs ${hucre}`}>
                    {/* Tıklama çekmeceyi açar: liste ekranından çıkmadan ürünün
                        tüm hikâyesi görünür. Tam düzenleme çekmecenin içindeki
                        "Düzenle" ile açılır. */}
                    <button
                      type="button"
                      className="block max-w-full truncate text-left font-medium hover:text-navy"
                      onClick={() => onAc(urun)}
                      data-testid="urun-adi"
                    >
                      {urunAdi(urun)}
                    </button>
                    {urun.detail ? <span className="block truncate text-xs text-ink-3">{urun.detail}</span> : null}
                    <SatirUyarilari urun={urun} />
                  </td>
                  {sutunlar.map((sutun) => (
                    <td key={sutun.anahtar} className={`${hucre} ${sutun.saga ? 'text-right' : ''}`}>
                      {sutun.hucre(urun, props)}
                    </td>
                  ))}
                  <td className={hucre}>
                    <button
                      type="button"
                      className="inline-flex h-11 w-11 items-center justify-center rounded-xl text-err"
                      aria-label="Ürünü sil"
                      onClick={() => eylemler.onSil(urun)}
                    >
                      <Trash2 className="h-4 w-4" aria-hidden />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          ))}

          <tfoot className="border-t-2 border-line bg-g50 font-semibold">
            <tr>
              <td className="px-3 py-3" colSpan={sabitSol}>
                TOPLAM
              </td>
              {sutunlar.map((sutun) => (
                <td key={sutun.anahtar} className={`px-3 py-3 ${sutun.saga ? 'text-right' : ''}`}>
                  {sutun.toplam ? sutun.toplam(props.liste) : null}
                </td>
              ))}
              <td className="px-3 py-3" />
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  );
}

/**
 * SATIR UYARILARI — hangi ürünün nesi eksik (C8).
 *
 * Üstteki çipler "kaç üründe" der; buradaki rozetler "bu üründe ne" der. İkisi
 * de sunucunun `hazir_eksikleri` dökümünden gelir, yoksa kullanıcı iki ayrı
 * sorun olduğunu sanar.
 */
function SatirUyarilari({ urun }: { urun: Product }) {
  const eksik = eksikEtiketleri(urun);
  if (eksik.length === 0) return null;

  return (
    <span className="mt-0.5 flex flex-wrap gap-1" data-testid="satir-uyarilari">
      {eksik.map((etiket) => (
        <span key={etiket} className="badge bg-warn-soft text-warn ring-warn/20">
          {etiket} yok
        </span>
      ))}
    </span>
  );
}

/**
 * HAZIR DÜĞMESİ (C8 kalite kapısı).
 *
 * Kapının kararı sunucudadır: eksik alan varsa uç 422 ile reddeder. Düğme bu
 * yüzden eksik üründe KAPATILMAZ — kullanıcı basar, gerekçeyi okur. Kapatsaydık
 * "neden basamıyorum?" sorusu cevapsız kalırdı.
 */
function HazirDugmesi({ urun, mesgul, onDegistir }: { urun: Product; mesgul: boolean; onDegistir: () => void }) {
  const eksik = eksikEtiketleri(urun);

  return (
    <button
      type="button"
      disabled={mesgul}
      onClick={onDegistir}
      aria-pressed={urun.hazir}
      data-testid="hazir-dugmesi"
      title={eksik.length > 0 ? `Eksik: ${eksik.join(' · ')}` : undefined}
      className={`badge ${urun.hazir ? 'bg-ok-soft text-ok ring-ok/20' : 'bg-g50 text-ink-3 ring-line'}`}
    >
      {urun.hazir ? 'HAZIR' : eksik.length > 0 ? `${count(eksik.length)} eksik` : 'İşaretle'}
    </button>
  );
}

function SUTUN_TANIMLARI(siralamaBasligi: Props['siralamaBasligi']): SutunTanimi[] {
  return [
    {
      anahtar: 'gorsel',
      baslik: 'Görsel',
      hucre: (urun, ctx) => ctx.gorsel(urun),
    },
    {
      anahtar: 'kategori',
      baslik: 'Kategori',
      hucre: (urun, ctx) => <span className="text-ink-2">{ctx.kategoriAdi(urun.category_id)}</span>,
    },
    {
      anahtar: 'adet',
      baslik: siralamaBasligi('qty', 'Miktar', true),
      saga: true,
      hucre: (urun, ctx) => (
        <MiktarHucresi
          deger={urun.qty}
          etiket={urunAdi(urun)}
          kapali={ctx.liste.status === 'completed' || ctx.liste.status === 'cancelled'}
          onKaydet={(yeni) => ctx.eylemler.onMiktar(urun, yeni)}
        />
      ),
      toplam: (liste) => count(liste.totals.qty),
    },
    {
      anahtar: 'birim_yuan',
      baslik: siralamaBasligi('price_yuan', '¥ Birim', true),
      saga: true,
      hucre: (urun) => `¥${money(urun.price_yuan)}`,
    },
    {
      anahtar: 'satir_yuan',
      baslik: '¥ Satır',
      saga: true,
      hucre: (urun) => `¥${money(urun.line_total_yuan)}`,
      toplam: (liste) => `¥${money(liste.totals.yuan)}`,
    },
    {
      anahtar: 'birim_tl',
      baslik: '₺ Birim',
      saga: true,
      hucre: (urun) => `₺${money(urun.price_yuan_tl)}`,
    },
    {
      anahtar: 'ddp_usd',
      baslik: '$ DDP',
      saga: true,
      hucre: (urun) => `$${money(urun.price_ddp_usd)}`,
      toplam: (liste) => `$${money(liste.totals.ddp_usd)}`,
    },
    {
      anahtar: 'satir_tl',
      baslik: siralamaBasligi('line_total_yuan_tl', '₺ Satır', true),
      saga: true,
      hucre: (urun) => <span className="font-semibold">₺{money(urun.line_total_yuan_tl)}</span>,
      toplam: (liste) => `₺${money(liste.totals.yuan_tl)}`,
    },
    {
      anahtar: 'durum',
      baslik: siralamaBasligi('status', 'Durum'),
      hucre: (urun, ctx) => (
        <StatusMenu
          status={urun.status}
          busy={ctx.mesgulId === urun.id}
          onChange={(hedef) => ctx.eylemler.onDurum(urun, hedef)}
        />
      ),
    },
    {
      anahtar: 'hazir',
      baslik: 'Hazır',
      hucre: (urun, ctx) => (
        <HazirDugmesi
          urun={urun}
          mesgul={ctx.mesgulId === urun.id}
          onDegistir={() => ctx.eylemler.onHazir(urun)}
        />
      ),
    },
  ];
}

interface Grup {
  ad: string;
  baslikli: boolean;
  urunler: Product[];
}

/**
 * Gruplama: sıralamayı BOZMADAN grup başlıkları ekler.
 *
 * Ürünlerin kendi sırası korunur (kullanıcının seçtiği sıralama geçerlidir);
 * yalnız aynı gruptakiler bir araya toplanır. Grup içinde yeniden sıralamak,
 * kullanıcının seçtiği sıralamayı sessizce ezmek olurdu.
 */
function grupla(urunler: Product[], mod: TabloTercihi['grupla'], kategoriAdi: (id: number | null) => string): Grup[] {
  if (mod === 'yok') {
    return [{ ad: 'tumu', baslikli: false, urunler }];
  }

  const kova = new Map<string, Product[]>();
  for (const urun of urunler) {
    const ad = mod === 'kategori' ? kategoriAdi(urun.category_id) : productStatusLabels[urun.status];
    const mevcut = kova.get(ad);
    if (mevcut) {
      mevcut.push(urun);
    } else {
      kova.set(ad, [urun]);
    }
  }

  return [...kova.entries()]
    .sort((a, b) => a[0].localeCompare(b[0], 'tr'))
    .map(([ad, grupUrunleri]) => ({ ad, baslikli: true, urunler: grupUrunleri }));
}
