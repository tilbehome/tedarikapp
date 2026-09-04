import { beforeEach, describe, expect, test, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import TurYanitPaneli from './TurYanitPaneli';
import type { ExcelOnizleme, TeklifTuru, YanitSatiri, YapistirOnizleme } from '../../api/types';

/**
 * FİRMA YANITI PANELİ (V3-C Aşama 2.2).
 *
 * Üç kural:
 *   · Önizleme YAZMAZ: "Ayrıştır" yalnız önizleme ucunu çağırır; yazım
 *     ancak "Seçili N satırı uygula" ile ve önizlemenin parmak iziyle olur.
 *   · Belirsiz/hatalı satır SEÇİLEMEZ (kutu kapalı); belirsiz parçanın nedeni
 *     ve YASAK işlemi görünür — panel tahmin yapmaz, sunucu da yapmaz.
 *   · Excel önizlemesi gruplu gelir; varsayılan seçim yalnız "uygulanabilir".
 */

const yapistirCasusu = vi.fn<(turId: number, metin: string) => Promise<YapistirOnizleme>>();
const uygulaCasusu = vi.fn<(turId: number, body: { kaynak: string; parmak_izi: string; satirlar: YanitSatiri[] }) => Promise<unknown>>();
const excelIceAktarCasusu = vi.fn<(turId: number, b64: string) => Promise<ExcelOnizleme>>();
const sablonCasusu = vi.fn(async () => undefined);

vi.mock('../../api/endpoints', () => ({
  teklifler: {
    yapistirAyristir: (turId: number, metin: string) => yapistirCasusu(turId, metin),
    yanitUygula: (turId: number, body: { kaynak: string; parmak_izi: string; satirlar: YanitSatiri[] }) => uygulaCasusu(turId, body),
    excelIceAktar: (turId: number, b64: string) => excelIceAktarCasusu(turId, b64),
    excelSablon: () => sablonCasusu(),
    excelSonuc: async () => undefined,
  },
}));

function satir(ustyaz: Partial<YanitSatiri>): YanitSatiri {
  return {
    rfq_satir_id: 'S-1',
    yanit_durumu: 'found',
    ddp_birim_fiyat: '4.20',
    para_birimi: 'USD',
    ddp_kdv_dahil_onayi: true,
    moq_deger: '300',
    moq_birim: 'adet',
    termin_baslangici: 'order_confirmation',
    termin_baslangici_aciklamasi: null,
    termin_suresi: 20,
    termin_birimi: 'calendar_day',
    koli_ici_adet: null,
    koli_uzunluk_cm: null,
    koli_genislik_cm: null,
    koli_yukseklik_cm: null,
    koli_cbm: null,
    koli_brut_kg: null,
    koli_net_kg: null,
    ambalaj: null,
    firma_notu: null,
    alternatif_baglanti: null,
    alternatif_aciklama: null,
    kademeler: [],
    ...ustyaz,
  };
}

const tur = { id: 7, state: 'SENT', etiket: 'R1 gönderildi' } as TeklifTuru;

function panel() {
  const onBilgi = vi.fn();
  const onHata = vi.fn();
  const onDegisti = vi.fn();
  render(<TurYanitPaneli tur={tur} onDegisti={onDegisti} onBilgi={onBilgi} onHata={onHata} />);
  return { onBilgi, onHata, onDegisti };
}

beforeEach(() => {
  yapistirCasusu.mockReset();
  uygulaCasusu.mockReset();
  excelIceAktarCasusu.mockReset();
});

describe('TurYanitPaneli — yapıştır', () => {
  test('ayrıştır yalnız önizler; belirsiz parça seçilemez ve nedeni görünür; uygula parmak iziyle gider', async () => {
    const kullanici = userEvent.setup();
    yapistirCasusu.mockResolvedValue({
      parmak_izi: 'a'.repeat(64),
      satirlar: [
        { rfq_satir_id: 'S-1', urun_kodu: 'P00001', urun_adi: { tr: 'Termos' }, talep_miktar: '24', yeni: satir({}), eski: null, hatalar: [], eksik_zorunlu: [], secilebilir: true, varsayilan_secili: true },
        {
          rfq_satir_id: 'S-2',
          urun_kodu: 'P00002',
          urun_adi: { tr: 'Hoparlör' },
          talep_miktar: '10',
          yeni: satir({ rfq_satir_id: 'S-2', ddp_birim_fiyat: null, para_birimi: null }),
          eski: null,
          hatalar: [{ alan: 'moq', deger: 0, kural: 'MOQ en az 1 olmalıdır.' }],
          eksik_zorunlu: ['ddp_birim_fiyat_kdv_dahil'],
          secilebilir: false,
          varsayilan_secili: false,
        },
      ],
      belirsiz: [{ parca: 'DDP价格 12.50', aday_satir_idleri: ['S-2'], neden: 'Para birimi yazılmamış.', yasak_otomatik_islem: '12.50 değerini USD sanma' }],
      dogrulama_hatalari: [],
      eslesmeyen_satirlar: [],
    });
    uygulaCasusu.mockResolvedValue({ tekrar: false, yazilan: 1, satirlar: ['S-1'], state: 'PRICING', yanit: {} });
    const { onBilgi, onDegisti } = panel();

    await kullanici.click(screen.getByRole('button', { name: /Firma yanıtını işle/ }));
    await kullanici.type(screen.getByLabelText('Firma yanıtı metni'), 'P00001 USD 4.20');
    await kullanici.click(screen.getByRole('button', { name: /Ayrıştır/ }));

    await waitFor(() => expect(screen.getByTestId('yapistir-onizleme')).toBeInTheDocument());
    expect(yapistirCasusu).toHaveBeenCalledWith(7, 'P00001 USD 4.20');
    expect(uygulaCasusu).not.toHaveBeenCalled();
    expect(screen.getByTestId('belirsiz-listesi')).toHaveTextContent('12.50 değerini USD sanma');
    expect(screen.getByLabelText('Satırı uygula: P00001')).toBeChecked();
    expect(screen.getByLabelText('Satırı uygula: P00002')).toBeDisabled();
    expect(screen.getByTestId('onizleme-S-2')).toHaveTextContent('MOQ en az 1 olmalıdır.');

    await kullanici.click(screen.getByRole('button', { name: 'Seçili 1 satırı uygula' }));

    await waitFor(() => expect(uygulaCasusu).toHaveBeenCalledTimes(1));
    const govde = uygulaCasusu.mock.calls[0]?.[1];
    if (!govde) throw new Error('uygula çağrılmadı');
    expect(govde.kaynak).toBe('yapistir');
    expect(govde.parmak_izi).toBe('a'.repeat(64));
    expect(govde.satirlar.map((s) => s.rfq_satir_id)).toEqual(['S-1']);
    expect(onBilgi).toHaveBeenLastCalledWith(expect.stringContaining('1 satır yazıldı'));
    expect(onDegisti).toHaveBeenCalled();
  });

  test('hata toast ile bildirilir, uygulama çağrılmaz', async () => {
    const kullanici = userEvent.setup();
    yapistirCasusu.mockRejectedValue(new Error('Tur henüz gönderilmedi'));
    const { onHata } = panel();

    await kullanici.click(screen.getByRole('button', { name: /Firma yanıtını işle/ }));
    await kullanici.type(screen.getByLabelText('Firma yanıtı metni'), 'x');
    await kullanici.click(screen.getByRole('button', { name: /Ayrıştır/ }));

    await waitFor(() => expect(onHata).toHaveBeenCalled());
    expect(uygulaCasusu).not.toHaveBeenCalled();
  });
});

describe('TurYanitPaneli — Excel', () => {
  test('şablon indirilir; yüklenen dosya gruplu önizlenir; yalnız uygulanabilir olan varsayılan seçilidir', async () => {
    const kullanici = userEvent.setup();
    excelIceAktarCasusu.mockResolvedValue({
      parmak_izi: 'b'.repeat(64),
      manifest: { schema_version: 1, exported_at: '2026-09-04 10:00:00', supplier_round_id: 7, rfq_snapshot_id: 3, row_count: 2, tur_satir_sayisi: 2 },
      ozet: { uygulanabilir: 1, uyarili: 1, hatali: 0, belirsiz: 0, degisiklik_yok: 0 },
      satirlar: [
        { rfq_satir_id: 'S-1', hucre: 'QUOTATION!A2', urun_kodu: 'P00001', urun_adi: { tr: 'Termos' }, talep_miktar: '24', grup: 'uygulanabilir', secilebilir: true, varsayilan_secili: true, imza_bozuk: false, eski: null, yeni: satir({}), degisen: ['ddp_birim_fiyat'], hatalar: [], uyarilar: [], belirsiz: [] },
        { rfq_satir_id: 'S-2', hucre: 'QUOTATION!A3', urun_kodu: 'P00002', urun_adi: { tr: 'Hoparlör' }, talep_miktar: '10', grup: 'uyarili', secilebilir: true, varsayilan_secili: false, imza_bozuk: false, eski: null, yeni: satir({ rfq_satir_id: 'S-2', moq_deger: '500' }), degisen: ['moq_deger'], hatalar: [], uyarilar: ['moq: MOQ talep miktarının (10) üstünde'], belirsiz: [] },
      ],
    });
    uygulaCasusu.mockResolvedValue({ tekrar: false, yazilan: 2, satirlar: ['S-1', 'S-2'], state: 'PRICING', yanit: {} });
    const { onBilgi } = panel();

    await kullanici.click(screen.getByRole('button', { name: /Firma yanıtını işle/ }));
    await kullanici.click(screen.getByRole('tab', { name: /Excel gel-git/ }));
    await kullanici.click(screen.getByRole('button', { name: /Şablonu indir/ }));
    await waitFor(() => expect(sablonCasusu).toHaveBeenCalled());

    const dosya = new File(['PK-sahte'], 'firma-r1.xlsx', { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    await kullanici.upload(screen.getByLabelText('Dolu Excel dosyası'), dosya);

    await waitFor(() => expect(screen.getByTestId('excel-onizleme')).toBeInTheDocument());
    expect(excelIceAktarCasusu).toHaveBeenCalledWith(7, expect.any(String));
    expect(screen.getByLabelText('Satırı uygula: P00001')).toBeChecked();
    expect(screen.getByLabelText('Satırı uygula: P00002')).not.toBeChecked();
    expect(screen.getByTestId('excel-satir-S-2')).toHaveTextContent('MOQ talep miktarının (10) üstünde');

    // Uyarılı satır ELLE seçilir, sonra ikisi birlikte uygulanır.
    await kullanici.click(screen.getByLabelText('Satırı uygula: P00002'));
    await kullanici.click(screen.getByRole('button', { name: 'Seçili 2 satırı uygula' }));

    await waitFor(() => expect(uygulaCasusu).toHaveBeenCalledTimes(1));
    expect(uygulaCasusu.mock.calls[0]?.[1].kaynak).toBe('excel');
    expect(uygulaCasusu.mock.calls[0]?.[1].parmak_izi).toBe('b'.repeat(64));
    expect(onBilgi).toHaveBeenLastCalledWith(expect.stringContaining('2 satır yazıldı'));
  });
});
