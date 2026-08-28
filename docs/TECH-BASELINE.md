# TECH-BASELINE — Teknoloji Taban Çizgisi (İE#9 §F14)

> **TEK GERÇEK KAYNAK.** Sürüm ve teknoloji bilgisi BURADA yaşar; README, CLAUDE.md ve
> diğer belgeler sürüm yazmak yerine bu dosyaya referans verir. Değişiklik PM kararıdır
> (K5/K13 teknoloji sınırları geçerli) ve karar günlüğüne (docs/08) işlenir.

## Çalışma Zamanı

| Bileşen | Taban | Not |
|---|---|---|
| PHP | **8.2 – 8.4** (K84) | `composer.json` `"php": "^8.2"`. İE#22 C1'de taban 8.1'den 8.2'ye çıkarıldı: çalışma zamanı için `php>=8.2` isteyen paketler (twofactorauth v3, bacon-qr v3, zipstream-php) artık BEYANLARIYLA tutarlı çalışıyor ve **`platform-check: true`** geri açıldı — uyumsuz sürümde uygulama sessizce çalışmak yerine açılışta AÇIKÇA durur. CI bekçisi `php-taban-uyum` işidir ve **8.2 + 8.3 matrisi** koşar; 8.1 işi düştü. SUNUCU NOTU: canlı barındırma MultiPHP'de 8.1.34 sürüyordu (K84-EK) — 8.3'e geçiş KULLANICI İŞİDİR ve deploy'dan ÖNCE yapılmalıdır; sıra ters çevrilirse `platform-check` ilk istekte 500 verir |
| Web çatısı | **Slim 4** | slim/slim ^4.14 + slim/psr7 |
| Veritabanı | **MariaDB 11.4 (canlı) / MySQL 8.4 (CI)** | PDO + prepared statements; utf8mb4 zorunlu; canlıda MariaDB 11.4.12 doğrulandı (diagnostics), CI entegrasyon job'ı MySQL 8.4 container'ı kullanır — şema her ikisiyle uyumlu tutulur |
| Sunucu | cPanel paylaşımlı hosting, Apache DSO | dış istek yalnız cURL; yazma yalnız `storage/` + `public/media/` (docs/04 §7) |
| Şifreleme | **sodium VEYA OpenSSL AES-256-GCM (AEAD)** | K27/K39 — ext-sodium önerilir ama ZORUNLU DEĞİL (ea-php84'te yüklenemiyor); ext-openssl zorunlu. Kayıt ön eki (`v1s`/`v1a`) arka ucu seçer |

## Panel (Frontend)

| Bileşen | Taban | Not |
|---|---|---|
| React | **19** | react / react-dom ^19.2 |
| Dil | **TypeScript** | ^5.9, `tsc --noEmit` CI'da zorunlu |
| Derleyici | **Vite** | ^7; çıktı `public/panel/` |
| Node | **22 LTS** | CI ve geliştirme makinesi |
| Durum/istek | zustand · axios | K19 onaylı liste |
| Stil | Tailwind CSS · lucide-react | K19 onaylı liste |

## Chrome Eklentisi

| Bileşen | Taban |
|---|---|
| Manifest | **V3** |
| Çatı | **WXT** (build + zip + HMR) |
| Dil | **TypeScript** (`tsc --noEmit` CI kapısı) |
| Test | **Vitest** — fixture tabanlı parser testleri, canlı 1688 isteği YOK |
| Bağımlılık | **Çalışma zamanında SIFIR harici paket.** Bundle'a üçüncü parti kod girmez; WXT/Vitest/TS yalnız geliştirme zinciridir. CI'da `npm audit --audit-level=high` kapısı vardır (İE#19 E1). |

> **Not (İE#19 E10):** bu tablo İE#11'e kadar "vanilla JS" diyordu; eklenti o iş
> emrinde WXT+TypeScript'e taşındı ve belge geride kalmıştı. Kaynak `extension/`,
> üretim bundle'ı ve Store zip'i CI'da derlenir.

## Kalite Hattı

| Araç | Taban | Kural |
|---|---|---|
| PHPUnit | **12** | K35 test rejimi; kritik akışlar test-first |
| PHPStan | **seviye 6** | her PR öncesi 0 hata |
| PHP-CS-Fixer | **PSR-12** | her PR öncesi 0 fark |
| ESLint + Prettier | frontend | CI'da zorunlu |
| composer audit / npm audit | her PR | K19 güvenlik denetimi |

## Sürüm Sabitleme

- `composer.lock` ve `frontend/package-lock.json` repodadır; CI `composer install` /
  `npm ci` ile birebir kurar (K19).
- Onaylı kütüphane listesi: CLAUDE.md §2 (K19). Liste dışı paket PM onayı ister.
