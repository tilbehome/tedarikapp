import { useRef, useState } from 'react';
import { ClipboardPaste, Download, FileSpreadsheet, Upload } from 'lucide-react';
import { teklifler as tekliflerApi } from '../../api/endpoints';
import type { ExcelOnizleme, ExcelOnizlemeGrubu, TeklifTuru, YanitSatiri, YapistirOnizleme } from '../../api/types';

/**
 * FİRMA YANITI PANELİ (V3-C Aşama 2.2 · #28-EK · excel-gelgit-spec §9).
 *
 * İki kanal, tek akış: ÖNİZLE → sahip satır seçer → UYGULA. Önizleme hiçbir
 * şey yazmaz; uygulama sunucuda tek transaction ve önizlemenin parmak iziyle
 * idempotenttir (aynı önizleme iki kez "Uygula"ya basılsa bile bir kez yazılır).
 *
 * K105: her düğme ne yapacağını söyler ("Ayrıştır", "Seçili N satırı uygula");
 * sessiz eylem yok — her işin sonunda toast (`onBilgi`) ya da hata (`onHata`).
 *
 * Belirsiz parçalar (para birimsiz fiyat, aynı adlı iki ürün) ASLA seçilemez:
 * altın set kuralı sunucudadır, panel yalnız nedenini ve yasak işlemi gösterir.
 */
interface Props {
  tur: TeklifTuru;
  onDegisti: () => void;
  onBilgi: (metin: string) => void;
  onHata: (hata: unknown) => void;
}

type Sekme = 'yapistir' | 'excel';

const GRUP_ETIKETI: Record<ExcelOnizlemeGrubu, string> = {
  uygulanabilir: 'Uygulanabilir',
  uyarili: 'Uyarılı (elle seç)',
  hatali: 'Hatalı (uygulanamaz)',
  belirsiz: 'Belirsiz (karar gerekli)',
  degisiklik_yok: 'Değişiklik yok',
};

const GRUP_TONU: Record<ExcelOnizlemeGrubu, string> = {
  uygulanabilir: 'bg-ok-bg text-ok ring-ok/20',
  uyarili: 'bg-warn-soft text-warn ring-warn/20',
  hatali: 'bg-err-bg text-err ring-err/20',
  belirsiz: 'bg-navy/10 text-navy ring-navy/20',
  degisiklik_yok: 'bg-g100 text-ink-3 ring-line',
};

export default function TurYanitPaneli({ tur, onDegisti, onBilgi, onHata }: Props) {
  const [acik, setAcik] = useState(false);
  const [sekme, setSekme] = useState<Sekme>('yapistir');

  return (
    <div className="mt-2" data-testid={`yanit-paneli-${tur.id}`}>
      <button type="button" className="btn-ghost !min-h-8 !px-2 !text-xs" onClick={() => setAcik((v) => !v)} aria-expanded={acik}>
        <ClipboardPaste className="h-3.5 w-3.5" aria-hidden />
        Firma yanıtını işle
      </button>
      {acik ? (
        <div className="mt-2 rounded-lg border border-line bg-surface-2 p-3 text-xs">
          <div className="mb-2 flex gap-1" role="tablist" aria-label="Yanıt kanalı">
            <SekmeDugmesi aktif={sekme === 'yapistir'} onClick={() => setSekme('yapistir')}>
              <ClipboardPaste className="h-3.5 w-3.5" aria-hidden /> Yapıştır (WhatsApp / e-posta)
            </SekmeDugmesi>
            <SekmeDugmesi aktif={sekme === 'excel'} onClick={() => setSekme('excel')}>
              <FileSpreadsheet className="h-3.5 w-3.5" aria-hidden /> Excel gel-git
            </SekmeDugmesi>
          </div>
          {sekme === 'yapistir' ? (
            <YapistirSekmesi tur={tur} onDegisti={onDegisti} onBilgi={onBilgi} onHata={onHata} />
          ) : (
            <ExcelSekmesi tur={tur} onDegisti={onDegisti} onBilgi={onBilgi} onHata={onHata} />
          )}
        </div>
      ) : null}
    </div>
  );
}

