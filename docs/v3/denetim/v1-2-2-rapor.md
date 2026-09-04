# SERTLEŞTİRME TUR 2 = v1.2.2 — KAPANIŞ RAPORU

**Dal:** `sertlestirme-v1-2-2` ← `v3-faz1` · **Tarih:** 4 Eyl 2026 · **PR:** `sertlestirme-v1-2-2` → `v3-faz1` (MERGE YOK · PAKET YOK — PM diff denetimi bekler)

Kapsam: Blok 0 (devirler) · Blok B (yedek seti) · Blok D (medya boru hattı) · H1/H2/H3 hükümleri · #30-R ve #30-EK docs commit'leri.
Her bulgu için sıra aynıydı: **önce KIRMIZI test → kök neden → düzeltme → süit**.

---

## 1. Parça · durum · kanıt

| Blok | Parça | Durum | Kanıt (test / commit) |
|---|---|---|---|
| 0 | 0.1 K100 docblock devri | ✓ | `1964613` |
| 0 | 0.2 migration numara bekçisi | ✓ | 5 test · `62473a8` |
| 0 | 0.3 aktivite etiketleri (13 etiket + bekçi) | ✓ | 3 test · `62473a8` |
| 0 | 0.4 "+ Hızlı işlem" | ✓ | `62473a8` |
| docs | #31-R / #34 / #35 teslimleri | ✓ | `42b97a5` |
| B1 | manifest + atomik set yazımı | ✓ | 11 + 10 test · `92e323a` |
| B1 | entegrasyon: `create()` SET üretir, medya bölünür, aynı-saniye çakışması | ✓ | 7 + 4 test · `d977a15` |
| B1-ek | manifest parçaları BAĞLAR (`sira` / `toplam_parca` / `sha256`, fail-closed) | ✓ | 8 test · `e33aac3` |
| B3 | doğrulama katmanı (`YedekProvasi`) + gece provası | ✓ | 8 test · `92e323a` |
| B3 | `bin/restore.php` + `YedekGeriYukleyici` + **CI MySQL provası (çok parçalı vaka dahil)** | ✓ | 5 mysql test · `e33aac3` |
| B2 | APP_KEY emaneti (şifre yeniden, aktivite izi, kurtarma metni, kalıcı kart) | ✓ | 7 test · `e33aac3` |
| B4 | panel kartı: parçalar tek tek + SHA + manifest + Doğrula ("tümünü zip" YOK) | ✓ | 4 test · `e33aac3` |
| B5 | uzak hedef anahtarları TANIMLI, uygulanmamış (bekçi) | ✓ | 3 test · `e33aac3` |
| H1 | config eksikse set KISMİ, SQL yine yazılır (K108) | ✓ | 12 birim + 1 mysql · `10725c9` |
| H2 | K28 bekçisi: xlsx SHA-256 sabit, < 50 KB | ✓ | 3 test · `7d1747a` |
| H3→#30 | Görev #30-R prototip docs commit'i (H3 kaldırıldı) | ✓ | `0837026` · 3 dosya · kontroller geçti |
| #30-EK | portal metinleri 149 + RFQ şeması v2 + status.viewed | ✓ | `e51c275` · 5 dosya · kontroller geçti |
| D1 | yakalama indirmez; `media_pending` + sonlandırma CAS (yarış deterministik) | ✓ | 5 test (Http) |
| D2 | ana görsel kuyruğa; `GET /api/media/proxy` K47 vekili; yerel/uzak rozeti | ✓ | 7 test (Http) + vitest |
| D6 | bellek bütçesi (`ertelendi` ≠ hata) + tür bazlı devre kesici 15 dk + zirve bellek raporu | ✓ | 12 + 6 test |
| D6-ek | `HataSinifi` `MedyaEksik` kalıcılığını TİPTEN okur | ✓ | 1 test (MedyaBellekButcesiTest) |

**Kapılar (lokal):** PHPStan temiz · CS-Fixer temiz · tsc temiz · eslint temiz · vitest **242/242** · mysql grubu **16/16** · birim süiti **966/966**.
Tam doğrulama CI'nın işi (5 job); CI kırmızı çıkarsa düzeltme commit'i atılır (K7 kabul edilmiş risk).

