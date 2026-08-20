/**
 * Yakalama formunun ORTAK stili (İE#13 A1) — tek metin, iki yüzey:
 * tarayıcı popup'ı ve sayfa içi mini panel (shadow DOM) aynı kaynaktan beslenir,
 * böylece iki arayüz zamanla birbirinden ayrışmaz.
 *
 * Shadow DOM'da 1688'in stilleri sızmaz; yine de her kural `.tdk-` altındadır.
 */
export const CAPTURE_CSS = `
.tdk { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; font-size: 13px; color: #0f172a; display: flex; flex-direction: column; gap: 10px; }
.tdk *, .tdk *::before, .tdk *::after { box-sizing: border-box; }
.tdk p, .tdk h2 { margin: 0; }
.tdk [hidden] { display: none !important; }
.tdk-durum { font-size: 11px; color: #64748b; }
.tdk-durum.ok { color: #15803d; }
.tdk-durum.hata { color: #b91c1c; }
.tdk-onizleme { display: flex; gap: 10px; align-items: flex-start; }
.tdk-onizleme img { width: 72px; height: 72px; object-fit: cover; border-radius: 10px; border: 1px solid #e2e8f0; background: #f8fafc; }
.tdk-bilgi { display: flex; flex-direction: column; gap: 4px; min-width: 0; flex: 1; }
.tdk-ad { font-weight: 600; line-height: 1.3; max-height: 3.9em; overflow: hidden; }
.tdk-fiyat { color: #1d4ed8; font-weight: 600; }
.tdk-ceviri { display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; font-size: 12px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 5px 7px; }
.tdk-ceviri-etiket { color: #1e40af; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; font-weight: 700; }
.tdk-ceviri-metin { color: #0f172a; flex: 1; min-width: 0; }
.tdk-ceviri-kullan { padding: 2px 8px; border-radius: 6px; background: #1d4ed8; color: #fff; font-size: 11px; font-weight: 600; }
.tdk-ek { color: #64748b; font-size: 11px; }
.tdk-uyari { color: #92400e; background: #fef3c7; border-radius: 8px; padding: 6px 8px; font-size: 11px; }
.tdk label { display: flex; flex-direction: column; gap: 3px; font-size: 12px; color: #475569; }
.tdk input, .tdk select { padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-family: inherit; color: #0f172a; background: #fff; width: 100%; }
.tdk button { padding: 8px 10px; border: 0; border-radius: 8px; background: #1d4ed8; color: #fff; font-weight: 600; font-size: 13px; font-family: inherit; cursor: pointer; }
.tdk button:disabled { opacity: .6; cursor: wait; }
.tdk-satir { display: flex; gap: 8px; }
.tdk-satir label { flex: 1; }
.tdk-sonuc { border-radius: 8px; padding: 8px; font-size: 12px; }
.tdk-sonuc.ok { background: #dcfce7; color: #166534; }
.tdk-sonuc.hata { background: #fee2e2; color: #991b1b; }
`;

/** Sayfa içi mini panelin kabuğu — yalnız inline yüzeyde kullanılır. */
export const INLINE_CSS = `
:host { all: initial; }
.tdk-tetik { position: fixed; right: 18px; bottom: 18px; z-index: 2147483000; display: flex; align-items: center; gap: 8px;
  padding: 10px 14px; border: 0; border-radius: 999px; background: #1d4ed8; color: #fff; font-weight: 600; font-size: 13px;
  font-family: system-ui, -apple-system, "Segoe UI", sans-serif; cursor: pointer; box-shadow: 0 6px 20px rgba(15, 23, 42, .28); }
.tdk-tetik:hover { background: #1e40af; }
.tdk-tetik[hidden] { display: none !important; }
.tdk-panel { position: fixed; right: 18px; bottom: 18px; z-index: 2147483001; width: 340px; max-height: 82vh; overflow-y: auto;
  padding: 14px; border-radius: 14px; background: #fff; box-shadow: 0 18px 48px rgba(15, 23, 42, .32); border: 1px solid #e2e8f0; }
.tdk-panel[hidden] { display: none !important; }
.tdk-baslik { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 10px;
  font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
.tdk-baslik strong { color: #1d4ed8; font-size: 14px; }
.tdk-kapat { border: 0; background: none; color: #64748b; font-size: 18px; line-height: 1; cursor: pointer; padding: 2px 6px; }
.tdk-kapat:hover { color: #0f172a; }
`;
