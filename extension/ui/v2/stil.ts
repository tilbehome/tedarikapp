/**
 * EKLENTİ v2 SAYFA İÇİ ARAYÜZ — STİLLER (İE#21 A1).
 *
 * Kaynak: `docs/sablon/eklenti-v2-sayfa-ici-mockup.html` (onaylı mockup) ve marka
 * kitinin tasarım tokenları (`docs/marka/design-system/tokens.json`). Değerler
 * mockup'ın `:root` bloğuyla BİREBİR aynıdır; token adları da korunur ki tasarım
 * güncellenince iki dosya karşılaştırılabilsin.
 *
 * NEDEN LACİVERT: 1688'in kendi vurgu rengi turuncudur. Düğmemiz turuncu olsaydı
 * sayfanın kendi öğesi sanılırdı — kullanıcı "bunu ben mi kurdum?" diye
 * düşünmemeli. Lacivert + altın odak halkası, sayfadan AYRIŞMAK için seçildi.
 *
 * İZOLASYON: tüm arayüz KAPALI shadow DOM içindedir; 1688'in CSS'i içeri, bizim
 * stilimiz dışarı sızmaz (E2E-EKL-26).
 */

export const V2_TOKENLAR = `
:host, :root {
  --lacivert:#0F2557; --lacivert-koyu:#0A1A3F; --altin:#D4A017;
  --n50:#F7F8FB; --n100:#EEF0F5; --n150:#E3E6EE; --n200:#D5D9E4;
  --n400:#9AA1B2; --n500:#6C7484; --n700:#3C4354; --n900:#171C2A;
  --yesil:#1E8E5A; --sari:#B7791F; --sari-zemin:#FDF6E7; --kirmizi:#C0392B;
  --r8:8px; --r12:12px; --r16:16px;
  --golge-kart:0 1px 3px rgba(15,37,87,.08);
  --golge-acilir:0 8px 24px rgba(15,37,87,.14);
  --golge-modal:0 20px 60px rgba(10,26,63,.28);
}
`;