---

## 2. Blok D — ne değişti, neden

**D1 — yakalama artık hiçbir görsel indirmez.** Eskiden ana görsel yakalama isteği içinde iniyordu (alicdn ~7,5 sn); on görselli sayfada eklenti "kaydediliyor…" derken kullanıcı bekliyordu. Şimdi `main_image` kaynak URL ile yazılır, medya işi kuyruğa girer, tur ana görseli + galeriyi indirir ve **mevcut satır CAS'ıyla** (`WHERE main_image = :eski`) sonlandırır. Test: 10 görselli yakalama, sahte indiriciye **0 çağrı**, < 2 sn. Yarış testi: indirme sürerken kullanıcı ana görseli değiştirir → CAS tutmaz, indirilen dosya silinir, seçim ezilmez.
Yan bulgu: **Gelen Kutusu'ndan taşıma yolu medya işini hiç kuyruğa yazmıyordu** — taşınan ürünün galerisi sonsuza kadar uzak kalıyordu. Kapatıldı.

**`media_pending` KOLON DEĞİL, türetilmiş alan:** "ana görsel uzak VE açık medya işi var". Kolon olsaydı `products` + `jobs` aynı gerçeği iki yerde tutar, biri geride kalırdı (turun tekrar eden teması). Ayrıca yeni migration 0037 numarasında V3-C ile çakışacaktı. Listede tek sorgu (N+1 yok).

**D2 — `GET /api/media/proxy?url=`:** indirme hattıyla **AYNI** `UrlGuard` (https, beyaz liste, açık ağ, DNS pinleme) + gerçek görsel imzası (HTML dönen kaynak 415) + `private` önbellek. Ürün nesnesi `main_image_gosterim` (çizilecek adres) ve `main_image_uzak` taşır; panelde **yerel/uzak** rozeti (çekmece + liste küçük resmi).

**D6 — bellek bütçesi:** `KUYRUK_BELLEK_BUTCESI_MB` (64). Medya işi her indirmeden ÖNCE sorar; dolunca `IsErtelendi` atar. Kuyruk **deneme hakkı yakmaz** (`deneme-1`), `hata_sinifi=ertelendi`, bildirim yok; inenler korunur, ikinci tur kalanları alır. Sınıra çarpıp ölmek yerine sınırdan önce durmak: ölen süreç iz bırakmaz.
**Devre kesici:** `KUYRUK_DEVRE_KESICI_ESIK`=5 art arda GEÇİCİ hata → o türde 15 dk yeni iş alınmaz; **tür bazlı** (medya çöktü diye çeviri durmaz), **ayarlar tablosunda** (süreç ömründeki sayaç her cron turunda sıfırlanırdı — A7 dersi); başarı sayacı sıfırlar; kalıcı hata beslemez. Açılış: kritik log + `activity_log(circuit_open)` + Sistem durumu uyarısı + NTF. Tur raporunda `ertelenen`, `atlanan_turler`, **`bellek_zirve_mb`** (`memory_get_peak_usage`).

---

## 3. B3 provası — MySQL çıktısı (ham)