function SekmeDugmesi({ aktif, onClick, children }: { aktif: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      type="button"
      role="tab"
      aria-selected={aktif}
      className={`inline-flex items-center gap-1 rounded-md px-2 py-1 ${aktif ? 'bg-navy text-white' : 'bg-g100 text-ink-2 hover:bg-line'}`}
      onClick={onClick}
    >
      {children}
    </button>
  );
}

// ── Yapıştır ─────────────────────────────────────────────────────────

function YapistirSekmesi({ tur, onDegisti, onBilgi, onHata }: Props) {
  const [metin, setMetin] = useState('');
  const [onizleme, setOnizleme] = useState<YapistirOnizleme | null>(null);
  const [secili, setSecili] = useState<Set<string>>(new Set());
  const [busy, setBusy] = useState(false);

  const ayristir = async () => {
    setBusy(true);
    try {
      const sonuc = await tekliflerApi.yapistirAyristir(tur.id, metin);
      setOnizleme(sonuc);
      setSecili(new Set(sonuc.satirlar.filter((s) => s.varsayilan_secili).map((s) => s.rfq_satir_id)));
      onBilgi(
        `${sonuc.satirlar.length} satır eşleşti` +
          (sonuc.belirsiz.length ? `, ${sonuc.belirsiz.length} parça belirsiz` : '') +
          (sonuc.dogrulama_hatalari.length ? `, ${sonuc.dogrulama_hatalari.length} alan hatası` : '') +
          '. Hiçbir şey yazılmadı; satırları kontrol edip uygulayın.',
      );
    } catch (hata) {
      onHata(hata);
    } finally {
      setBusy(false);
    }
  };

  const uygula = async () => {
    if (!onizleme) return;
    setBusy(true);
    try {
      const satirlar = onizleme.satirlar.filter((s) => secili.has(s.rfq_satir_id)).map((s) => s.yeni);
      const sonuc = await tekliflerApi.yanitUygula(tur.id, { kaynak: 'yapistir', parmak_izi: onizleme.parmak_izi, satirlar });
      onBilgi(sonuc.tekrar ? 'Bu önizleme zaten uygulanmıştı; yeniden yazılmadı.' : `${sonuc.yazilan} satır yazıldı; tur "${sonuc.state}" durumunda.`);
      setOnizleme(null);
      setMetin('');
      onDegisti();
    } catch (hata) {
      onHata(hata);
    } finally {
      setBusy(false);
    }
  };

  const sec = (id: string, ac: boolean) =>
    setSecili((eski) => {
      const yeni = new Set(eski);
      if (ac) yeni.add(id);
      else yeni.delete(id);
      return yeni;
    });

  return (
    <div data-testid="yapistir-sekmesi">
      <label className="flex flex-col gap-1">
        <span className="text-ink-3">Firmanın mesajını olduğu gibi yapıştırın (ürün kodları ile: {tur.etiket})</span>
        <textarea
          aria-label="Firma yanıtı metni"
          className="input font-mono"
          rows={5}
          value={metin}
          onChange={(olay) => setMetin(olay.target.value)}
          placeholder={'P00012 有货，DDP含土耳其税 USD 4.20，MOQ 300，订单确认后20天。'}
        />
      </label>
      <div className="mt-2 flex gap-2">
        <button type="button" className="btn-primary" disabled={busy || metin.trim() === ''} onClick={() => void ayristir()}>
          Ayrıştır (yazmaz)
        </button>
      </div>

      {onizleme ? (
        <div className="mt-3" data-testid="yapistir-onizleme">
          {onizleme.belirsiz.length ? (
            <ul className="mb-2 space-y-1 rounded-md border border-navy/20 bg-navy/5 p-2" data-testid="belirsiz-listesi">
              {onizleme.belirsiz.map((b, i) => (
                <li key={i}>
                  <b>Belirsiz:</b> <code>{b.parca}</code> — {b.neden}{' '}
                  <span className="text-ink-3">(aday: {b.aday_satir_idleri.length ? b.aday_satir_idleri.join(', ') : 'yok'} · yapılmayan: {b.yasak_otomatik_islem})</span>
                </li>
              ))}
            </ul>
          ) : null}
          {onizleme.satirlar.length === 0 ? <p className="text-ink-3">Hiçbir satır eşleşmedi; ürün kodlarını kontrol edin.</p> : null}
          <ul className="divide-y divide-line-soft">
            {onizleme.satirlar.map((s) => (
              <li key={s.rfq_satir_id} className="flex flex-wrap items-start gap-2 py-2" data-testid={`onizleme-${s.rfq_satir_id}`}>
                <input
                  type="checkbox"
                  aria-label={`Satırı uygula: ${s.urun_kodu ?? s.rfq_satir_id}`}
                  checked={secili.has(s.rfq_satir_id)}
                  disabled={!s.secilebilir}
                  onChange={(olay) => sec(s.rfq_satir_id, olay.target.checked)}
                />
                <div className="min-w-0 flex-1">
                  <div className="font-medium text-ink">
                    {s.urun_kodu} · {s.urun_adi?.tr ?? s.urun_adi?.en ?? s.urun_adi?.zh ?? ''} <span className="text-ink-3">(talep {s.talep_miktar})</span>
                  </div>
                  <YanitOzeti satir={s.yeni} eski={s.eski} />
                  {s.eksik_zorunlu.length ? <div className="text-warn">Eksik: {s.eksik_zorunlu.join(', ')}</div> : null}
                  {s.hatalar.map((h, i) => (
                    <div key={i} className="text-err">
                      {h.alan}: {h.kural}
                    </div>
                  ))}
                </div>
              </li>
            ))}
          </ul>
          {onizleme.eslesmeyen_satirlar.length ? (
            <p className="mt-1 text-ink-3">{onizleme.eslesmeyen_satirlar.length} RFQ satırı bu metinde geçmiyor; mevcut değerleri değişmez.</p>
          ) : null}
          <div className="mt-2 flex gap-2">
            <button type="button" className="btn-primary" disabled={busy || secili.size === 0} onClick={() => void uygula()}>
              Seçili {secili.size} satırı uygula
            </button>
            <button type="button" className="btn-ghost" disabled={busy} onClick={() => setOnizleme(null)}>
              Vazgeç
            </button>
          </div>
        </div>
      ) : null}
    </div>
  );
}

