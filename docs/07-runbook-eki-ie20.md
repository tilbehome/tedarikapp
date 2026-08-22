# Runbook eklemeleri — İE#20 (v0.11.5 sonrası)

> Bu dosya `docs/07-deploy-runbook.md`in EKİDİR. İçeriği bir sonraki runbook
> gözden geçirmesinde ana belgeye taşınacaktır; şimdilik ayrı durması, canlı
> kurulum sırasında aranan bilginin tek yerde toplanmasını sağlar.

## 1. Yeni cron girdisi — KUYRUK (İE#20 C3)

Canlıda **iki** zamanlanmış görev olur. Tek-cron ilkesi bilinçli olarak bu tek
istisnayla genişletildi: yedek gecede bir yeter, kuyruk yetmez (çeviri işi
sabaha kadar bekleyemez).

```cron
# 1) Gecelik yedek + bakım (mevcut — DEĞİŞMEDİ)
15 3 * * *  /usr/local/bin/php /home/<kullanici>/tedarikapp/bin/backup.php >/dev/null 2>&1

# 2) İş kuyruğu (YENİ) — 5 dakikada bir
*/5 * * * * /usr/local/bin/php /home/<kullanici>/tedarikapp/bin/kuyruk.php >/dev/null 2>&1
```

Koşum kendi süresini kollar (varsayılan 50 sn), yani iki koşum normalde üst üste
binmez. Binse bile kuyruk sahiplenmesi aynı işin iki kez çalışmasını engeller.

**Cron çalışıyor mu?** Panel → Ayarlar → Kuyruk durumu. "En eski bekleyen"
sürekli büyüyorsa cron koşmuyordur. Elle denemek için:

```bash
php bin/kuyruk.php --durum     # yalnız sayıları yazar, iş almaz
php bin/kuyruk.php             # bir tur işler
```

## 2. APP_KEY EMANET PROSEDÜRÜ (İE#19 G8 — zorunlu)

`APP_KEY` bir ayar değil, bir **kimliktir**. Şunların hepsi ona bağlıdır:
yedek şifrelemesi, 2FA secret'ları, oturum ve paylaşım imzaları, erişim anahtarı
özetleri, çeviri sağlayıcı anahtarı.

**Kaybedilirse yedekler de açılamaz** — çünkü yedek de onunla şifrelidir. Bu
yüzden anahtar, yedeklerin durduğu yerden **AYRI** saklanır:

1. `config.php` içindeki `APP_KEY` değerini kopyalayın (64 hex karakter).
2. **Parola yöneticisine** kaydedin (kayıt adı: `tedarikapp APP_KEY — <alan adı>`).
3. İkinci kopyayı **çevrimdışı** tutun: kapalı zarf ya da kasa. Yedeklerin
   bulunduğu diskte/bulutta TUTMAYIN — ikisi birlikte kaybolursa geri dönüş yok.
4. **Yılda bir tatbikat:** zarfı açın, değeri `config.php` ile karşılaştırın,
   tarih atıp geri koyun. Okunamayan bir emanet, emanet değildir.

Anahtar değişirse (zorunlu kalmadıkça DEĞİŞTİRMEYİN): eski yedekler açılamaz,
yöneticiler authenticator'ı yeniden kurar, paylaşım erişim anahtarları yenilenir.
Ayrıntı: `docs/config-referansi.md` §3.

## 3. Yedek kapsamı genişledi (İE#19 G8)

`php bin/backup.php` artık iki dosya üretir:

| Dosya | İçerik |
|---|---|
| `yedek-<zaman>.sql.enc` | Veritabanı dökümü (şifreli) |
| `yedek-<zaman>.files.enc` | `config.php` + `storage/sozluk-*.php` (şifreli) |

İkisi birlikte yaşar, birlikte silinir. Geri yüklerken **önce dosya yedeğini**
açın (config.php olmadan DB dökümü işe yaramaz), sonra SQL'i yükleyin.

## 4. Göç sırası — İE#20 C2 (ÜRÜN ≠ İLAN)

**Sıra önemlidir ve her adım geri alınabilir:**

```bash
# 0) YEDEK — göçten önce her zaman
php bin/backup.php

# 1) Envanter (SALT OKUNUR, hiçbir şey değişmez)
php bin/veri-temizlik-raporu.php
php bin/veri-temizlik-raporu.php --json > storage/temizlik-raporu.json

# 2) Şema (idempotent)
php bin/migrate.php

# 3) PROVA — yazmaz, ne olacağını söyler
php bin/goc-ilan.php

# 4) Ürün Sahibi onayı geldikten SONRA:
php bin/goc-ilan.php --uygula
php bin/goc-ilan.php --dogrula      # sayım + örneklem karşılaştırması

# Geri dönüş (products'a DOKUNMAZ):
php bin/goc-ilan.php --geri-al --uygula
```

## 5. Release paketleme değişti (İE#19 E9)

`--panel-dal` artık **zorunludur** ve damga denetlenir:

```bash
cd frontend && npm run build        # damgayı yazar
cd .. && php bin/release.php --version=0.11.6 --panel-dal=<dal>
```

Kirli çalışma kopyasından derlenmiş panel **paketlenmez**; damgadaki commit
paketlenen ağaçla eşleşmek zorundadır. `config.php` pakete asla girmez,
`config.example.php` girer.