export const V2_CSS = `
${V2_TOKENLAR}

* { box-sizing: border-box; }
button { font: inherit; cursor: pointer; border: 0; }

/* ── BİRİNCİL DÜĞME (sayfa içi, satır içi montaj) ───────────────────── */
.tdk-btn {
  margin-top: 14px; width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px;
  background: var(--lacivert); color: #fff; border-radius: 10px; padding: 13px 18px;
  font-size: 14.5px; font-weight: 600; letter-spacing: .2px;
  box-shadow: var(--golge-kart); transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
}
.tdk-btn:hover { background: var(--lacivert-koyu); box-shadow: var(--golge-acilir); transform: translateY(-1px); }
.tdk-btn:focus-visible { outline: 3px solid var(--altin); outline-offset: 2px; }
.tdk-btn[disabled] { opacity: .6; cursor: default; transform: none; }
.tdk-btn .kup { width: 20px; height: 20px; border-radius: 5px; flex-shrink: 0;
  background: linear-gradient(135deg,#fff 0%,#fff 55%,var(--altin) 55%); }
.tdk-btn .rozet { margin-left: auto; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.25);
  font-size: 10.5px; font-weight: 600; padding: 3px 9px; border-radius: 999px; color: #F4E9CE; }

/* ── PILL (yedek montaj: satır içi yer bulunamazsa sağ alt) ─────────── */
.tdk-pill {
  position: fixed; right: 18px; bottom: 18px; z-index: 2147483000;
  display: flex; align-items: center; gap: 8px;
  background: var(--lacivert); color: #fff; border-radius: 999px; padding: 11px 16px;
  font-size: 13.5px; font-weight: 600; box-shadow: var(--golge-acilir);
}
.tdk-pill:hover { background: var(--lacivert-koyu); }
.tdk-pill:focus-visible { outline: 3px solid var(--altin); outline-offset: 2px; }
.tdk-pill .sayac { background: var(--altin); color: var(--lacivert-koyu); border-radius: 999px;
  font-size: 11px; font-weight: 800; padding: 1px 7px; }

/* ── DURUM ŞERİDİ (D1–D10 · hiçbir eylem sessiz değildir) ───────────── */
.tdk-serit { display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 600;
  padding: 9px 12px; border-radius: var(--r8); background: var(--n100); color: var(--n700); }
.tdk-serit .nokta { width: 8px; height: 8px; border-radius: 50%; background: var(--n400); flex: none; }
.tdk-serit[data-durum="D2_OKUNUYOR"], .tdk-serit[data-durum="D6_GONDERILIYOR"] { background: #EEF2FF; color: #3730A3; }
.tdk-serit[data-durum="D2_OKUNUYOR"] .nokta, .tdk-serit[data-durum="D6_GONDERILIYOR"] .nokta { background: #4F46E5; }
.tdk-serit[data-durum="D3_ONIZLEME"], .tdk-serit[data-durum="D7_GONDERILDI"] { background: #E9F7F0; color: var(--yesil); }
.tdk-serit[data-durum="D3_ONIZLEME"] .nokta, .tdk-serit[data-durum="D7_GONDERILDI"] .nokta { background: var(--yesil); }
.tdk-serit[data-durum="D4_KISMI"], .tdk-serit[data-durum="D8_MUKERRER"] { background: var(--sari-zemin); color: var(--sari); }
.tdk-serit[data-durum="D4_KISMI"] .nokta, .tdk-serit[data-durum="D8_MUKERRER"] .nokta { background: var(--sari); }
.tdk-serit[data-durum="D5_OKUMA_HATASI"], .tdk-serit[data-durum="D9_YETKI_HATASI"],
.tdk-serit[data-durum="D10_SUNUCU_HATASI"] { background: #FDECEA; color: var(--kirmizi); }
.tdk-serit[data-durum="D5_OKUMA_HATASI"] .nokta, .tdk-serit[data-durum="D9_YETKI_HATASI"] .nokta,
.tdk-serit[data-durum="D10_SUNUCU_HATASI"] .nokta { background: var(--kirmizi); }

/* ── ÇEKMECE ────────────────────────────────────────────────────────── */
.tdk-ortu { position: fixed; inset: 0; background: rgba(10,26,63,.42); z-index: 2147483000; }
.tdk-cekmece { position: fixed; top: 0; right: 0; bottom: 0; width: 448px; max-width: 100vw;
  background: #fff; z-index: 2147483001; box-shadow: var(--golge-modal);
  display: flex; flex-direction: column; font-family: system-ui, sans-serif; color: var(--n900); }
.tdk-ust { background: var(--lacivert); color: #fff; padding: 14px 18px; display: flex; align-items: center; gap: 11px;
  border-bottom: 3px solid var(--altin); }
.tdk-ust .kup { width: 26px; height: 26px; border-radius: 7px;
  background: linear-gradient(135deg,#fff 0%,#fff 55%,var(--altin) 55%); }
.tdk-marka { font-weight: 800; font-size: 16px; letter-spacing: -.2px; }
.tdk-marka small { display: block; font-weight: 500; font-size: 9.5px; letter-spacing: 1.6px; color: #AAB6D6; }
.tdk-kapat { margin-left: auto; background: transparent; color: #B9C3DE; font-size: 20px; line-height: 1;
  padding: 4px 8px; border-radius: 6px; }
.tdk-kapat:hover { background: rgba(255,255,255,.12); color: #fff; }
.tdk-govde { flex: 1; overflow-y: auto; padding: 14px 16px 16px; background: var(--n50); display: grid; gap: 12px; }
.tdk-alt { border-top: 1px solid var(--n150); padding: 12px 16px; background: #fff; display: grid; gap: 8px; }

/* ── ÜRÜN + DOLULUK ─────────────────────────────────────────────────── */
.tdk-kart { background: #fff; border: 1px solid var(--n150); border-radius: var(--r12); padding: 12px; }
.tdk-ad { font-weight: 700; font-size: 14px; line-height: 1.35; }
.tdk-zh { font-size: 12px; color: var(--n500); margin-top: 3px; }
.tdk-doluluk { display: flex; align-items: center; gap: 12px; }
.tdk-halka { width: 54px; height: 54px; border-radius: 50%; flex: none; display: grid; place-items: center;
  font-size: 12px; font-weight: 800; color: var(--lacivert);
  background: conic-gradient(var(--yesil) calc(var(--oran,0) * 1%), var(--n150) 0); }
.tdk-halka span { width: 42px; height: 42px; border-radius: 50%; background: #fff; display: grid; place-items: center; }
.tdk-alanlar { display: grid; gap: 4px; max-height: 260px; overflow-y: auto; }
.tdk-alan { display: flex; align-items: baseline; gap: 8px; font-size: 12px; padding: 4px 0;
  border-bottom: 1px solid var(--n100); }
.tdk-alan .ad { color: var(--n700); min-width: 108px; }
.tdk-alan .deger { color: var(--n900); flex: 1; }
.tdk-alan .kanal { font-size: 9.5px; font-weight: 700; letter-spacing: .4px; color: var(--n500);
  background: var(--n100); border-radius: 999px; padding: 2px 7px; }
.tdk-alan.eksik { color: var(--sari); }
.tdk-alan.eksik .ad, .tdk-alan.eksik .deger { color: var(--sari); }

/* ── SEÇİLEN VARYANT ────────────────────────────────────────────────── */
.tdk-varyant { display: flex; flex-wrap: wrap; gap: 6px; }
.tdk-varyant .cip { border: 1px solid var(--n200); border-radius: 999px; padding: 4px 10px; font-size: 12px;
  background: #fff; color: var(--n700); }
.tdk-varyant .cip[aria-pressed="true"] { background: var(--lacivert); color: #fff; border-color: var(--lacivert); }

/* ── HEDEF ALANLARI ─────────────────────────────────────────────────── */
.tdk-alan-grup { display: grid; gap: 8px; }
.tdk-satir2 { display: grid; grid-template-columns: 1fr 110px; gap: 8px; }
.tdk-girdi { display: grid; gap: 4px; }
.tdk-girdi label { font-size: 11px; font-weight: 600; color: var(--n500); }
.tdk-girdi select, .tdk-girdi input, .tdk-girdi textarea {
  border: 1px solid var(--n200); border-radius: var(--r8); padding: 8px 10px; font-size: 13px;
  font-family: inherit; color: var(--n900); background: #fff; width: 100%;
}
.tdk-girdi select:focus, .tdk-girdi input:focus, .tdk-girdi textarea:focus {
  outline: 2px solid var(--lacivert); outline-offset: 1px;
}

/* ── MÜKERRER SEÇENEKLERİ ───────────────────────────────────────────── */
.tdk-mukerrer { display: grid; gap: 6px; background: var(--sari-zemin); border: 1px solid #F3E2BE;
  border-radius: var(--r12); padding: 12px; }
.tdk-mukerrer h4 { margin: 0; font-size: 13px; color: var(--sari); }
.tdk-mukerrer button { text-align: left; background: #fff; border: 1px solid var(--n200); border-radius: var(--r8);
  padding: 8px 10px; font-size: 12.5px; color: var(--n900); }
.tdk-mukerrer button:hover { border-color: var(--lacivert); }

/* ── BAĞLANTI ŞERİDİ (D5) ───────────────────────────────────────────── */
.tdk-baglanti { display: flex; align-items: center; gap: 8px; font-size: 12px; padding: 9px 12px;
  border-radius: var(--r8); background: var(--sari-zemin); color: var(--sari); border: 1px solid #F3E2BE; }
.tdk-baglanti[data-baglanti="DENENIYOR"] { background: #EEF2FF; color: #3730A3; border-color: #DDE3FF; }
.tdk-baglanti[data-baglanti="ERISILEMIYOR"], .tdk-baglanti[data-baglanti="YETKI"] {
  background: #FDECEA; color: var(--kirmizi); border-color: #F3C6C0; }
.tdk-baglanti .metin { flex: 1; }
.tdk-baglanti button { background: #fff; border: 1px solid currentColor; border-radius: var(--r8);
  padding: 4px 10px; font-size: 11.5px; color: inherit; }

/* ── KUYRUK ROZETİ (dead-letter ikizi) ──────────────────────────────── */
.tdk-kuyruk { display: grid; gap: 6px; border: 1px solid #F3C6C0; background: #FDECEA; border-radius: var(--r12);
  padding: 10px 12px; font-size: 12px; color: var(--kirmizi); }
.tdk-kuyruk .eylemler { display: flex; gap: 6px; flex-wrap: wrap; }
.tdk-kuyruk button { background: #fff; border: 1px solid #F3C6C0; border-radius: var(--r8); padding: 5px 10px;
  font-size: 11.5px; color: var(--kirmizi); }

/* ── DISCLOSURE ─────────────────────────────────────────────────────── */
.tdk-disclosure { display: grid; gap: 10px; font-size: 12.5px; color: var(--n700); }
.tdk-disclosure h4 { margin: 0; font-size: 14px; color: var(--n900); }
.tdk-disclosure ul { margin: 0; padding-left: 18px; display: grid; gap: 3px; }
.tdk-disclosure .yesil { color: var(--yesil); }
.tdk-disclosure .dugmeler { display: flex; gap: 8px; }
.tdk-onay { background: var(--lacivert); color: #fff; border-radius: var(--r8); padding: 9px 14px; font-weight: 600; }
.tdk-red { background: #fff; border: 1px solid var(--n200); border-radius: var(--r8); padding: 9px 14px; color: var(--n700); }

/* ── GÖNDER ─────────────────────────────────────────────────────────── */
.tdk-gonder { width: 100%; background: var(--lacivert); color: #fff; border-radius: 10px; padding: 12px;
  font-size: 14px; font-weight: 700; }
.tdk-gonder[disabled] { opacity: .55; cursor: default; }
.tdk-ipucu { font-size: 11px; color: var(--n500); text-align: center; }

@media (prefers-reduced-motion: reduce) { .tdk-btn, .tdk-pill { transition: none; } }
`;
