# TEMİZ KURULUM RUNBOOK'U — v0.12.1-beta

> **Karar (Ürün Sahibi, 23 Ağustos 2026):** canlıdaki v0.11.4 kurulumu ve TÜM
> verisi silinir, sistem **sıfırdan** kurulur. Amaç çift: temiz veri zemini +
> kurulum sürecinin (gereksinim denetimi, sihirbaz, kilit) **gerçek saha
> testi**. Veri göçü YOKTUR — `bin/sifirla-ve-kur.php` planı iptal edilmiştir.
>
> **Bu belgeyi Ürün Sahibi uygular.** SSH/Terminal YOKTUR (MegaTR paylaşımlı
> hosting). Kullanılan üç araç: **cPanel Dosya Yöneticisi**, **phpMyAdmin**,
> **Cron Jobs**. Her adımın sonunda bir **⟲ Ters giderse** satırı vardır.
> Sıra atlanmaz: her adım bir öncekinin çıktısına dayanır.

---

## 0. Paket bilgileri

| | |
|---|---|
| Sürüm | **v0.12.1-beta** (v1.0 adı İE#21 kapanışına saklı) |
| Zip | `dist/tedarikapp-v0.12.1-beta.zip` |
| Boyut | 30,60 MB · 2.165 dosya (+ `MANIFEST.txt`) |
| SHA-256 | `bedb182ed1a2db7ce793ea55176c68757dd4352ffcd9c5e3f0e42353495d4e28` |
| Panel damgası | `v3-faz1 @ eeb25f8` · **temiz çalışma kopyası** |
| Migration | 26 dosya (`0001`–`0026`) — boş veritabanına sıra ile uygulanır |
| Sözlükler | `config/sozluk-zh-tr.php` · `config/sozluk-en-tr.php` **pakette** |
| Vendor | üretim kipi (`--no-dev`; `phpunit` yok) |

Paketin içinde **`config.php` ve `.env` YOKTUR** — sırlar zip'e girmez, kurulum
sihirbazı `config.php`yi kendisi üretir.

---

## a) SİGORTA YEDEĞİ — silmeden önce

Yedeği **kullanmayı planlamıyoruz**; veri test verisidir. Yine de alınır: geri
dönüşü olmayan bir işlemi yedeksiz yapmak, verinin değerli olup olmamasından
bağımsız olarak yanlıştır.

1. **Veritabanı:** cPanel → **phpMyAdmin** → soldan veritabanını seç →
   **Dışa Aktar (Export)** sekmesi → yöntem **Hızlı (Quick)**, biçim **SQL** →
   **Git**. İnen `.sql` dosyasını bilgisayarda **`yedek-<tarih>.sql`** adıyla
   saklayın. Silmeyin — 30 gün durur.
2. **`config.php`:** cPanel → **Dosya Yöneticisi** → uygulama kökü →
   `config.php` → sağ tık **Download**. Bu dosya **DB adı, DB kullanıcısı ve DB
   şifresini** taşır; yeni kurulumda aynı bilgileri gireceğiz. Bir kenara
   kaydedin, kimseyle paylaşmayın.
3. Dosya listesini not edin: kökte `public/`, `app/`, `vendor/`, `storage/`,
   `config.php` görüyor olmalısınız (v0.11.4 yerleşimi).

> **Not — APP_KEY:** Eski `config.php` içindeki `APP_KEY`, eski yedeklerin
> şifresini çözen anahtardır. Yeni kurulum **YENİ** bir APP_KEY üretir; eski
> `.sql.enc` yedekleri yeni anahtarla AÇILMAZ. Bu yüzden 1. adımdaki yedeği
> phpMyAdmin'den **şifresiz `.sql`** olarak alıyoruz.

**⟲ Ters giderse:** Export "Fatal error / timeout" verirse: Export ekranında
**Özel (Custom)** → "Compression: zipped" seçip tekrar deneyin. Yine olmazsa
tabloları ikiye bölerek (önce `products`, sonra kalanlar) iki kez dışa aktarın.
İndirme tamamlanmadan **hiçbir silme adımına geçmeyin**.

---

## b) ESKİ KURULUMUN SİLİNMESİ

**Veritabanının kendisi ve DB kullanıcısı SİLİNMEZ.** Yeni kurulum aynı
bilgilerle bağlanacak; cPanel'de DB oluşturmak tek elle yapılan adımdır ve onu
tekrar yapmaya gerek yoktur.

### b1. Dosyalar

cPanel → **Dosya Yöneticisi** → subdomain kökü
(`tedarikapp.tilbehometoptan.com/`). Bu klasörün **içindeki her şeyi** silin:

- `public/` · `app/` · `vendor/` · `bootstrap/` · `setup/` · `migrations/` ·
  `storage/` · `config/` · `config.php` · `composer.json` · `MANIFEST.txt` ve
  kökteki tüm dosyalar (gizli dosyalar dahil — Dosya Yöneticisi ayarlarından
  **"Show Hidden Files"** açık olsun, `.htaccess` de gidecek).
- **Klasörün kendisini SİLMEYİN** — subdomain o klasöre bağlıdır; silinirse
  subdomain kırılır.

> Silmeden önce: Dosya Yöneticisi'nde **Select All → Compress → zip** yapıp
> `eski-kurulum.zip`i indirmek 2 dakikadır ve b) adımının sigortasıdır.

