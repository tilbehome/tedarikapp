<?php

/**
 * ══════════════════════════════════════════════════════════════════════════
 *  tedarikapp — YAPILANDIRMA ÖRNEĞİ  (config.example.php)
 * ══════════════════════════════════════════════════════════════════════════
 *
 *  DÜZENLEME KURALLARI — okumadan değiştirmeyin:
 *
 *   1. Bu dosyayı DÜZENLEMEYİN. Kopyasını `config.php` adıyla oluşturup onu
 *      doldurun. Güncellemede release zip'i `config.example.php` dosyasını
 *      ÜZERİNE YAZAR; `config.php` pakete hiç girmez, sizin dosyanız kalır.
 *
 *   2. Normal akışta bu dosyayı ELLE doldurmazsınız: kurulum sihirbazı
 *      `config.php`yi kendisi üretir (yazma izni yoksa içeriği ekranda verir,
 *      siz File Manager ile kaydedersiniz — WordPress wp-config.php modeli).
 *      Burası acil durum ve elle kurulum referansıdır.
 *
 *   3. K44: bu dosyada YALNIZ önyükleme için gereken şeyler bulunur —
 *      veritabanı erişimi ve sırlar. Kur, log seviyesi, medya ayarları,
 *      panel adresi gibi HER ŞEY veritabanındaki `settings` tablosunda
 *      yaşar ve panelden yönetilir. Buraya yeni anahtar eklemeyin.
 *
 *   4. Dosya web'den erişilebilir OLMAMALIDIR. Kök `.htaccess` yalnız
 *      `public/` dizinini dışarı açar; uygulamayı doğru yerleştirdiyseniz
 *      bu dosya zaten erişilemez.
 *
 *   5. Değerler PHP dizisidir; tırnak içinde yazılır. Boşluk, tırnak ve
 *      ters bölü içeren parolalarda tek tırnak kullanın: 'p@ss\'w ord'
 *
 *   6. Her anahtarın ne yaptığı ve SONRADAN DEĞİŞTİRİLİRSE NE OLDUĞU
 *      docs/config-referansi.md dosyasında tek tek yazılıdır. Özellikle
 *      APP_KEY ve EXTENSION_TOKEN_SALT'ı değiştirmeden ÖNCE oraya bakın.
 *
 * ══════════════════════════════════════════════════════════════════════════
 */

return [
    // ── VERİTABANI (zorunlu) ───────────────────────────────────────────────
    // Paylaşımlı hostingde sunucu adı genellikle 'localhost'tur.
    'DB_HOST' => 'localhost',
    'DB_PORT' => '3306',
    'DB_NAME' => 'ORNEK_veritabani_adi',
    'DB_USER' => 'ORNEK_kullanici',
    'DB_PASS' => 'ORNEK_PAROLA_BURAYA',

    // ── UYGULAMA ANAHTARI (zorunlu) ────────────────────────────────────────
    // 64 hex karakter. Üretim: php -r "echo bin2hex(random_bytes(32));"
    //
    // DİKKAT — bu anahtar DEĞİŞTİRİLİRSE:
    //   • mevcut yedekler (storage/backups/*.enc) BİR DAHA ÇÖZÜLEMEZ,
    //   • 2FA secret'ları çözülemez (yöneticiler authenticator'ı yeniden kurar),
    //   • açık oturumlar ve paylaşım imzaları düşer,
    //   • erişim anahtarı (K62) özetleri geçersizleşir; anahtarlar yenilenir.
    // Anahtarı YEDEKLERDEN AYRI bir yerde saklayın (docs/07 §5b emanet prosedürü).
    'APP_KEY' => 'BURAYA_64_HEX_KARAKTER',

    // ── OPSİYONEL: EKLENTİ TOKEN TUZU ──────────────────────────────────────
    // Verilmezse APP_KEY'den türetilir. DEĞİŞTİRİLİRSE tüm eklenti token'ları
    // geçersiz olur: her kullanıcı panelden yeni token üretip eklentiye
    // yeniden girmek zorunda kalır (eklenti sessizce 401 almaya başlar).
    // 'EXTENSION_TOKEN_SALT' => '',

    // ── OPSİYONEL: ORTAM ───────────────────────────────────────────────────
    // Varsayılan 'production'. 'local' YALNIZ geliştirme makinesinde kullanılır
    // ve üretim sır denetimlerini gevşetir — canlıda ASLA yazmayın.
    // 'APP_ENV' => 'production',
];
