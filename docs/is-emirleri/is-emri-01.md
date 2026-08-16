# İŞ EMRİ #1 — Repo iskeleti ve belge setinin kurulması
Faz: Faz 0 · Modül: — · Dal: main (ilk kurulum, istisna olarak doğrudan main)

## Hedef
`tilbehome/tedarikapp` reposu profesyonel iskeletiyle GitHub'da yayında olacak; 9 belgelik doc seti `docs/` altında, README kökte, sır yönetimi (.gitignore + .env.example) hazır.

## Ön Koşul
- Bünyamin, Claude'un (PM) hazırladığı 13 MD dosyasını (README.md + CLAUDE.md + CHANGELOG.md + 00–09 belgeleri) proje klasörünün yanına `docs-taslak/` klasörü olarak koymuş olacak.
- GitHub'da `tilbehome/tedarikapp` reposuna push yetkisi (repo yoksa private olarak oluştur).

## Yapılacaklar
1. Proje klasöründe git deposunu başlat (veya boş repo'yu klonla). Varsayılan dal: `main`.
2. Klasör iskeletini oluştur (içleri şimdilik boş, `.gitkeep` ile):
   ```
   app/            (PHP kaynak — Faz 1'de dolacak)
   public/         (giriş noktası + React build hedefi)
   public/media/   (ürün görselleri — yazılabilir olacak)
   extension/      (Chrome eklentisi — Faz 3)
   frontend/       (React kaynak — Faz 1)
   migrations/     (veritabanı şemaları)
   setup/          (kurulum sihirbazı — Faz 1)
   tests/          (PHPUnit)
   docs/
   docs/is-emirleri/
   ```
3. `docs-taslak/` içindeki dosyaları yerleştir: `README.md`, `CLAUDE.md` ve `CHANGELOG.md` repo köküne, `00-…md` – `09-…md` dosyaları `docs/` altına. Bu iş emri dosyasını `docs/is-emirleri/is-emri-01.md` olarak kaydet. CLAUDE.md'yi OKU — bundan sonraki tüm oturumlarında bağlayıcıdır.
4. `.gitignore` oluştur: `.env`, `vendor/`, `node_modules/`, `storage/`, `*.log`, `docs-taslak/`, işletim sistemi/IDE artıkları.
5. `.env.example` oluştur (değerler boş/örnek): `APP_ENV`, `APP_KEY`, `APP_URL`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `EXTENSION_TOKEN_SALT`, `TZ=Europe/Istanbul`.
6. README'deki belge linklerinin repo yapısıyla çalıştığını doğrula.
7. Commit standardına uygun tek commit: `chore(repo): proje iskeleti ve belge seti v0.1` → GitHub'a push.

## Kapsam DIŞI
- Hiçbir uygulama kodu, composer/npm kurulumu, framework iskeleti YAZILMAYACAK.
- Belgelerin içeriğinde değişiklik YAPILMAYACAK (yazım hatası dahi görülürse rapora not düşülür, PM karar verir).

## Kabul Kriterleri
- [ ] Repo GitHub'da, `main` dalında, private.
- [ ] 10 belge `docs/` altında + README, CLAUDE.md ve CHANGELOG.md kökte, linkler çalışıyor.
- [ ] `.gitignore` ve `.env.example` mevcut; repoda hiçbir sır yok.
- [ ] Klasör iskeleti eksiksiz.
- [ ] Tek, standarda uygun commit.

## Test
- `git log --oneline` çıktısı ve GitHub'da dosya ağacının görünümü rapora eklenir.

## Teslim
Push sonrası ÇIKTI RAPORU şablonuyla (bkz. docs/00-calisma-protokolu.md, bölüm 4) rapor üret; Bünyamin raporu PM'e iletecek.
