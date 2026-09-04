import { useState } from 'react';
import { Copy, Eye, EyeOff, Send } from 'lucide-react';
import { teklifler as tekliflerApi } from '../../api/endpoints';
import type { Firma, TeklifTuru, TurGonderimSonucu } from '../../api/types';
import { messageOf, useAsync } from '../../lib/useAsync';
import { dateTime } from '../../lib/format';
import { useToast } from '../../components/Toast';
import { ErrorNote, Skeleton } from '../../components/ui';

/**
 * LİSTE DETAYI › TEKLİF TURLARI (V3-C Aşama 2.1).
 *
 * Sahibin döngüsü tek bölümde: tur aç → firmaya gönder → onayla / revizyon
 * iste / vazgeç. Firma tarafı (portal) Blok C'de; burada firma geçişleri
 * yalnız GÖZLEM olarak görünür ("Açıldı / Açılmadı") — sahip yazamaz.
 *
 * K105 §2.6 (yıkıcı eylem): GÖNDER geri alınamaz — RFQ donar, kur kilitlenir,
 * link üretilir. Onay metni NE OLACAĞINI yazar; düğmede eylem adı durur.
 *
 * Bağlantı ile 6 haneli anahtar AYRI kutularda gösterilir ve aynı kanaldan
 * gitmemesi gerektiği yazılıdır (mesaj kalıpları §1-2): ikisi tek mesajda
 * giderse anahtarın anlamı kalmaz.
 */
interface Props {
  listId: number;
  listeKapali: boolean;
}

export default function TeklifTurlari({ listId, listeKapali }: Props) {
  const push = useToast((state) => state.push);
  const turlar = useAsync(() => tekliflerApi.listeninTurlari(listId), [listId]);
  const [yeniAcik, setYeniAcik] = useState(false);
  const [gonderimler, setGonderimler] = useState<Record<number, TurGonderimSonucu>>({});

  const yenile = () => turlar.reload();

  return (
    <section className="card mb-4 p-4" aria-label="Teklif turları" data-testid="teklif-turlari">
      <div className="mb-2 flex items-center justify-between gap-2">
        <h2 className="text-sm font-semibold text-ink-2">Teklif turları</h2>
        {!listeKapali ? (
          <button type="button" className="btn-ghost" onClick={() => setYeniAcik((v) => !v)}>
            Yeni tur aç
          </button>
        ) : null}
      </div>

      {yeniAcik && !listeKapali ? (
        <YeniTurFormu
          listId={listId}
          onAcildi={() => {
            setYeniAcik(false);
            yenile();
            push('Teklif turu taslak olarak açıldı.');
          }}
          onHata={(hata) => push(messageOf(hata), 'error')}
          onKapat={() => setYeniAcik(false)}
        />
      ) : null}

      {turlar.loading ? (
        <Skeleton rows={2} />
      ) : turlar.error ? (
        <ErrorNote message={turlar.error} onRetry={turlar.reload} />
      ) : (turlar.data ?? []).length === 0 ? (
        <p className="text-sm text-ink-3" data-testid="tur-yok">
          Bu liste için henüz teklif turu yok. Firmaya fiyat sormak için bir tur açın.
        </p>
      ) : (
        <ul className="divide-y divide-line-soft">
          {(turlar.data ?? []).map((tur) => (
            <TurSatiri
              key={tur.id}
              tur={tur}
              gonderim={gonderimler[tur.id]}
              onDegisti={yenile}
              onGonderildi={(sonuc) => {
                setGonderimler((onceki) => ({ ...onceki, [tur.id]: sonuc }));
                yenile();
              }}
              onHata={(hata) => push(messageOf(hata), 'error')}
              onBilgi={(metin) => push(metin)}
            />
          ))}
        </ul>
      )}
    </section>
  );
}