### b2. Tablolar

İki yol var, **birincisi tercih edilir**:

- **Yol 1 (önerilen) — sihirbaza bırakın.** Tabloları şimdi silmeyin. Kurulum
  sihirbazının **4. adımında** "Temiz kurulum — tabloları sıfırla ve kur"
  düğmesi vardır: kutuya birebir **`SIFIRLA`** yazılınca veritabanındaki TÜM
  tabloları düşürüp sıfırdan kurar. Bu yol, kurulum akışını da test ettiği için
  bu operasyonun amacına daha uygundur.
- **Yol 2 — phpMyAdmin'den elle.** phpMyAdmin → veritabanı → **Yapı
  (Structure)** sekmesi → en altta **Tümünü işaretle** → açılır menüden
  **Sil (Drop)** → onay. Uyarı çıkarsa "Enable foreign key checks" kutusunun
  işaretini kaldırın.

**⟲ Ters giderse:**
- *Yanlış klasörü sildim:* subdomain kökü ile `public_html` karıştırılmışsa
  başka siteler etkilenir. Hemen cPanel → **Yedeklemeler / JetBackup** →
  ilgili klasörün dünkü kopyasını geri yükleyin; MegaTR'de günlük yedek vardır.
- *"Drop" hata veriyor (foreign key):* önce yabancı anahtar denetimini kapatın
  (phpMyAdmin → SQL sekmesi → `SET FOREIGN_KEY_CHECKS=0;` çalıştırın), sonra
  Drop'u tekrarlayın.
- *Tablolar silindi ama dosyalar duruyor:* sorun değil, sırayı bozmaz —
  b1'i tamamlayıp devam edin.

---

## c) YENİ PAKETİN YÜKLENMESİ

1. **Yükleme:** Dosya Yöneticisi → subdomain kökü → **Upload** →
   `tedarikapp-v0.12.1-beta.zip`. Yükleme bitince dosya boyutunun **30,60 MB**
   olduğunu listede doğrulayın (yarım yüklenen zip bozuk açılır).
2. **Açma:** zip'e sağ tık → **Extract** → hedef yol **subdomain kökünün
   kendisi** olmalı (örn. `/home/<kullanici>/tedarikapp.tilbehometoptan.com`).
   Extract penceresi bazen zip adında bir alt klasör önerir — **öneriyi silin**,
   kökü bırakın.
3. **Yerleşim denetimi.** Extract sonrası kökte şunlar OLMALI:
   ```
   app/  bin/  bootstrap/  config/  migrations/  public/  setup/  storage/  vendor/
   composer.json   MANIFEST.txt
   ```
   `public/` içinde `index.php` ve `panel/index.html` bulunmalı.
4. **Zip'i silin** (kökte durursa indirilebilir hale gelir).
5. **`config.php` YAZMAYIN.** Bu sunucuda PHP diske yazamaz; sihirbaz dosyanın
   içeriğini **ekranda üretecek**, siz Dosya Yöneticisi ile kaydedeceksiniz
   (d3 adımı). İçeriği şimdiden elle yazmak, sihirbazın ürettiği APP_KEY ile
   uyuşmazlığa yol açar.
6. **İzinler:** `public/media` ve `storage` klasörlerine yazma izni gerekir.
   Dosya Yöneticisi → klasör → sağ tık **Permissions** → `0755` yetmezse
   `0777`. `public/media/.htaccess` dosyasının **yerinde olduğunu** mutlaka
   doğrulayın (PHP çalıştırmayı kapatır) — yoksa 0777 vermeyin.

**⟲ Ters giderse:**
- *Extract yanlış dizine açıldı* (örn. `kok/tedarikapp-v0.12.0-beta/app/...`):
  yanlış klasörün İÇİNDEKİ her şeyi seçip **Move** ile bir üst dizine taşıyın,
  sonra boşalan klasörü silin. Alternatif: yanlış açılanı komple silip zip'i
  yeniden Extract edin (zip hâlâ duruyorsa).
