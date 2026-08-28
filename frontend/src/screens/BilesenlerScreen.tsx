import { useState } from 'react';
import { Download, Plus, Trash2 } from 'lucide-react';
import {
  Adimlar,
  Cekmece,
  Chip,
  ConfirmBar,
  Dugme,
  EmptyState,
  ErrorNote,
  Field,
  Hucre,
  Ipucu,
  Modal,
  PageHeader,
  Popover,
  Satir,
  Sayfalama,
  Sekmeler,
  Skeleton,
  SoonBadge,
  Spinner,
  Tablo,
  TabloBaslik,
  TabloGovde,
  BaslikHucre,
  siralamaCevir,
  type Siralama,
  type Yogunluk,
} from '../components/ui';
import IslemDurumu from '../components/IslemDurumu';
import { useUzunIslem } from '../lib/useUzunIslem';
import { useToast } from '../components/Toast';
import { temaEtiketleri, useTema } from '../lib/tema';

/**
 * BİLEŞEN KİTAPLIĞI ÖRNEK SAYFASI (İE#16 D1.3).
 *
 * Amaç iki tanedir: (1) bir parçanın nasıl göründüğünü ve davrandığını tek
 * yerde görmek, (2) TEMA DENETİMİ — koyu temaya geçince bütün parçalar burada
 * bir arada sınanır; bir bileşende sabit renk unutulmuşsa burada göze çarpar.
 *
 * Menüde yer almaz; komut paletinden ("Bileşen kitaplığı") ve /bilesenler
 * adresinden açılır — geliştirme aracıdır, günlük akışın parçası değildir.
 */