function YeniTurFormu({
  listId,
  onAcildi,
  onHata,
  onKapat,
}: {
  listId: number;
  onAcildi: () => void;
  onHata: (hata: unknown) => void;
  onKapat: () => void;
}) {
  const firmalar = useAsync(() => tekliflerApi.firmalar(), []);
  const [firmaId, setFirmaId] = useState<number | ''>('');
  const [yeniFirma, setYeniFirma] = useState('');
  const [gecerlilik, setGecerlilik] = useState('15');
  const [busy, setBusy] = useState(false);

  const ac = async () => {
    setBusy(true);
    try {
      let secili = firmaId;
      if (secili === '' && yeniFirma.trim() !== '') {
        const firma: Firma = await tekliflerApi.firmaOlustur({ ad: yeniFirma.trim() });
        secili = firma.id;
      }
      if (secili === '') return;
      await tekliflerApi.ac(listId, { firma_id: secili, gecerlilik_gun: Number(gecerlilik) || undefined });
      onAcildi();
    } catch (hata) {
      onHata(hata);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="mb-3 rounded-lg border border-line bg-surface-2 p-3 text-sm" data-testid="yeni-tur-formu">
      <div className="flex flex-wrap items-end gap-3">
        <label className="flex flex-col gap-1">
          <span className="text-xs text-ink-3">Firma</span>
          <select className="input" value={firmaId} onChange={(olay) => setFirmaId(olay.target.value === '' ? '' : Number(olay.target.value))}>
            <option value="">— seç ya da yeni yaz —</option>
            {(firmalar.data ?? []).map((firma) => (
              <option key={firma.id} value={firma.id}>
                {firma.ad}
              </option>
            ))}
          </select>
        </label>
        <label className="flex flex-col gap-1">
          <span className="text-xs text-ink-3">Yeni firma adı</span>
          <input className="input" value={yeniFirma} onChange={(olay) => setYeniFirma(olay.target.value)} placeholder="Yiwu … Co" disabled={firmaId !== ''} />
        </label>
        <label className="flex flex-col gap-1">
          <span className="text-xs text-ink-3">Geçerlilik (gün)</span>
          <input className="input w-24" inputMode="numeric" value={gecerlilik} onChange={(olay) => setGecerlilik(olay.target.value)} />
        </label>
        <button type="button" className="btn-primary" disabled={busy || (firmaId === '' && yeniFirma.trim() === '')} onClick={() => void ac()}>
          Turu aç
        </button>
        <button type="button" className="btn-ghost" onClick={onKapat}>
          Vazgeç
        </button>
      </div>
      <p className="mt-2 text-xs text-ink-3">
        Tur TASLAK olarak açılır; firma bir şey görmez. Gönderdiğinizde ürünler ve kur o tura kilitlenir.
      </p>
    </div>
  );
}

function TurSatiri({
  tur,
  gonderim,
  onDegisti,
  onGonderildi,
  onHata,
  onBilgi,
}: {
  tur: TeklifTuru;
  gonderim?: TurGonderimSonucu;
  onDegisti: () => void;
  onGonderildi: (sonuc: TurGonderimSonucu) => void;
  onHata: (hata: unknown) => void;
  onBilgi: (metin: string) => void;
}) {
  const [gonderOnay, setGonderOnay] = useState(false);
  const [revizyonAcik, setRevizyonAcik] = useState(false);
  const [sebep, setSebep] = useState('');
  const [busy, setBusy] = useState(false);

  const calistir = async (is: () => Promise<unknown>, mesaj: string) => {
    setBusy(true);
    try {
      await is();
      onBilgi(mesaj);
      onDegisti();
    } catch (hata) {
      onHata(hata);
    } finally {
      setBusy(false);
    }
  };

  const gonder = async () => {
    setBusy(true);
    try {
      const sonuc = await tekliflerApi.gonder(tur.id, { gecerlilik_gun: tur.gecerlilik_gun ?? undefined, kanal: 'panel' });
      setGonderOnay(false);
      onGonderildi(sonuc);
      // F1 (sessiz eylem yok): bağlantı ve anahtar aşağıda belirir, ama toast
      // da söyler — kullanıcı sayfanın altına bakmadan işin bittiğini bilmeli.
      onBilgi(`Tur firmaya gönderildi; ${sonuc.satir_sayisi} satır donduruldu. Bağlantı ve anahtar aşağıda.`);
    } catch (hata) {
      onHata(hata);
    } finally {
      setBusy(false);
    }
  };

  const kopyala = (metin: string, etiket: string) => {
    void navigator.clipboard?.writeText(metin);
    onBilgi(`${etiket} kopyalandı.`);
  };

  return (
    <li className="py-3 text-sm" data-testid={`tur-${tur.id}`}>
      <div className="flex flex-wrap items-center gap-3">
        <span className="font-medium text-ink">{tur.firma_adi}</span>
        <span className="badge bg-g100 text-ink-2 ring-line" title={tur.state}>
          {tur.etiket}
        </span>
        {tur.sent_at !== null && !tur.nihai ? (
          <span className={`inline-flex items-center gap-1 text-xs ${tur.goruntulendi ? 'text-ok' : 'text-warn'}`}>
            {tur.goruntulendi ? <Eye className="h-3.5 w-3.5" aria-hidden /> : <EyeOff className="h-3.5 w-3.5" aria-hidden />}
            {tur.goruntulendi ? 'Açıldı' : 'Açılmadı'}
          </span>
        ) : null}
        {tur.bekleme_gun !== null ? (
          <span className="text-xs text-ink-3">{tur.bekleme_gun === 0 ? 'bugün gönderildi' : `${tur.bekleme_gun} gündür bekliyor`}</span>
        ) : null}
        {tur.kur.deger ? (
          <span className="text-xs text-ink-3" title={tur.kur.kilit_at ? `Kilit: ${dateTime(tur.kur.kilit_at)}` : 'Gönderimde kilitlenir'}>
            ¥ {tur.kur.deger} ({tur.kur.kaynak})
          </span>
        ) : null}
        {tur.state_reason ? <span className="text-xs text-ink-3">· {tur.state_reason}</span> : null}

        <span className="ml-auto flex flex-wrap gap-2">
          {tur.state === 'DRAFT' ? (
            <>
              <button type="button" className="btn-primary" disabled={busy} onClick={() => setGonderOnay(true)}>
                <Send className="h-4 w-4" aria-hidden />
                Firmaya gönder
              </button>
              <button type="button" className="btn-ghost" disabled={busy} onClick={() => void calistir(() => tekliflerApi.vazgec(tur.id, {}), 'Turdan vazgeçildi.')}>
                Vazgeç
              </button>
            </>
          ) : null}
          {tur.state === 'RESPONDED' || tur.state === 'EXPIRED' ? (
            <>
              {tur.state === 'RESPONDED' ? (
                <button type="button" className="btn-primary" disabled={busy} onClick={() => void calistir(() => tekliflerApi.onayla(tur.id), 'Teklif onaylandı.')}>
                  Onayla
                </button>
              ) : null}
              <button type="button" className="btn-ghost" disabled={busy} onClick={() => setRevizyonAcik((v) => !v)}>
                Revizyon iste
              </button>
              <button type="button" className="btn-ghost" disabled={busy} onClick={() => void calistir(() => tekliflerApi.vazgec(tur.id, {}), 'Turdan vazgeçildi.')}>
                Vazgeç
              </button>
            </>
          ) : null}
          {tur.state === 'SENT' || tur.state === 'VIEWED' || tur.state === 'PRICING' ? (
            <button type="button" className="btn-ghost" disabled={busy} onClick={() => void calistir(() => tekliflerApi.vazgec(tur.id, {}), 'Turdan vazgeçildi; firmanın bağlantısı kapandı.')}>
              Vazgeç
            </button>
          ) : null}
        </span>
      </div>

      {gonderOnay ? (
        <div className="mt-2 rounded-lg border border-warn/40 bg-warn-soft p-3 text-xs text-ink-2" data-testid="gonder-onay">
          <p>
            Gönderince bu turun ürün listesi ve kuru <b>kilitlenir</b> (sonradan değiştirilemez), firma için bağlantı ve 6
            haneli anahtar üretilir. Listeyi sonradan değiştirirseniz firma eski hâlini görür; yeni fiyat için revizyon
            turu açılır.
          </p>
          <div className="mt-2 flex gap-2">
            <button type="button" className="btn-primary" disabled={busy} onClick={() => void gonder()}>
              Gönder
            </button>
            <button type="button" className="btn-ghost" onClick={() => setGonderOnay(false)}>
              Vazgeç
            </button>
          </div>
        </div>
      ) : null}

      {gonderim ? (
        <div className="mt-2 grid gap-2 rounded-lg border border-line bg-surface-2 p-3 text-xs sm:grid-cols-2">
          <div data-testid="tur-baglanti">
            <div className="mb-1 text-ink-3">Bağlantı (firmaya gönderin)</div>
            <code className="block break-all">{gonderim.share_url}</code>
            <button type="button" className="btn-ghost mt-1 !min-h-7 !px-2 !text-xs" onClick={() => kopyala(gonderim.share_url, 'Bağlantı')}>
              <Copy className="h-3 w-3" aria-hidden /> Kopyala
            </button>
          </div>
          <div data-testid="tur-anahtar">
            <div className="mb-1 text-ink-3">Erişim anahtarı (AYRI mesajla)</div>
            <code className="block text-base tracking-widest">{gonderim.erisim_anahtari}</code>
            <button type="button" className="btn-ghost mt-1 !min-h-7 !px-2 !text-xs" onClick={() => kopyala(gonderim.erisim_anahtari, 'Anahtar')}>
              <Copy className="h-3 w-3" aria-hidden /> Kopyala
            </button>
          </div>
          <p className="text-warn sm:col-span-2" data-testid="tur-kanal-uyarisi">
            Bağlantı ile anahtarı <b>ayrı</b> mesajlarda gönderin; ikisi tek mesajda giderse anahtarın koruması kalmaz.
            {gonderim.satir_sayisi ? ` ${gonderim.satir_sayisi} satır donduruldu.` : ''}
          </p>
        </div>
      ) : null}

      {revizyonAcik ? (
        <div className="mt-2 rounded-lg border border-line bg-surface-2 p-3 text-xs">
          <label className="flex flex-col gap-1">
            <span className="text-ink-3">Revizyon gerekçesi</span>
            <textarea aria-label="Revizyon gerekçesi" className="input" rows={2} value={sebep} onChange={(olay) => setSebep(olay.target.value)} />
          </label>
          <p className="mt-1 text-ink-3">Firma bu gerekçeyi görür. Eski tur salt okunur kalır; yeni tur aynı kurla (inherit) taslak açılır.</p>
          <div className="mt-2 flex gap-2">
            <button
              type="button"
              className="btn-primary"
              disabled={busy || sebep.trim() === ''}
              onClick={() =>
                void calistir(async () => {
                  await tekliflerApi.revizyon(tur.id, { sebep: sebep.trim(), rate_policy: 'inherit' });
                  setRevizyonAcik(false);
                }, 'Revizyon istendi; yeni tur taslak olarak açıldı.')
              }
            >
              Revizyon iste ve yeni tur aç
            </button>
            <button type="button" className="btn-ghost" onClick={() => setRevizyonAcik(false)}>
              Vazgeç
            </button>
          </div>
        </div>
      ) : null}
    </li>
  );
}