// ── Excel ────────────────────────────────────────────────────────────

function ExcelSekmesi({ tur, onDegisti, onBilgi, onHata }: Props) {
  const [onizleme, setOnizleme] = useState<ExcelOnizleme | null>(null);
  const [secili, setSecili] = useState<Set<string>>(new Set());
  const [busy, setBusy] = useState(false);
  const [dosyaAdi, setDosyaAdi] = useState('');
  const dosyaRef = useRef<HTMLInputElement | null>(null);

  const sablonIndir = async () => {
    setBusy(true);
    try {
      await tekliflerApi.excelSablon(tur.id);
      onBilgi('Şablon indirildi; firmaya bağlantı/anahtar OLMADAN gönderin (dosyada yoktur).');
    } catch (hata) {
      onHata(hata);
    } finally {
      setBusy(false);
    }
  };

  const dosyaSecildi = async (dosya: File | undefined) => {
    if (!dosya) return;
    setBusy(true);
    try {
      setDosyaAdi(dosya.name);
      const base64 = await dosyayiBase64Oku(dosya);
      const sonuc = await tekliflerApi.excelIceAktar(tur.id, base64);
      setOnizleme(sonuc);
      setSecili(new Set(sonuc.satirlar.filter((s) => s.varsayilan_secili).map((s) => s.rfq_satir_id)));
      onBilgi(
        `Önizleme hazır: ${sonuc.ozet.uygulanabilir} uygulanabilir, ${sonuc.ozet.uyarili} uyarılı, ${sonuc.ozet.hatali} hatalı, ${sonuc.ozet.belirsiz} belirsiz, ${sonuc.ozet.degisiklik_yok} değişiklik yok. Hiçbir şey yazılmadı.`,
      );
    } catch (hata) {
      setOnizleme(null);
      onHata(hata);
    } finally {
      setBusy(false);
      if (dosyaRef.current) dosyaRef.current.value = '';
    }
  };

  const uygula = async () => {
    if (!onizleme) return;
    setBusy(true);
    try {
      const satirlar = onizleme.satirlar
        .filter((s) => secili.has(s.rfq_satir_id) && s.yeni !== null)
        .map((s) => s.yeni as YanitSatiri);
      const sonuc = await tekliflerApi.yanitUygula(tur.id, { kaynak: 'excel', parmak_izi: onizleme.parmak_izi, etiket: dosyaAdi, satirlar });
      onBilgi(sonuc.tekrar ? 'Bu dosya zaten uygulanmıştı; yeniden yazılmadı.' : `${sonuc.yazilan} satır yazıldı; tur "${sonuc.state}" durumunda.`);
      setOnizleme({ ...onizleme, satirlar: onizleme.satirlar });
      onDegisti();
    } catch (hata) {
      onHata(hata);
    } finally {
      setBusy(false);
    }
  };

  const sonucIndir = async () => {
    if (!onizleme) return;
    setBusy(true);
    try {
      await tekliflerApi.excelSonuc(tur.id, { ...onizleme, uygulanan: Array.from(secili) });
      onBilgi('Sonuç dosyası indirildi (…-IMPORT-RESULT.xlsx).');
    } catch (hata) {
      onHata(hata);
    } finally {
      setBusy(false);
    }
  };

  const sec = (id: string, ac: boolean) =>
    setSecili((eski) => {
      const yeni = new Set(eski);
      if (ac) yeni.add(id);
      else yeni.delete(id);
      return yeni;
    });

  return (
    <div data-testid="excel-sekmesi">
      <div className="flex flex-wrap gap-2">
        <button type="button" className="btn-ghost" disabled={busy} onClick={() => void sablonIndir()}>
          <Download className="h-4 w-4" aria-hidden /> Şablonu indir (.xlsx)
        </button>
        <label className="btn-primary cursor-pointer">
          <Upload className="h-4 w-4" aria-hidden /> Dolu dosyayı yükle
          <input
            ref={dosyaRef}
            type="file"
            accept=".xlsx"
            className="sr-only"
            aria-label="Dolu Excel dosyası"
            disabled={busy}
            onChange={(olay) => void dosyaSecildi(olay.target.files?.[0])}
          />
        </label>
      </div>
      <p className="mt-1 text-ink-3">Dosyada bağlantı ya da anahtar yoktur; makrolu, şifreli ya da başka tura ait dosya reddedilir.</p>

      {onizleme ? (
        <div className="mt-3" data-testid="excel-onizleme">
          <div className="mb-2 flex flex-wrap gap-1">
            {(Object.keys(GRUP_ETIKETI) as ExcelOnizlemeGrubu[]).map((g) => (
              <span key={g} className={`badge ${GRUP_TONU[g]}`}>
                {GRUP_ETIKETI[g]}: {onizleme.ozet[g]}
              </span>
            ))}
          </div>
          <ul className="divide-y divide-line-soft">
            {onizleme.satirlar.map((s) => (
              <li key={`${s.hucre}-${s.rfq_satir_id}`} className="flex flex-wrap items-start gap-2 py-2" data-testid={`excel-satir-${s.rfq_satir_id}`}>
                <input
                  type="checkbox"
                  aria-label={`Satırı uygula: ${s.urun_kodu ?? s.rfq_satir_id}`}
                  checked={secili.has(s.rfq_satir_id)}
                  disabled={!s.secilebilir}
                  onChange={(olay) => sec(s.rfq_satir_id, olay.target.checked)}
                />
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2 font-medium text-ink">
                    <span>
                      {s.urun_kodu ?? s.rfq_satir_id} · {s.urun_adi?.tr ?? s.urun_adi?.en ?? s.urun_adi?.zh ?? ''}
                    </span>
                    <span className={`badge ${GRUP_TONU[s.grup]}`}>{GRUP_ETIKETI[s.grup]}</span>
                    <span className="text-ink-3">{s.hucre}</span>
                  </div>
                  {s.yeni ? <YanitOzeti satir={s.yeni} eski={s.eski} /> : null}
                  {s.degisen.length ? <div className="text-ink-3">Değişen: {s.degisen.join(', ')}</div> : null}
                  {s.uyarilar.map((u, i) => (
                    <div key={`u${i}`} className="text-warn">
                      {u}
                    </div>
                  ))}
                  {s.belirsiz.map((b, i) => (
                    <div key={`b${i}`} className="text-navy">
                      {b}
                    </div>
                  ))}
                  {s.hatalar.map((h, i) => (
                    <div key={`h${i}`} className="text-err">
                      {h}
                    </div>
                  ))}
                </div>
              </li>
            ))}
          </ul>
          <div className="mt-2 flex flex-wrap gap-2">
            <button type="button" className="btn-primary" disabled={busy || secili.size === 0} onClick={() => void uygula()}>
              Seçili {secili.size} satırı uygula
            </button>
            <button type="button" className="btn-ghost" disabled={busy} onClick={() => void sonucIndir()}>
              <Download className="h-4 w-4" aria-hidden /> Sonuç dosyası
            </button>
            <button type="button" className="btn-ghost" disabled={busy} onClick={() => setOnizleme(null)}>
              Kapat
            </button>
          </div>
        </div>
      ) : null}
    </div>
  );
}