export default function BilesenlerScreen() {
  const tema = useTema();
  const push = useToast((state) => state.push);
  const islem = useUzunIslem();

  const [sekme, setSekme] = useState('dugmeler');
  const [modal, setModal] = useState(false);
  const [cekmece, setCekmece] = useState(false);
  const [popover, setPopover] = useState(false);
  const [onay, setOnay] = useState(false);
  const [yogunluk, setYogunluk] = useState<Yogunluk>('ferah');
  const [siralama, setSiralama] = useState<Siralama | null>({ alan: 'ad', yon: 'artan' });
  const [sayfa, setSayfa] = useState(1);
  const [cipler, setCipler] = useState(['platform: 1688', 'skor > 70']);

  const ornekSatirlar = [
    { ad: 'Termos Yemek Kabı', platform: '1688', skor: 87, fiyat: '12,00' },
    { ad: 'Katlanır Bisiklet', platform: '1688', skor: 91, fiyat: '168,00' },
    { ad: 'Sensörlü Çöp Kovası', platform: 'Alibaba', skor: 79, fiyat: '18,90' },
  ];

  return (
    <>
      <PageHeader
        title="Bileşen kitaplığı"
        subtitle={`Tasarım sistemi parçaları — aktif tema: ${temaEtiketleri[tema]}`}
        actions={<SoonBadge>geliştirme aracı</SoonBadge>}
      />

      <Sekmeler
        sekmeler={[
          { deger: 'dugmeler', etiket: 'Düğmeler ve form' },
          { deger: 'veri', etiket: 'Veri gösterimi' },
          { deger: 'katman', etiket: 'Katmanlar' },
          { deger: 'durum', etiket: 'Durumlar' },
        ]}
        aktif={sekme}
        onDegis={setSekme}
      />

      {sekme === 'dugmeler' && (
        <div className="space-y-4">
          <Bolum baslik="Düğme türleri">
            <div className="flex flex-wrap items-center gap-2">
              <Dugme tur="birincil" simge={<Plus size={16} />}>
                Birincil
              </Dugme>
              <Dugme tur="ikincil">İkincil</Dugme>
              <Dugme tur="ghost" simge={<Download size={16} />}>
                Ghost
              </Dugme>
              <Dugme tur="tehlikeli" simge={<Trash2 size={16} />}>
                Tehlikeli
              </Dugme>
              <Dugme tur="birincil" yukleniyor>
                Yükleniyor
              </Dugme>
              <Dugme tur="ikincil" disabled>
                Kapalı
              </Dugme>
              <Dugme tur="ikincil" kucuk>
                Küçük
              </Dugme>
            </div>
          </Bolum>

          <Bolum baslik="Form alanları">
            <div className="grid gap-3 sm:grid-cols-2">
              <Field label="Ürün adı" hint="Türkçe ad; öneri kabul edilirse buraya yazılır">
                <input className="field-input" defaultValue="Termos Yemek Kabı" />
              </Field>
              <Field label="Kategori">
                <select className="field-input">
                  <option>Mutfak</option>
                  <option>Ev</option>
                </select>
              </Field>
              <Field label="Adet" error="Adet 1 ile 100000 arasında olmalı">
                <input className="field-input" defaultValue="0" />
              </Field>
              <Field label="Not">
                <textarea className="field-input min-h-20 py-2" defaultValue="Kutu logolu olacak" />
              </Field>
            </div>
          </Bolum>

          <Bolum baslik="Çipler (seçili filtreler)">
            <div className="flex flex-wrap gap-2">
              {cipler.map((cip) => (
                <Chip key={cip} onRemove={() => setCipler((mevcut) => mevcut.filter((x) => x !== cip))}>
                  {cip}
                </Chip>
              ))}
              {cipler.length === 0 && <span className="text-md text-ink-3">Filtre yok.</span>}
            </div>
          </Bolum>

          <Bolum baslik="Rozetler ve ipucu">
            <div className="flex flex-wrap items-center gap-2">
              <span className="badge bg-ok-soft text-ok ring-ok/20">Geldi</span>
              <span className="badge bg-warn-soft text-warn ring-warn/20">Yolda</span>
              <span className="badge bg-err-soft text-err ring-err/20">İptal</span>
              <span className="badge bg-info-soft text-info ring-info/20">Bilgi</span>
              <span className="badge bg-plat-1688-soft text-plat-1688 ring-transparent">1688</span>
              <SoonBadge />
              <Ipucu metin="Kritik bilgi ipucuna saklanmaz — bu yalnız açıklamadır.">
                <span className="cursor-help text-md text-ink-3 underline decoration-dotted">ipucu örneği</span>
              </Ipucu>
            </div>
          </Bolum>
        </div>
      )}

      {sekme === 'veri' && (
        <div className="space-y-4">
          <Bolum
            baslik="Tablo"
            sag={
              <div className="flex gap-1">
                <Dugme kucuk tur={yogunluk === 'ferah' ? 'birincil' : 'ghost'} onClick={() => setYogunluk('ferah')}>
                  Ferah
                </Dugme>
                <Dugme kucuk tur={yogunluk === 'sik' ? 'birincil' : 'ghost'} onClick={() => setYogunluk('sik')}>
                  Sıkı
                </Dugme>
              </div>
            }
          >
            <Tablo yogunluk={yogunluk}>
              <TabloBaslik>
                <tr>
                  <BaslikHucre alan="ad" siralama={siralama} onSirala={(alan) => setSiralama(siralamaCevir(siralama, alan))}>
                    Ürün
                  </BaslikHucre>
                  <BaslikHucre>Platform</BaslikHucre>
                  <BaslikHucre
                    alan="skor"
                    siralama={siralama}
                    onSirala={(alan) => setSiralama(siralamaCevir(siralama, alan))}
                    sagaHizali
                  >
                    Puan
                  </BaslikHucre>
                  <BaslikHucre sagaHizali>Birim ¥</BaslikHucre>
                </tr>
              </TabloBaslik>
              <TabloGovde>
                {ornekSatirlar.map((satir) => (
                  <Satir key={satir.ad} onClick={() => setCekmece(true)}>
                    <Hucre>{satir.ad}</Hucre>
                    <Hucre>
                      <span className="badge bg-plat-1688-soft text-plat-1688 ring-transparent">{satir.platform}</span>
                    </Hucre>
                    <Hucre sagaHizali>{satir.skor}</Hucre>
                    <Hucre sagaHizali>{satir.fiyat}</Hucre>
                  </Satir>
                ))}
              </TabloGovde>
            </Tablo>
            <Sayfalama sayfa={sayfa} sonMu={sayfa >= 3} onDegis={setSayfa} toplam={62} />
          </Bolum>

          <Bolum baslik="Adım göstergesi">
            <Adimlar adimlar={['Yakala', 'Elemeden geçir', 'Listeye al', 'Teklif']} aktif={1} />
          </Bolum>
        </div>
      )}

      {sekme === 'katman' && (
        <div className="space-y-4">
          <Bolum baslik="Modal · çekmece · popover">
            <div className="relative flex flex-wrap gap-2">
              <Dugme onClick={() => setModal(true)}>Modal aç</Dugme>
              <Dugme onClick={() => setCekmece(true)}>Çekmece aç</Dugme>
              <span className="relative">
                <Dugme onClick={() => setPopover((x) => !x)}>Popover</Dugme>
                <Popover acik={popover} onKapat={() => setPopover(false)}>
                  <p className="text-md text-ink-2">
                    Filtre paneli bu katmanda durur: arka plan kilitlenmez, sayfa kullanılabilir kalır.
                  </p>
                </Popover>
              </span>
            </div>
          </Bolum>

          <Bolum baslik="Bildirim ve onay">
            <div className="flex flex-wrap gap-2">
              <Dugme onClick={() => push('Kaydedildi.')}>Toast</Dugme>
              <Dugme
                tur="tehlikeli"
                onClick={() =>
                  push('Ürün silindi.', 'success', () => push('Silme geri alındı.'))
                }
              >
                Geri alınabilir işlem
              </Dugme>
              <Dugme tur="ikincil" onClick={() => setOnay((x) => !x)}>
                Onay çubuğu
              </Dugme>
            </div>
            {onay && (
              <div className="mt-3">
                <ConfirmBar
                  question="3 ürün kalıcı olarak silinecek. Bu işlem geri alınamaz."
                  confirmLabel="Kalıcı sil"
                  onConfirm={() => setOnay(false)}
                  onCancel={() => setOnay(false)}
                />
              </div>
            )}
          </Bolum>

          <Bolum baslik="Uzun işlem deseni (D1.8)">
            <Dugme
              tur="birincil"
              yukleniyor={islem.calisiyor}
              onClick={() =>
                void islem.baslat(async (rapor) => {
                  for (let adim = 1; adim <= 3; adim++) {
                    await new Promise((coz) => setTimeout(coz, 500));
                    rapor(`${adim}/3 parti işlendi`);
                  }

                  return '3 parti işlendi · 0 başarısız.';
                })
              }
            >
              Örnek işlemi başlat
            </Dugme>
            <IslemDurumu islem={islem} fiil="Örnek işlem çalışıyor" />
          </Bolum>
        </div>
      )}

      {sekme === 'durum' && (
        <div className="space-y-4">
          <Bolum baslik="Yükleniyor">
            <Skeleton rows={2} />
            <Spinner />
          </Bolum>
          <Bolum baslik="Hata">
            <ErrorNote message="Sunucuya ulaşılamadı." onRetry={() => push('Yeniden denendi.')} />
          </Bolum>
          <Bolum baslik="Boş durum">
            <EmptyState
              title="Kayıt yok"
              description="Bu süzgeçle eşleşen bir kayıt bulunamadı. Filtreleri gevşetmeyi deneyin."
              action={<Dugme tur="birincil">Filtreleri temizle</Dugme>}
            />
          </Bolum>
        </div>
      )}

      <Modal
        acik={modal}
        baslik="Örnek modal"
        onKapat={() => setModal(false)}
        eylemler={
          <>
            <Dugme onClick={() => setModal(false)}>Vazgeç</Dugme>
            <Dugme tur="birincil" onClick={() => setModal(false)}>
              Kaydet
            </Dugme>
          </>
        }
      >
        <p className="text-md text-ink-2">
          Esc kapatır, dışarı tıklamak kapatır; kapanınca odak çağıran düğmeye geri döner.
        </p>
      </Modal>

      <Cekmece
        acik={cekmece}
        baslik="Termos Yemek Kabı"
        onKapat={() => setCekmece(false)}
        altBar={
          <div className="flex gap-2">
            <Dugme tur="birincil" className="flex-1">
              Listeye ekle
            </Dugme>
            <Dugme>İzle</Dugme>
          </div>
        }
      >
        <p className="mb-3 text-md text-ink-2">
          Sağ çekmece ayrıntı okuma yüzeyidir: sayfa arkada görünür kalır, kullanıcı listedeki yerini kaybetmez.
        </p>
        <div className="rounded-lg border border-line p-3">
          <div className="mb-1 text-xs font-bold tracking-wide text-ink-3">TEDARİK PUANI</div>
          <div className="flex items-center gap-3">
            <span className="text-2xl font-bold tabular-nums text-ink">87</span>
            <span className="h-2 flex-1 overflow-hidden rounded-full bg-g100">
              <span className="block h-full w-[87%] rounded-full bg-gold" />
            </span>
          </div>
        </div>
      </Cekmece>
    </>
  );
}

function Bolum({ baslik, sag, children }: { baslik: string; sag?: React.ReactNode; children: React.ReactNode }) {
  return (
    <section className="card p-4">
      <div className="mb-3 flex items-center justify-between gap-3">
        <h2 className="text-md font-semibold text-ink">{baslik}</h2>
        {sag}
      </div>
      {children}
    </section>
  );
}
