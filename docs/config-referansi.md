# config.php Referansı (İE#19 EK-2)

> Bu belge **koddan çıkarılmıştır**: `app/Core/Config.php` içindeki
> `REQUIRED_KEYS`, `FILE_ONLY_KEYS` ve `CONFIG_PHP_DEFAULTS` sabitleri ile
> `App\Setup\ConfigWriter::generate()` şablonu esas alınmıştır. Kodda anahtar
> değişirse bu belge de değişir; çelişki görürsen **kod kazanır, belgeyi düzelt**.

## 1. İki katmanlı yapılandırma (K44)

tedarikapp'ın ayarları **iki yerde** yaşar ve bu ayrım bilinçlidir:

| Katman | Nerede | Ne bulunur | Kim değiştirir |
|---|---|---|---|
| **Dosya** | `config.php` (kök) | Yalnız önyükleme için gerekenler: veritabanı erişimi ve sırlar | Sunucu sahibi (File Manager / sihirbaz) |
| **Veritabanı** | `settings` tablosu | Panel adresi, kurlar, log sürücüsü, medya modu, belge antedi, terminoloji… | Panel kullanıcısı (Ayarlar ekranı) |

**Neden:** üretim sunucusunda uygulama köke yazamaz (`nobody`, DSO). Ayarlar
dosyada yaşasaydı hiçbir ayar panelden değiştirilemezdi. Bu yüzden dosyada
yalnız "veritabanına ulaşmak için gereken minimum" durur.

`Config::get()` sırası: **settings (DB) → dosya değeri → çağrı yerindeki varsayılan.**
Tek istisna `FILE_ONLY_KEYS`'tir (aşağıda): bunlar veritabanından **asla** okunmaz —
veritabanına erişmek için gereken bilgi veritabanından okunamaz, ayrıca sırların
DB üzerinden değiştirilebilmesi yetki yükseltme yolu açardı.

## 2. Dosyadan okunan anahtarların TAM listesi

| Anahtar | Zorunlu? | Varsayılan | Sonradan değiştirmenin etkisi |
|---|---|---|---|
| `DB_HOST` | **Evet** | — | Uygulama yeni sunucuya bağlanır. Yanlışsa panel açılmaz (503 önyükleme hatası). |
| `DB_PORT` | Hayır | `3306` | Aynı. |
| `DB_NAME` | **Evet** | — | **Veri değişir**: başka bir veritabanına bağlanmak, "verilerim gitti" görüntüsü üretir. Yedeğin geri yükleneceği ad da budur. |
| `DB_USER` | **Evet** | — | Yetkisi yetersizse migration/`CREATE` adımları düşer. |
| `DB_PASS` | Hayır* | `''` | Yanlışsa açılış 503. (*Üretimde boş parola sır denetiminden geçmez.) |
| `APP_KEY` | **Evet (üretim)** | — | Aşağıda ayrı bölüm — **en tehlikeli anahtar**. |
| `EXTENSION_TOKEN_SALT` | Hayır | APP_KEY'den türetilir | Aşağıda ayrı bölüm — **tüm eklenti token'larını kırar**. |
| `APP_ENV` | Hayır | `production` | `local` yazmak üretim sır denetimlerini gevşetir ve hata detaylarını açar. Canlıda **yazılmaz**. |

`REQUIRED_KEYS` sabiti ayrıca `APP_URL` ve `TZ` ister; ancak bunlar
`CONFIG_PHP_DEFAULTS` ile karşılanır (`APP_URL` → `https://localhost` yer tutucu,
`TZ` → `Europe/Istanbul`, `LOG_DRIVER` → `db`). **Gerçek `APP_URL` değeri `settings`
tablosundadır** ve kurulumda yazılır — paylaşım/QR linkleri oradan üretilir (E5).
`APP_URL`'i `config.php`ye yazmayın; panel Ayarlar'dan değiştirin.

### Geriye dönük uyum: `.env`

K44 öncesi kurulumlarda aynı anahtarlar `.env` dosyasındadır ve **hâlâ okunur**.
Yeni kurulum `.env` üretmez. İkisi birdeyse `config.php` kazanır.

## 3. `APP_KEY` — değiştirmeden önce okuyun

`APP_KEY`, 64 hex karakterlik ana anahtardır. Şunların **hepsi** ondan türer:

- **Yedek şifrelemesi** (`storage/backups/*.sql.enc`, `*.files.enc`)
- **2FA (TOTP) secret'larının şifrelenmesi**
- Oturum/çerez imzaları, paylaşım indirme imzaları (K58)
- Erişim anahtarı (K62) HMAC özetleri
- `EXTENSION_TOKEN_SALT` verilmemişse eklenti token tuzu

**Değiştirirseniz:** eski yedekler **bir daha çözülemez**, yöneticiler
authenticator'ı yeniden kurar, açık oturumlar düşer, paylaşım erişim anahtarları
yenilenmek zorunda kalır. Bu bir "ayar" değil, bir **kimlik**tir.

**Kaybederseniz:** yedeğiniz olsa bile geri dönemezsiniz — çünkü yedek de onunla
şifrelidir. Bu yüzden **emanet prosedürü zorunludur** (docs/07 §5b): anahtar,
yedeklerin durduğu yerden **ayrı** bir kasada (parola yöneticisi veya kapalı zarf)
saklanır ve yılda bir tatbikatla okunabilirliği doğrulanır.

## 4. `EXTENSION_TOKEN_SALT` — token kırma etkisi

Eklenti Bearer token'ları veritabanında düz saklanmaz; **tuzlu SHA-256 özeti**
saklanır. Tuz bu anahtardan gelir (verilmemişse APP_KEY'den türetilir).

**Değiştirirseniz:** veritabanındaki tüm token özetleri anlamsızlaşır. Eklenti
hiçbir uyarı vermeden **401** almaya başlar; kullanıcı "eklenti çalışmıyor" der
ama panelde hata görünmez. Çözüm: panelden **yeni token üretip** eklentiye
yeniden girmek. Yani bu anahtar, bir güvenlik olayında token'ları toptan iptal
etmenin **kasıtlı** yoludur — kazayla değiştirilecek bir değer değildir.

Verilecekse **en az 32 karakter** olmalıdır (`Config::assertProductionSecrets`).

## 5. Sık sorulanlar

**"config.php'yi güncelleme silecek mi?"** Hayır. Release zip'ine `config.php`
**hiç girmez** (paketleyici bunu doğrular ve girmişse paketi reddeder). Zip yalnız
`config.example.php` taşır ve onu üzerine yazar.

**"Yeni bir ayar eklemem gerekiyor, buraya yazabilir miyim?"** Hayır — K44 gereği
yeni ayarlar `settings` tablosuna ve panele eklenir. Dosyaya yalnız önyükleme
için gereken (veritabanına ulaşmadan bilinmesi şart olan) değerler girer.

**"Parolamda tırnak var."** PHP tek tırnaklı string kuralları geçerlidir:
`'p@ss\'w ord'`.
