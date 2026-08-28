/**
 * EKLENTİ v2 — 10 DURUMLU GÖRÜNÜR DURUM MAKİNESİ (İE#21 A2).
 *
 * Kaynak: `docs/v3/hazirlik/eklenti-e2e-senaryo-katalogu.md` §2–3 (10 durum +
 * geçiş tablosu) ve `tedarikapp-1688-sayfa-ici-eklenti-ui-uygulama-raporu.md`.
 *
 * NEDEN AYRI MODÜL: v1'de durum, arayüz kodunun içine dağılmış boolean'lardı
 * (`mesgul`, `hata`, `gonderildi`). Üç yeni gereksinim — çift tık koruması, 502
 * sonrası aynı `capture_id` ile tekrar, mükerrerde dört seçenek — bu boolean'larla
 * yazılınca her biri diğerinin kenar durumunu bozuyordu. Durum artık TEK yerde ve
 * geçişler VERİDİR: arayüz yalnız çizer, testler tabloyu doğrudan sınar.
 *
 * ÜÇ KURAL (kataloğun ağ ilkesi sütunu):
 *   1. `D1 Kapalı` ve `D2 Okunuyor` sırasında `/api/capture` ÇAĞRILMAZ.
 *   2. `D6 Gönderiliyor` TEK istek üretir; ikinci tık geçiş üretmez.
 *   3. `D7 Gönderildi`ten sonra kendiliğinden yeni POST olmaz — yeni yakalama
 *      ancak kullanıcı komutuyla (yeni tarama) başlar.
 */

export type Durum =
  | 'D1_KAPALI'
  | 'D2_OKUNUYOR'
  | 'D3_ONIZLEME'
  | 'D4_KISMI'
  | 'D5_OKUMA_HATASI'
  | 'D6_GONDERILIYOR'
  | 'D7_GONDERILDI'
  | 'D8_MUKERRER'
  | 'D9_YETKI_HATASI'
  | 'D10_SUNUCU_HATASI';

export type Olay =
  | 'TARA'
  | 'OKUMA_TAM'
  | 'OKUMA_KISMI'
  | 'OKUMA_HATASI'
  | 'DEVAM'
  | 'GONDER'
  | 'YANIT_BASARILI'
  | 'YANIT_MUKERRER'
  | 'YANIT_YETKI'
  | 'YANIT_SUNUCU'
  | 'BAGLANTI_YENILENDI'
  | 'MUKERRER_IPTAL'
  | 'KAPAT'
  | 'SAYFA_DEGISTI';

/** Kullanıcıya görünen metin — katalog §2'deki zorunlu ifadeler. */
export const DURUM_METINLERI: Record<Durum, string> = {
  D1_KAPALI: "TedarikApp'e Ekle",
  D2_OKUNUYOR: 'Veriler okunuyor…',
  D3_ONIZLEME: 'Ürün verileri bulundu',
  D4_KISMI: 'Bazı bilgiler eksik',
  D5_OKUMA_HATASI: 'Ürün verileri alınamadı',
  D6_GONDERILIYOR: 'Gönderiliyor…',
  D7_GONDERILDI: "TedarikApp'e gönderildi",
  D8_MUKERRER: 'Ürün zaten listede',
  D9_YETKI_HATASI: 'TedarikApp bağlantısı gerekli',
  D10_SUNUCU_HATASI: 'TedarikApp şu anda yanıt vermiyor',
};

/**
 * Geçiş tablosu (katalog §3). Tabloda OLMAYAN geçiş YOKTUR: makine olayı yok sayar
 * ve durumda kalır. "Yok sayar" bilinçli — hata fırlatmak, hızlı çift tıkta
 * arayüzü çökertirdi; sessiz kalmak kullanıcının gördüğü davranışla aynıdır.
 */