- *Site "500 Internal Server Error" veriyor:* neredeyse her zaman kökteki
  `.htaccess` PHP sürüm satırıdır. cPanel → **MultiPHP Manager** → subdomain →
  **PHP 8.3** seçin (varsayılan 8.1.34'e düşerse de çalışır ama 8.3 önerilir).
- *Site "Panel henüz derlenmemiş" (503) diyor:* `public/panel/` eksik açılmış —
  Extract'i tekrarlayın.
- *Sayfa bomboş / beyaz:* `vendor/` eksiktir. `MANIFEST.txt` ile karşılaştırın;
  eksikse zip yeniden yüklenir.

---

## d) KURULUM SİHİRBAZI (web'den)

Tarayıcıdan **https://tedarikapp.tilbehometoptan.com** adresine gidin.

> **v0.12.1 ile değişen şey (D2-REV):** sihirbaz artık açılışta **TAM TEŞHİS**
> koşuyor — dosyalar, ayar dosyası, veritabanı, tablolar, migration defteri ve
> sürüm. Üstte bir **durum rozeti** ("Teşhis: … bulundu") ve altında yalnız o
> duruma uyan seçenekler çıkıyor. Bu yüzden bu bölümün eski "ters giderse"
> satırlarının çoğu buradan KALKTI: artık ekranda karşılığı var.
>
> Sekiz durumun hepsi sihirbazın İÇİNDEN çözülür: kurulum yok · sağlıklı kurulum ·
> yarım kurulum · ayar dosyası kayıp/bozuk · paket dosyaları eksik · migration
> yarım · sürüm uyuşmazlığı (güncelleme) · veritabanına erişilemiyor.
> Her ekranda **"Teşhisi yeniden çalıştır"** düğmesi vardır.

**Bizim senaryomuzda** (b adımında her şey silindi) teşhis "**Kurulum bulunamadı**"
diyecek ve doğrudan normal akışa girecektir:

| Adım | Ne yapılır | Ne girilir |
|---|---|---|
| **1. Gereksinimler** | PHP sürümü/eklentileri, HTTPS, `public/media` ve `storage` yazma izni | Kırmızı satır varsa düzeltip **Yeniden denetle** |
| **2. Veritabanı** | Bağlantı testi | (a) adımında indirdiğiniz **eski `config.php`**'deki `DB_HOST` (`localhost`), `DB_PORT` (`3306`), `DB_NAME`, `DB_USER`, `DB_PASS` |
| **3. Ayarlar** | Panel adresi sorulur, APP_KEY kriptografik üretilir | `https://tedarikapp.tilbehometoptan.com` |
| **3b. Dosyayı siz kaydedin** | Sunucu yazamadığı için içerik ekranda gösterilir | **İçeriği kopyala** → File Manager → kökte **`config.php`** → yapıştır → kaydet → **"Kaydettim, doğrula"** |
| **4. Tablolar** | **Tabloları oluştur** — 26 migration sırayla koşar | Eski tablo kaldıysa: kutuya **`SIFIRLA`** + **Temiz kurulum** |
| **5. Yönetici + 2FA** | Hesap açılır, QR çıkar | Kullanıcı adı + güçlü şifre; QR'ı Authenticator ile okutup 6 haneli kodu girin |
| **6. Kurtarma kodları** | Tek seferlik kodlar | **Kâğıda/parola yöneticisine** kaydedin — bir daha gösterilmez |
| **7. Bitti** | Sihirbaz kilitlenir, sürüm kaydı yazılır | Panele yönlenir |

### Sihirbaz başka bir durum bulursa

| Rozet | Ne demek | Ekrandaki yol |
|---|---|---|
| **Sağlıklı kurulum bulundu** | Kilit var, tablolar tam | "Panele git" **veya** sahiplik doğrulaması + "Temiz kurulum" |
| **Yarım kalmış kurulum** | Ayar dosyası var, kilit yok | "Kaldığım adımdan devam et" / "Temiz kurulum" |
| **Ayar dosyası kayıp/bozuk** | `config.php` yok ya da alanları eksik | "Ayar dosyasını yeniden oluştur" — DB bilgileri yeniden sorulur |
| **Paket dosyaları eksik** | MANIFEST'e göre eksik/bozuk dosya | Liste ekranda; paketi yeniden yükleyip **"Paketi yükledim — yeniden tara"** |
| **Veritabanı tabloları eksik** | Migration yarım | "Bekleyenleri tamamla" / "Temiz kurulum" |
| **Yeni sürüm yüklenmiş** | Dosyalar yeni, şema eski | "Güncellemeyi çalıştır" — **her sürüm güncellemesinin resmî yolu budur** |
| **Veritabanına erişilemiyor** | Bağlantı kurulamıyor | Hata sınıflandırılır (yanlış şifre / sunucu yok / DB yok) ve ilgili alana odaklı düzeltme formu açılır |

### Sahiplik doğrulama

Kurulu bir sisteme dokunan her yol önce kimlik sorar. **Birincil yol yönetici
e-postası + şifresidir** (2FA sorulmaz — bu bir oturum açma değil, sahiplik
kanıtıdır). Şifrenize erişemiyorsanız aynı ekranda katlanır **"APP_KEY ile
doğrula"** bölümü vardır ve dosyanın nereden açılacağını tarif eder. Doğrulama,
bu tarayıcıya özel **15 dakikalık** bir bilet verir; kurulum kilidi silinmez.
Yanlış denemeler artan beklemeye tabidir ve hepsi işlem günlüğüne yazılır.

**⟲ Ters giderse (sihirbazın çözemediği tek şey):**
- *Sihirbaz sayfası hiç açılmıyor (500 / beyaz ekran):* bu bir PHP/paket
  sorunudur, teşhis kodu çalışamıyor demektir. cPanel → **MultiPHP Manager** →
  subdomain → **PHP 8.3**. Düzelmezse paketi yeniden Extract edin.
- *Veri kurtarma:* sihirbaz veri KURTARMAZ. Yedekten dönüş phpMyAdmin →
  **İçe Aktar (Import)** ile (a) adımındaki `.sql` dosyasından yapılır.

## e) KURULUM SONRASI KONTROL LİSTESİ

Sırayla yapın; her satır bir kanıt üretir.

### e1. Migration durumu
Panelde **Ayarlar → Sistem** (ya da `/api/system/status`) → **bekleyen
migration: 0** olmalı. phpMyAdmin'de tablo listesinde şunlar GÖRÜNMELİ:
`platforms` · `listings` · `listing_price_tiers` · `jobs` · `translation_cache`.
`migrations` tablosunda **26 satır** olmalı.

**⟲ Ters giderse:** panel "Güncelleme tamamlanmalı" ekranı gösterirse tek tık
**migrate** düğmesini kullanın. Hata mesajı bir migration adı veriyorsa o adı
raporlayın — yarıda kalmış migration elle kurcalanmaz.

### e2. Kuyruk cron'u
cPanel → **Cron Jobs** → iki girdi (yol kendi kullanıcı adınızla):
```cron
15 3  * * *  /usr/local/bin/php /home/<kullanici>/tedarikapp.tilbehometoptan.com/bin/backup.php >/dev/null 2>&1
*/5 * * * *  /usr/local/bin/php /home/<kullanici>/tedarikapp.tilbehometoptan.com/bin/kuyruk.php >/dev/null 2>&1
```
**⟲ Ters giderse:** kuyruk birikiyorsa cron koşmuyordur. Geçici olarak çıktıyı
dosyaya alıp bakın:
`/usr/local/bin/php .../bin/kuyruk.php --durum > /home/<kullanici>/kuyruk.txt 2>&1`

### e3. Çeviri anahtarı + bağlantı testi
Panel → **Ayarlar → Çeviri** → sağlayıcı (varsayılan **DeepSeek**) + API
anahtarı → **Kaydet** → **Bağlantıyı test et**.
- Model alanı **boş bırakılabilir**: gri yazıyla görünen varsayılan model
  kullanılır (`deepseek-v4-flash`).
- Test **başarısızsa sağlayıcının hatası ekranda görünür** (`model_not_found`,
  `invalid_api_key` gibi) — test asla sessizce yedeğe düşmez. Ne yazıyorsa onu
  raporlayın.

### e4. Sözlük paketle geldi mi
Dosya Yöneticisi → kökte `config/sozluk-zh-tr.php` ve `config/sozluk-en-tr.php`
**var olmalı**. Boyutları 0 byte olmamalı. Bu dosyalar çeviri katmanının
terim tutarlılığını sağlar; eksikse çeviri çalışır ama terimler oynar.

**⟲ Ters giderse:** zip yeniden Extract edilir (sözlükler pakette doğrulandı).

### e5. Kur değerleri
Panel → **Ayarlar → Kur** → güncel değerleri **elle** girin:

| Alan | Değer |
|---|---|
| ¥ (CNY) | **7,15** |
| $ (USD) | **48,05** |

Kur listeye **kilitlenir** (K4): girdikten sonra açılan yeni listeler bu kuru
taşır, eski listeler kendi kilitli kurunu korur. TL değerleri veritabanına
yazılmaz, her zaman "orijinal fiyat × listenin kilitli kuru" olarak hesaplanır.

### e6. Kategori ağacı (Görev #8B)
**Toplu içe aktarım mekanizması YOKTUR** — bugün kategoriler yalnız panelden
tek tek eklenir (`Ayarlar → Kategoriler`). İki seçenek:
- **Şimdi:** ağacın ilk seviyesini elle girin (10–15 kalem, ~10 dakika).
- **Sonra:** toplu içe aktarım **İE#21'e madde olarak yazılsın** — CSV/JSON
  yükleme ucu + panel ekranı gerekir. Öneri: `POST /api/categories/import`,
  idempotent (aynı ad iki kez eklenmez).

### e7. ALTIN SET SINAVI — çeviri ölçümü (50 kayıt, TR/EN ayrı tablo)

Sınav **ölçer, üretmez**. Sırası şudur:

1. **Kayıt topla:** Chrome eklentisiyle 1688'den **50 ürün** yakalayın (Gelen
   Kutusu'na düşerler), bir listeye taşıyın.
2. **Kuyruk işlesin:** çeviri işleri kuyruğa girer; cron 5 dakikada bir koşar.
   50 kayıt tipik olarak 2–3 turda biter. Elle hızlandırmak için Cron Jobs'a
   **tek seferlik** bir girdi ekleyip 5 dakika sonra silebilirsiniz.
3. **Sınavı koş (cron + dosya yöntemi).** cPanel → Cron Jobs → **Once per five
   minutes** seçip şu komutu ekleyin, çıktı ana dizine yazılır:
   ```cron
   */5 * * * * /usr/local/bin/php /home/<kullanici>/tedarikapp.tilbehometoptan.com/bin/ceviri-sinavi.php > /home/<kullanici>/sinav.txt 2>&1
   ```
   Bir tur geçtikten sonra **cron girdisini SİLİN**, Dosya Yöneticisi'nden
   `sinav.txt` dosyasını indirin/görüntüleyin.
4. **Çıktı:** iki ayrı tablo basar — **TABLO 1 (TÜRKÇE)** ve **TABLO 2
   (İNGİLİZCE)**, her satırda ürün no · orijinal Çince başlık · çeviri ·
   sağlayıcı; başta **TR kapsama / EN kapsama** yüzdeleri. Bu dosyanın tamamı
   sınav kanıtıdır, olduğu gibi raporlanır.

   Seçenekler: `--adet=100` (kapsam), `--liste=<id>` (tek liste),
   `--eksik` (yalnız çevirisi eksik olanlar).

**⟲ Ters giderse:**
- *"Sınava girecek ürün yok":* yakalanan ürünlerin Çince orijinal başlığı yok
  demektir — eklenti yakalaması yarım kalmıştır, yeniden yakalayın.
- *Kapsama düşük (TR veya EN eksik):* önce `bin/kuyruk.php --durum` (aynı cron
  yöntemiyle dosyaya alın). Kuyruk boşsa sorun sağlayıcıdadır → e3'teki
  **Bağlantıyı test et**. Kuyruk doluysa cron koşmuyordur → e2.
- *Yalnız EN eksik:* çeviri ayarlarında EN üretimi kapatılmış olabilir;
  Ayarlar → Çeviri'de İngilizce anahtarı **açık** olmalı (varsayılan açık).

### e8. Kapanış duman testi
- `/panel` açılıyor, giriş + 2FA çalışıyor.
- Yeni liste + elle ürün ekleme çalışıyor.
- Bir liste paylaş → `/liste/<token>` dışarıdan açılıyor, erişim anahtarı
  kapısı çalışıyor.
- Excel ve PDF çıktısı üretiliyor.
- Chrome eklentisi: Ayarlar → Güvenlik'ten **yeni token** üretip eklentiye
  girin (eski token silinen kurulumla gitti) ve bir ürün yakalayın.

**⟲ Ters giderse:** eklenti yakalaması 401 veriyorsa `public/.htaccess`
içindeki `CGIPassAuth On` satırı Extract sırasında ezilmiş olabilir (cgi-fcgi
`Authorization` başlığını iletmez). Dosyayı pakettekiyle karşılaştırın.

---

## Bilinen not

Pakette `bin/goc-ilan.php` bulunur. Göç iptal edildiği için **kullanılmaz** ve
temiz kurulumda çalıştırılmasına gerek yoktur; bir sonraki sürümde paketten
çıkarılacaktır.