Lokal MySQL 8.4, `--group mysql` (aynı test CI `mysql-integration` job'unda koşar):

```
PHPUnit 12.5.33 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.32
Configuration: C:\Users\PC\Downloads\tedarikapp\phpunit.xml

................                                                  16 / 16 (100%)

Time: 01:29.154, Memory: 46.00 MB

Backup Restore (Tests\Integration\BackupRestore)
 ✔ Yedek bos veritabanina geri yuklenir
 ✔ Yanlis anahtarla cozulemez

Migration0020Idempotent (Tests\Integration\Migration0020Idempotent)
 ✔ MigrationIkiKezKosarVeHATA VERMEZ
 ✔ Yarim kalmis kosum tamamlanir

My Sql Integration (Tests\Integration\MySqlIntegration)
 ✔ Sema kritik kolonlar dogru
 ✔ Liste silince urunler cascade ile gider
 ✔ Kurulum kilidi mysql uzerinde yazilir ve okunur
 ✔ Auth liste urun ve para akisi mysql uzerinde calisir
 ✔ ARAMA UCLARI MYSQL UZERINDE CALISIR
 ✔ D a r k o l o n b a s e l i n e u y g u l a n m i s s a y i l m a z
 ✔ S i f r e l i a n a h t a r m y s q l e y a z i l i r

Yedek Seti Geri Yukleme (Tests\Integration\YedekSetiGeriYukleme)
 ✔ T e k p a r c a l i s e t g e r i y u k l e n i r
 ✔ C o k p a r c a l i s e t b i r e b i r g e r i y u k l e n i r
 ✔ E k s i k p a r c a l i s e t g e r i y u k l e n m e z
 ✔ K i s m i s e t b a y r a k l a b i r e b i r g e r i y u k l e n i r
 ✔ B o z u k p a r c a l i s e t g e r i y u k l e n m e z

OK (16 tests, 89 assertions)
```

Çok parçalı vaka: `BACKUP_MEDIA_MAX_MB=1` + 4 × 600 KB sıkıştırılamayan yapay medya → bölme **assert ile** zorlanır (`medya_parca_sayisi > 1`) → sil → geri yükle → satır sayıları + dosya SHA-256 haritası birebir. Negatif vakalar: eksik parça → kapı kapalı, veritabanına dokunulmadı; bozuk parça → SHA tutmaz.

---

## 4. H1 — KISMİ vaka çıktısı

`config.php` yokken `BackupService::create()`:

```
=== create() sonucu ===
{ "durum": "KISMI", "eksik": ["config"], "sebep": "config.php bulunamadı", "parca_sayisi": 1 }

=== MANIFEST.json ===
{ "bicim": 1, "set_id": "18055ebf-…", "surum": "1.2.0", "sifreleme": "aes-256-gcm",
  "parcalar": [ { "ad": "veritabani.sql.enc", "tur": "sql", "sira": 1, "boyut": 62, "sha256": "80660273…" } ],
  "toplam_parca": 1, "migration_defteri": ["0035_bildirimler","0036_paylasim_anahtari_sifreli_alan"],
  "eksik": ["config"], "sebep": "config.php bulunamadı" }

=== kapiyiAc() bayraksiz ===
RED: GERİ YÜKLEME DURDURULDU — set KISMİ (eksik: config) — config.php bulunamadı.
Eksik bileşeni elle tamamlayacağınızı kabul ediyorsanız --kismi-kabul ile yeniden koşun.

=== kapiyiAc() --kismi-kabul ===
KABUL: durum=KISMI eksik=config toplam_parca=1

=== prova (parça bağı KISMİ sette de fail-closed) ===
GECERSIZ: veritabani.sql.enc diskte YOK.
```

`bin/restore.php` kuru koşuda `DURUM: KISMİ — eksik: config …` basar; `--onayla --kismi-kabul` ile geçer ve "config geri yüklenmedi, elle girilecek" yazar. `restore-test.php` aynı kapıdan geçer. Panelde set satırında **kalıcı** "KISMİ (ayarlar eksik)" rozeti; app_logs'a uyarı (cron ve elle yedekte); cron özetine "· KISMİ (config eksik)".

---

## 5. H2 — kaynak araştırması: BULUNDU (depo dışı)

`C:\Users\PC\Downloads\liste\ornek-tedarik-listesi.xlsx` — **1 154 629 bayt, 3 Eyl 11:50**, yanında aynı adlı PDF (164 KB). Panelin Excel dışa aktarımı (görsel gömülü) aynı adla kaydedilip depo köküne kopyalanmış; kodda dosyaya ad veren yol yok. İlk büyüme (19 Ağu, 34 283 bayt) Excel'de yeniden kaydetmeydi (`b0c6f1a`). Bekçi kuruldu: SHA `5f8dab53…`, < 50 KB; mesaj `git checkout` ile geri almayı söyler. docs/08'e kayıt düşüldü.

---

## 6. Sapmalar ve PM'e sorular

1. **NTF olay kodu:** devre kesici için katalogda olay yok; katalog 37 olayla kilitli (K99/K102 bekçisi). **`NTF-QUEUE-STALLED` yeniden kullanıldı** (grup kuyruk, kritik; bağlam `devre_kesici: true, is_turu`). Kendine ait bir kod (`NTF-QUEUE-CIRCUIT-OPEN`) isteniyorsa PM kararıyla katalog + `BagliOlaylar` + bekçi sayısı güncellenir — tek satır değişir.
2. **`media_pending` türetilmiş alan, kolon değil** (gerekçe §2). Kolon istenirse migration numarası V3-C yeniden numaralandırmasından sonra belirlenmeli.
3. **Vekil yeniden kodlama yapmaz** (yalnız imza denetimi): geçici gösterim için GD'yi her istekte çalıştırmak paylaşımlı hostingde gereksiz yük. Arşiv yolu (`store()`) yeniden kodlamaya devam eder.
4. **Manifest `surum` alanı `1.2.0`** yazıyor (AppVersion); sürüm damgası paket anında 1.2.2'ye çekilir — bilgi.
5. `docs/10`: ürün nesnesine 3 türetilmiş alan + `/api/media/proxy` + kuyruk/durum alanları eklendi (D1/D2/D6 emri gereği). Eklenti sözleşmesi (docs/04) **DEĞİŞMEDİ**.

---

## 7. Süit sayıları

- **birim:** 966 test · 2 837 iddia · 17 atlandı (mysql grubu DSN'siz) ✓
- **mysql:** 16 test · 89 iddia ✓ (lokal MySQL 8.4; CI `mysql-integration` aynı grubu koşar)
- **vitest:** 28 dosya · 242 test ✓
- **PHPStan** ✓ · **CS-Fixer** ✓ · **tsc/eslint** ✓
- http-1..4 ve E2E: CI

---

## 8. Kabul turu listeleri

### 8a. v1.2.2 kurulum sonrası (ÜS)
1. Ayarlar > Veri & Bakım > **Şimdi yedek al** → set satırı görünür, "Doğrula" yeşil rapor; satırı açınca parçalar + SHA + manifest indirilebilir; "tümünü zip indir" düğmesi YOK.
2. **Kurtarma anahtarı** kartı: kalıcı uyarı görünür; şifre girilmeden "Anahtarı göster" kapalı; yanlış şifre 401; doğru şifre anahtar + yönerge; aktivitede "Kurtarma anahtarı görüntülendi".
3. `config.php`'yi geçici olarak okunamaz yapıp `php bin/backup.php` → çıktıda `UYARI … KISMİ`, panelde "KISMİ (ayarlar eksik)" rozeti, app_logs'ta uyarı; sonra geri al.
4. `php bin/restore.php` (kuru koşu) → set özeti, hiçbir şey yazmaz; KISMİ sette `--kismi-kabul` uyarısı.
5. Eklentiyle **10 görselli** bir ürün yakala → anında kaydedildi; panelde ürün küçük resmi vekilden gelir, "uzak" rozeti; birkaç dakika sonra (kuyruk turu) rozet "yerel".
6. Ayarlar > Arka plan işleri: "Ertelenen" satırı yalnız > 0 iken görünür; `KUYRUK_BELLEK_BUTCESI_MB=8` ile büyük ürünü işleyince ertelenen ≥ 1 ve iş ölü rafına DÜŞMEZ.
7. `MEDIA_ALLOWED_HOSTS`'u geçici bozup 5 medya işi koştur → Sistem durumu'nda "Devre kesici açık" uyarısı + bildirim; 15 dk sonra kendiliğinden kapanır.
8. `php bin/kuyruk.php` çıktısında `zirve bellek X MB` görünür.

### 8b. V3-C (Aşama 2, `is-emri-v3c`)
1. Teklif turu makinesi: 10 durum, izinli geçişler dışı 422.
2. RFQ snapshot: tur açılınca liste satırları dondurulur; sonraki düzenleme snapshot'ı değiştirmez.
3. Teklifler menüsü + liste yaşam döngüsü stepper + gönderim günlüğü.
4. Paste-parse sunucu tarafı + Excel gidiş-dönüş (K50 determinizm).
5. Listeler merkezi + şablonlar; her yeni ekran K105 ortak bileşenleriyle doğar (defter yeşil).
6. #30-R prototipi + #30-EK metinleri kanonik seçim: Blok C emrinde (üç PM kararı bekliyor).
