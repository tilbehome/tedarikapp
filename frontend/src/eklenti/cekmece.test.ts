import { beforeEach, describe, expect, test, vi } from 'vitest';

import { cekmeceKur } from '../../../extension/ui/v2/cekmece';
import { V2_CSS } from '../../../extension/ui/v2/stil';
import type { PanelEylemleri, PanelGorunumu } from '../../../extension/ui/v2/panel';
import { baslangicDurumu } from '../../../extension/core/durumMakinesi';

/**
 * D10-c / rc7 EK-1 §5 — PANEL VARSAYILAN KAPALIDIR.
 *
 * SAHA KANITI (Ürün Sahibi ekranı, rc6): panel her sayfa yüklemesinde
 * kendiliğinden açık görünüyor; içi boş kabuk; sağ-alt pill yedeği de
 * görünmüyor.
 *
 * KÖK NEDEN (koddan kanıtlandı): çekmece `panel.hidden = true` ile kapatılıyordu,
 * ama `V2_CSS` içindeki `.tdk-cekmece { display: flex }` YAZAR kuralı, tarayıcının
 * `[hidden] { display: none }` kuralını EZER (yazar kuralı UA kuralını yener).
 * Yani "kapalı" hiçbir zaman kapalı değildi: 448 px genişliğindeki çekmece hep
 * ekranda duruyor, sağ alt köşedeki pill yedeğini de örtüyordu. Gövde ise
 * yalnız `ciz()` çağrılınca doluyor — tıklama olmadığı için boş kalıyordu.
 * "Boş kabuk" görüntüsünün tamamı bu tek kusurdan çıkıyor.
 *
 * ÇÖZÜM: görünürlük artık SINIFLA yönetilir (`tdk-acik`). `hidden` özniteliği
 * korunur (erişilebilirlik + tarayıcı davranışı) ama görünürlük ona BAĞLI DEĞİLDİR.
 */

function gorunum(fark: Partial<PanelGorunumu> = {}): PanelGorunumu {
  return {
    makine: baslangicDurumu(),
    rapor: null,
    urunAdi: null,
    orijinalAd: null,
    varyantlar: [],
    seciliVaryant: null,
    listeler: [],
    hedef: { listeId: null, miktar: 1, not: '', etiketler: [] },
    duranlar: [],
    disclosureGerekli: false,
    urunId: null,
    baglanti: 'BILINMIYOR',
    baglantiMesaj: 'Bağlantı durumu bilinmiyor.',
    ...fark,
  };
}

function eylemler(): PanelEylemleri {
  return {
    onTara: vi.fn(),
    onGonder: vi.fn(),
    onKapat: vi.fn(),
    onDevam: vi.fn(),
    onMukerrer: vi.fn(),
    onHedef: vi.fn(),
    onVaryant: vi.fn(),
    onDisclosure: vi.fn(),
    onKuyruk: vi.fn(),
    onPaneldeAc: vi.fn(),
    onBaglantiyiDene: vi.fn(),
  };
}

function panelDugumu(kok: ShadowRoot): HTMLElement {
  const panel = kok.querySelector('.tdk-cekmece');
  expect(panel).not.toBeNull();

  return panel as HTMLElement;
}

describe('rc7 EK-1 §5 — çekmece varsayılan KAPALI', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
  });

  test('kurulunca AÇILMAZ: açık sınıfı yok, acikMi() false', () => {
    const cekmece = cekmeceKur({ eylemler: eylemler() });

    expect(cekmece.acikMi()).toBe(false);
    expect(panelDugumu(cekmece.kok()).classList.contains('tdk-acik')).toBe(false);
  });

  test('ÇİZMEK AÇMAZ: gövde dolsa da panel kapalı kalır', () => {
    const cekmece = cekmeceKur({ eylemler: eylemler() });

    // D10'da eklenen "önce çiz sonra aç" adımı, çizimin kendisini açma sanılırsa
    // panel her veri güncellemesinde önüne atlardı.
    cekmece.ciz(gorunum());

    expect(cekmece.acikMi()).toBe(false);
    expect(panelDugumu(cekmece.kok()).classList.contains('tdk-acik')).toBe(false);
  });

  test('yalnız ac() açar, kapat() kapatır', () => {
    const cekmece = cekmeceKur({ eylemler: eylemler() });

    cekmece.ac();
    expect(cekmece.acikMi()).toBe(true);
    expect(panelDugumu(cekmece.kok()).classList.contains('tdk-acik')).toBe(true);

    cekmece.kapat();
    expect(cekmece.acikMi()).toBe(false);
    expect(panelDugumu(cekmece.kok()).classList.contains('tdk-acik')).toBe(false);
  });

  test('YENİ KURULUM (sayfa yenilemesi) HER ZAMAN kapalı başlar — durum hatırlanmaz', () => {
    for (let yukleme = 0; yukleme < 5; yukleme++) {
      const cekmece = cekmeceKur({ eylemler: eylemler() });
      cekmece.ac();
      expect(cekmece.acikMi()).toBe(true);

      // Sayfa yenilendi: yeni kurulum. Önceki "açık" hâli TAŞINMAZ.
      const yeni = cekmeceKur({ eylemler: eylemler() });
      expect(yeni.acikMi()).toBe(false);
      expect(panelDugumu(yeni.kok()).classList.contains('tdk-acik')).toBe(false);
    }
  });

  test('CSS görünürlüğü SINIFA bağlar; `hidden` özniteliğine güvenmez', () => {
    // Kök neden testi: `.tdk-cekmece { display: flex }` yazar kuralı, UA'nın
    // `[hidden] { display: none }` kuralını ezer. Bu yüzden görünürlük sınıfla
    // yönetilir ve varsayılan `display: none` olmalıdır.
    expect(V2_CSS).toMatch(/\.tdk-cekmece\s*\{[^}]*display:\s*none/);
    expect(V2_CSS).toMatch(/\.tdk-cekmece\.tdk-acik\s*\{[^}]*display:\s*flex/);
    expect(V2_CSS).toMatch(/\.tdk-ortu\s*\{[^}]*display:\s*none/);
    expect(V2_CSS).toMatch(/\.tdk-ortu\.tdk-acik\s*\{[^}]*display:\s*block/);
  });
});