const GECISLER: Record<Durum, Partial<Record<Olay, Durum>>> = {
  D1_KAPALI: { TARA: 'D2_OKUNUYOR', SAYFA_DEGISTI: 'D1_KAPALI' },
  // D2'de TARA yok: çift tık koruması (E2E-EKL-04) tablonun kendisindedir.
  D2_OKUNUYOR: {
    OKUMA_TAM: 'D3_ONIZLEME',
    OKUMA_KISMI: 'D4_KISMI',
    OKUMA_HATASI: 'D5_OKUMA_HATASI',
    KAPAT: 'D1_KAPALI',
    SAYFA_DEGISTI: 'D1_KAPALI',
  },
  D3_ONIZLEME: { GONDER: 'D6_GONDERILIYOR', TARA: 'D2_OKUNUYOR', KAPAT: 'D1_KAPALI', SAYFA_DEGISTI: 'D1_KAPALI' },
  D4_KISMI: { DEVAM: 'D3_ONIZLEME', GONDER: 'D6_GONDERILIYOR', TARA: 'D2_OKUNUYOR', KAPAT: 'D1_KAPALI', SAYFA_DEGISTI: 'D1_KAPALI' },
  D5_OKUMA_HATASI: { TARA: 'D2_OKUNUYOR', KAPAT: 'D1_KAPALI', SAYFA_DEGISTI: 'D1_KAPALI' },
  // D6'da GONDER yok: tek istek sözü (E2E-EKL-12) tabloda tutulur.
  D6_GONDERILIYOR: {
    YANIT_BASARILI: 'D7_GONDERILDI',
    YANIT_MUKERRER: 'D8_MUKERRER',
    YANIT_YETKI: 'D9_YETKI_HATASI',
    YANIT_SUNUCU: 'D10_SUNUCU_HATASI',
    SAYFA_DEGISTI: 'D1_KAPALI',
  },
  D7_GONDERILDI: { TARA: 'D2_OKUNUYOR', KAPAT: 'D1_KAPALI', SAYFA_DEGISTI: 'D1_KAPALI' },
  D8_MUKERRER: { GONDER: 'D6_GONDERILIYOR', MUKERRER_IPTAL: 'D3_ONIZLEME', KAPAT: 'D1_KAPALI', SAYFA_DEGISTI: 'D1_KAPALI' },
  D9_YETKI_HATASI: { BAGLANTI_YENILENDI: 'D3_ONIZLEME', KAPAT: 'D1_KAPALI', SAYFA_DEGISTI: 'D1_KAPALI' },
  D10_SUNUCU_HATASI: { GONDER: 'D6_GONDERILIYOR', KAPAT: 'D1_KAPALI', SAYFA_DEGISTI: 'D1_KAPALI' },
};

/** Bu durumlarda ağ isteği açılmış olabilir mi? (katalog "ağ ilkesi" sütunu) */
export const AG_ISTEGI_ACIK: Durum[] = ['D6_GONDERILIYOR'];

/** Mükerrer ekranındaki dört seçenek (E2E-EKL-16..20). */
export type MukerrerSecenegi = 'MEVCUDU_AC' | 'BASKA_LISTEYE' | 'MEVCUDU_GUNCELLE' | 'IPTAL';

export const MUKERRER_SECENEKLERI: { kod: MukerrerSecenegi; etiket: string }[] = [
  { kod: 'MEVCUDU_AC', etiket: 'Mevcut ürünü aç' },
  { kod: 'BASKA_LISTEYE', etiket: 'Başka listeye ekle' },
  { kod: 'MEVCUDU_GUNCELLE', etiket: 'Mevcut kaydı güncelle' },
  { kod: 'IPTAL', etiket: 'İptal' },
];

export interface MakineDurumu {
  durum: Durum;
  /** Aynı yakalamanın kimliği — 502 sonrası tekrar AYNI kimlikle gider (E2E-EKL-15). */
  captureId: string | null;
  /** Kaç kez POST denendi? Tek istek sözünün sayısal kanıtı. */
  gonderimSayisi: number;
  /** Kısmi okumada eksik alan adları (E2E-EKL-10). */
  eksikler: string[];
}

export function baslangicDurumu(): MakineDurumu {
  return { durum: 'D1_KAPALI', captureId: null, gonderimSayisi: 0, eksikler: [] };
}

export interface OlayEki {
  captureId?: string;
  eksikler?: string[];
}

/**
 * Tek geçiş. Yeni bir nesne döner (mutasyon yok): arayüz eski durumu elinde
 * tutup karşılaştırabilsin, testler ara adımları görebilsin.
 */
export function gecis(mevcut: MakineDurumu, olay: Olay, ek: OlayEki = {}): MakineDurumu {
  const hedef = GECISLER[mevcut.durum][olay];
  if (hedef === undefined) {
    return mevcut;
  }

  const sonraki: MakineDurumu = {
    durum: hedef,
    captureId: mevcut.captureId,
    gonderimSayisi: mevcut.gonderimSayisi,
    eksikler: mevcut.eksikler,
  };

  if (olay === 'TARA' || olay === 'SAYFA_DEGISTI') {
    // YENİ TARAMA = YENİ YAKALAMA: kimlik sıfırlanır. Aksi hâlde başka bir ürün
    // önceki ürünün capture_id'siyle gider ve sunucu onu "aynı yakalama" sayar.
    sonraki.captureId = ek.captureId ?? null;
    sonraki.gonderimSayisi = 0;
    sonraki.eksikler = [];
  }

  if (olay === 'OKUMA_KISMI') {
    sonraki.eksikler = ek.eksikler ?? [];
  }
  if (olay === 'OKUMA_TAM') {
    sonraki.eksikler = [];
  }

  if (olay === 'GONDER') {
    // 502 ya da mükerrer sonrası TEKRAR: kimlik KORUNUR (idempotens sözü, K25).
    sonraki.captureId = mevcut.captureId ?? ek.captureId ?? null;
    sonraki.gonderimSayisi = mevcut.gonderimSayisi + 1;
  }

  return sonraki;
}

/** Arayüz kolaylığı: bu durumda tarama düğmesi basılabilir mi? */
export function taranabilir(durum: Durum): boolean {
  return GECISLER[durum].TARA !== undefined;
}

/** Arayüz kolaylığı: bu durumda gönder düğmesi basılabilir mi? */
export function gonderilebilir(durum: Durum): boolean {
  return GECISLER[durum].GONDER !== undefined;
}
