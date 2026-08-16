# İŞ EMRİ #8 — Faz 1D: React Paneli (Uygulamanın Yüzü)
Faz: Faz 1D · Dal: `is-emri-8-panel` (PR aç; merge PM onayıyla) · Test rejimi: K35 (otomatik UI testi YOK — manuel kabul listesi)

> ÖN ŞART: PR #6 merge. Oku: docs/09 (ekran envanteri E1–E9 — BİREBİR uyulacak), docs/10 (API), docs/04 §5 (UI ilkeleri), K22 (çeviri katmanı), K35.

## Hedef
Telefonda uygulama hissi veren, masaüstünde Excel rahatlığında çalışan panel: giriş+2FA'dan liste yönetimine, ürün girişinden kur ayarına Faz 1'in tüm backend'i ekranlarda yaşıyor.

## Teknoloji (K5/K21 — kilitli)
React 19 + TypeScript + Vite + Tailwind + Zustand + React Router + Lucide. `frontend/` altında; K19 dışına kütüphane YOK (grafik/DnD/form kütüphanesi ekleme — gerekirse F-havuzuna öner).

## Yapılacaklar
1. **Temel iskelet:** Vite+TS proje, API istemcisi (zarf çözümü, hata kodu→Türkçe mesaj sözlüğü, 401→girişe yönlendirme, CSRF başlık yönetimi, para alanları `string` tipinde — asla number'a çevrilip hesaplanmaz, hesap DAİMA backend'den gelir), Zustand store'ları, route koruması.
2. **Ekranlar — docs/09 envanteri birebir (E1–E9):** giriş (şifre→TOTP→kurtarma yolu), liste görünümleri (Aktif/Pasif/Arşiv sekmeleri + dönem başlıkları), liste detay (ürün tablosu: görsel küçük, ad, kategori, adet, ¥, ₺, $, durum rozeti; sıralama; toplam satırı — backend'den), ürün ekle/düzenle (manuel giriş formu; görsel URL alanı → MediaService; hotlink modundaysa "arşivleme kapalı" rozeti), durum işaretleme (tek dokunuş menüsü + izinli geçişler API'den), kategoriler, ayarlar (kur güncelle + kur tarihçesi + sistem durumu), çöp kutusu (geri al / kalıcı sil), aktivite. Export/Paylaşım butonları GÖRÜNÜR ama "Faz 2" rozetiyle pasif.
3. **Mobil öncelik:** telefonda ürünler kart, masaüstünde tablo; alt gezinme çubuğu (mobil) / yan menü (masaüstü); dokunma hedefleri ≥44px; iskelet yükleme durumları; boş durum ekranları ("İlk listeni oluştur").
4. **Türkçe sözlük (K22):** enum→Türkçe eşlemeleri TEK dosyada (`locales/tr.ts`); ekranlarda ham enum sızmaz.
5. **Build & servis:** `npm run build` çıktısı `public/panel/` altına; Slim catch-all `/panel*` → index.html; build çıktısı repo'ya COMMIT EDİLMEZ (.gitignore) — runbook'a "release zip'i build içerir" adımı eklenir.
6. **CI frontend işi:** npm ci → tsc --noEmit → eslint → vite build. (Otomatik UI testi YOK — K35.)
7. **Belge:** docs/09'a "gerçeklenen ekran ↔ rota" tablosu; runbook build adımı; CHANGELOG.

## Kapsam DIŞI
Export üretimi ve paylaşım işlevi (Faz 2), Gelen Kutusu ekranı (Faz 3 — menüde "yakında" olarak durabilir), GTİP, PWA, tema/karanlık mod.

## Kabul — MANUEL KABUL LİSTESİ (raporda her madde ✓/✗ + ekran görüntüsü)
- [ ] Telefonda (gerçek cihaz veya 390px): giriş+TOTP → liste oluştur → 3 ürün ekle (görselli) → durumları ilerlet → toplamları gör → listeyi kopyala → çöpe at → geri al. Tüm akış AKICI.
- [ ] Masaüstünde aynı akış tablo görünümüyle.
- [ ] Kur ayarı değişince YENİ liste yeni kuru kilitliyor, eski liste etkilenmiyor (ekranda doğrulanır).
- [ ] Geçersiz durum geçişi UI'da zaten sunulmuyor (izinli listesi API'den); 422 durumunda Türkçe hata.
- [ ] Para her yerde string→görüntü; JS'te tek bir aritmetik yok (kod taraması).
- [ ] Oturum düşünce girişe dönüş; CSRF hatasında düzgün mesaj.
- [ ] CI (backend+frontend) yeşil; tsc 0 hata; eslint 0; K19 dışı bağımlılık yok (package.json denetimi).

## Teslim
PR + ÇIKTI RAPORU + telefon ve masaüstü ekran görüntüleri seti (her ekrandan en az bir kare).
