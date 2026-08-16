# İŞ EMRİ #6 — Faz 1C: Liste/Ürün Veri Katmanı, Durum Makinesi, MoneyService
Faz: Faz 1C · Modül: M2+M3+M5 (backend) · Dal: `is-emri-6-veri-katmani` (PR aç; merge PM onayıyla)

> ÖN ŞART: PR #4 merge. Oku: docs/04 (§2 şema, §2b durum makinesi, §2d doğrulama), docs/10, docs/08 (K22–K25, K29, K31), CLAUDE.md.

## Hedef
Listeler ve ürünler API üzerinden eksiksiz yaşıyor: CRUD + kopyalama + görünümler + çöp kutusu + durum makinesi + kilitli kurla kuruş-doğru para hesabı. React paneli (Faz 1D) bu API'nin üzerine sıfır sürprizle oturacak.

## Yapılacaklar
1. **Migration'lar (K23: 1 DDL = 1 migration):** `lists` (period, visibility, yuan_rate/usd_rate DECIMAL(12,4), rate_locked_at, revision, share_token_hash, share_expires_at, updated_at, archived_at, deleted_at), `products` (docs/04 §2'deki TÜM alanlar + koli içi adet alanı `units_per_carton` NULL, updated_at, deleted_at), `product_images`, `product_status_history` (K-kararı: durum tarihçesi ayrı tablo), `exports` (snapshot_json dahil — uçları Faz 2'de), `activity_log`'a actor alanları (ALTER). K22: tüm durum kodları İngilizce enum (`draft/sent/ordered/completed/cancelled`, `to_order/ordered/in_transit/received/cancelled`; Türkçe yalnız UI sözlüğünde).
2. **MoneyService (K29, test-first K14):** birim fiyat × adet, Yuan→TL ve USD→TL çevirimi (listenin kilitli kuru), satır/genel toplam; K24: birim DECIMAL(12,4), satır toplamı bcmath HALF_UP 2 hane. Altın testler: ¥9,00×7,04=₺63,36 · ¥12,00×7,04=₺84,48 · 3 kalemli liste TOPLAM satırı. Controller/başka serviste bcmath çağrısı YASAK (PHPStan/inceleme ile denetlenir).
3. **StateMachine servisi:** docs/04 §2b geçiş matrisi; geçersiz geçiş → 422 + izinli geçiş listesi (docs/10). Tek adım geri alma kuralı dahil. Her geçiş `product_status_history` + activity_log'a yazar.
4. **API uçları (docs/10'a birebir; hepsi Auth+CSRF korumalı):** lists CRUD + `POST /lists/{id}/duplicate` + görünürlük değiştirme (aktif/pasif/arşiv) + soft delete/restore; products CRUD + `PATCH /products/bulk` (taşı/durum/sil) + sıralama; çöp kutusu listesi + geri al + kalıcı sil; kur güncelleme kuralı: kur listeye oluşturmada kilitlenir, `sent` sonrası kur alanları değiştirilemez (422).
5. **Çöp kutusu temizliği:** `bin/purge-trash.php` — 30 günü dolan soft-delete kayıtlarını kalıcı siler (cron adayı; runbook'a not).
6. **Doğrulama:** docs/04 §2d sınırları backend'de; K22 gereği hata mesajları makine kodlu.
7. **Belge:** docs/08'e K32 satırı ekle: "Capture sözleşmesi Faz 3 iş emrinde TEK SEFERDE v2'ye revize edilecek (parser raporları + F30 teknik profil alanları); o güne kadar §2c sabittir". CHANGELOG güncelle.

## Kapsam DIŞI
React paneli, export uçlarının içi (xlsx/pdf üretimi — Faz 2), paylaşım sayfası, capture/Gelen Kutusu, price_history (F9).

## Kabul Kriterleri
- [ ] Tüm yeni tablolar docs/04 §2 ile birebir; enum'lar İngilizce (K22); migration'lar temiz DB'de ve mevcut DB üzerinde (İE#5 kurulumu) sorunsuz koşuyor (`/api/system/migrate` ile de).
- [ ] MoneyService altın testleri kuruşu kuruşuna geçiyor; bcmath yalnız MoneyService'te.
- [ ] Geçersiz TÜM durum geçişleri testte 422 (matris tam tarama); geçerli geçişler history+activity'ye düşüyor.
- [ ] `sent` listede kur değişikliği 422; kopyalanan liste GÜNCEL ayar kurunu alır ve yeniden kilitler.
- [ ] Soft delete: silinen liste normal uçlarda görünmez, çöp kutusunda görünür, restore çalışır; purge script 30 gün kuralına uyar (testte zaman sabitlenir).
- [ ] CI yeşil; PHPStan lvl6 0; CS-Fixer 0; composer audit temiz; docs/10 ile uç davranışları birebir.

## Teslim
PR aç, ÇIKTI RAPORU + örnek istek/yanıt dökümleri (liste oluştur → 3 ürün ekle → durum ilerlet → topla → kopyala akışının gerçek çıktıları).