// ── ortak ────────────────────────────────────────────────────────────

/** Satırın "ne diyor" özeti; eski değer varsa yanında (eski → yeni). Para dizedir, hesap yapılmaz (K14). */
function YanitOzeti({ satir, eski }: { satir: YanitSatiri; eski: YanitSatiri | null }) {
  const fiyat = satir.ddp_birim_fiyat ? `${satir.ddp_birim_fiyat} ${satir.para_birimi ?? '?'}` : '—';
  const eskiFiyat = eski?.ddp_birim_fiyat ? `${eski.ddp_birim_fiyat} ${eski.para_birimi ?? '?'}` : null;
  const termin = satir.termin_suresi ? `${satir.termin_suresi} ${satir.termin_birimi === 'working_day' ? 'iş günü' : satir.termin_birimi === 'week' ? 'hafta' : 'gün'}` : '—';

  return (
    <div className="flex flex-wrap gap-x-3 text-ink-2">
      <span>
        Durum: <b>{DURUM_ETIKETI[satir.yanit_durumu]}</b>
      </span>
      <span>
        DDP+KDV: <b>{fiyat}</b>
        {eskiFiyat && eskiFiyat !== fiyat ? <span className="text-ink-3"> (eski {eskiFiyat})</span> : null}
        {satir.ddp_birim_fiyat && satir.ddp_kdv_dahil_onayi !== true ? <span className="text-warn"> KDV beyanı yok</span> : null}
      </span>
      <span>
        MOQ: <b>{satir.moq_deger ? `${satir.moq_deger} ${satir.moq_birim ?? ''}` : '—'}</b>
      </span>
      <span>
        Termin: <b>{termin}</b>
        {satir.termin_baslangici ? <span className="text-ink-3"> ({BASLANGIC_ETIKETI[satir.termin_baslangici] ?? satir.termin_baslangici})</span> : null}
      </span>
      {satir.kademeler.length ? <span>Kademe: {satir.kademeler.map((k) => `${k.min_adet}${k.max_adet ? `–${k.max_adet}` : '+'}→${k.birim_fiyat}`).join(' · ')}</span> : null}
      {satir.firma_notu ? <span className="text-ink-3">Not: {satir.firma_notu}</span> : null}
    </div>
  );
}

const DURUM_ETIKETI: Record<YanitSatiri['yanit_durumu'], string> = {
  unanswered: 'Yanıtsız',
  found: 'Bulundu',
  not_found: 'Bulunamadı',
  alternative_available: 'Alternatif var',
};

const BASLANGIC_ETIKETI: Record<string, string> = {
  order_confirmation: 'sipariş onayından',
  deposit_received: 'kaporadan',
  sample_approval: 'numune onayından',
  artwork_approval: 'görsel onayından',
  custom: 'özel başlangıç',
};

function dosyayiBase64Oku(dosya: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const okuyucu = new FileReader();
    okuyucu.onerror = () => reject(new Error('Dosya okunamadı.'));
    okuyucu.onload = () => {
      const sonuc = typeof okuyucu.result === 'string' ? okuyucu.result : '';
      resolve(sonuc.includes(',') ? sonuc.slice(sonuc.indexOf(',') + 1) : sonuc);
    };
    okuyucu.readAsDataURL(dosya);
  });
}
