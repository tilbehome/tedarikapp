# İŞ EMRİ #9 — Sağlamlaştırma Sprinti (K37 + Dış İnceleme Bulguları)
Faz: Faz 1 kapanış · Dal: `is-emri-9-saglamlastirma` (main 32bc817'den) · Test rejimi: K35 · **İE#9 kabulü = üretim kurulumunun ÖN ŞARTI**

> Kaynak: dış kod incelemesi (PM işledi) + Claude Code'un release-zip bulgusu. Yeni özellik YOK — yalnız kapatma.

## A — Kurulum Güvenliği (K37, KRİTİK testli)
1. **SetupLock fail-CLOSED:** kilit DB'den okunamıyorsa (bağlantı/tablo hatası) sonuç "kilitli" kabul edilir → /setup ve /api/setup 403. Test: DB erişimi koparılmış senaryoda 403.
2. **EnvWriter overwrite yasağı:** `.env` mevcutsa HTTP kurulum akışından ASLA üzerine yazılmaz; `.env` varlığı setup'ı ayrıca kilitler (DB kilidinden bağımsız birinci katman). Test: mevcut .env + setup denemesi → 403/422, dosya bit değişmemiş.
3. **Setup HTTPS + secure çerez:** `APP_ENV=production`'da sır girilen adımlar (DB, admin, TOTP) HTTPS değilse İLERLEMEZ (403 + açıklayıcı mesaj); setup oturum çerezi secure+httponly+samesite. Lokal (`APP_ENV!=production`) istisna.

## B — Veri Bütünlüğü (KRİTİK testli)
4. **ListMutationPolicy:** `completed`/`cancelled` liste DOKUNULMAZ — ürün ekleme/taşıma/silme/durum/alan düzenleme/reorder tümü 422 (`LIST_IMMUTABLE`); reopen ucu YOK (çözüm: kopyala). Test: terminal listeye her mutasyon türü 422.
5. **Transaction sarmalayıcı:** `Connection::transaction(fn)`; şu akışlar tek transaction: ürün create/update/status, bulk, list duplicate, kur PUT + rate_history, restore/purge. Test: yapay hata ile rollback (yarım kayıt kalmıyor).
6. **Reorder sıkılaştırma:** `ordered_ids` listedeki ürünlerin TAM permütasyonu olmalı (eksik/fazla/duplicate → 422).

## C — Medya Yaşam Döngüsü
7. Kalıcı silme (trash purge + permanent delete) fiziksel medya dosyalarını da siler; `bin/purge-trash.php`'ye yetim dosya GC eklenir (DB'de referansı olmayan public/media dosyaları). KRİTİK test: sil → dosya diskte yok.
8. `reencode()` dönüşü tutarlı: `{bytes, extension, mime}` birlikte (webp/avif encoder yoksa uzantı-içerik uyumsuzluğu imkânsız).
9. Migration: `product_images`'a `storage_mode` (local|remote) + `source_url`; `products.main_image` → VARCHAR(1000).

## D — K33 ↔ RequirementChecker Barışması
10. Gereksinimler moda göre: DB + PHP≥8.4 + eklentiler + (production'da) HTTPS = her modda ZORUNLU; `storage/` ve `public/media/` yazılabilirliği = arşiv modu için gerekli, **yazılamıyorsa kurulum BLOKLANMAZ** → hotlink/DB modu önerisiyle devam (uyarı kartı). Test: yazılamaz ortamda sihirbaz sonuna kadar gidiyor.

## E — Operasyon
11. `app_logs` retention: housekeeping cron (`bin/purge-trash.php` genişletilir veya `bin/housekeeping.php`) `LOG_RETENTION_DAYS` uygular. `.env.example`'dan bayat `EXPORT_PATH` kaldırılır (K33: export stream — yorum satırıyla not).
12. **CI'ya gerçek MySQL 8.4 job'ı:** service container → temiz migration → kritik HTTP akışları (kurulum, auth, liste+ürün+para) → şema doğrulamaları. (Üç canlı-koşum hatasının kanıtladığı boşluk.)

## F — Belge/Sözleşme Tek Gerçek
13. docs/10 koda eşitlenir: 422 standardı kalır (409 satırı düzeltilir), sayfalama "Faz 4" notuna iner, gerçek uç davranışları yansıtılır.
14. **docs/TECH-BASELINE.md** oluştur (PHP 8.4 · Slim 4 · MySQL 8.4 · React 19 · TS · Vite · Node LTS · MV3 · PHPUnit · PHPStan lvl6); README/CLAUDE/diğer belgeler sürüm yazmak yerine buna referans verir; README (hâlâ "Private/Faz 0" diyor) ve yol haritası gerçek duruma çekilir.
15. **docs/07 §4 release-zip listesi düzeltilir** (Claude Code bulgusu): `setup/`, `bin/`, `.env.example`, `public/panel/` build çıktısı ve tüm çalışma-zamanı dosyaları dahil — "zip'ten kurulabilirlik" tanımı netleşir.
16. docs/08'e K37 satırı; CHANGELOG.

## Kapsam DIŞI
Yeni özellik, export üretimi, paylaşım, capture, reopen ucu, GTİP kolonlarının repository'ye bağlanması (Faz 3'ün ilk maddesi).

## Kabul (K35)
- [ ] A/B/C-7 kritik testleri yeşil (fail-closed, .env koruması, HTTPS kapısı, immutability, rollback, medya GC).
- [ ] Yazılamaz-ortam sihirbaz akışı D-10'a göre uçtan uca (blokaj yok, hotlink modu önerisi).
- [ ] CI üç job (PHP + panel + **MySQL entegrasyon**) yeşil; PHPStan lvl6 0; CS-Fixer 0.
- [ ] Release zip tarifiyle temiz ortama kurulum SİMÜLE edildi (zip → aç → sihirbaz → panel açılıyor).
- [ ] **İE#8'in eksik kabul kanıtı bu raporla birlikte gelir:** ekran görüntülü manuel kabul listesi (telefon + masaüstü, is-emri-08 kabul maddeleri ✓/✗).

## Teslim
PR + ÇIKTI RAPORU. Bu raporun kabulü = Faz 1 resmî kapanışı → üretim kurulum günü planlanır.
