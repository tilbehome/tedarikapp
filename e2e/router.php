<?php

declare(strict_types=1);

/**
 * PHP yerleşik sunucusu için yönlendirici (İE#13 Blok E — YALNIZ test/geliştirme).
 *
 * Neden gerekli: `php -S ... public/index.php` her isteği front controller'a verir;
 * o zaman `/panel/assets/index-xxx.js` gibi STATİK dosyalar da uygulamaya düşer ve
 * SPA fallback'i yüzünden JavaScript yerine HTML döner (panel tarayıcıda açılmaz).
 * Burası dosya gerçekten varsa `false` döndürür — sunucu onu olduğu gibi servis eder.
 *
 * Üretimde bu dosya KULLANILMAZ: orada Apache/LiteSpeed + public/.htaccess vardır.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';
$file = dirname(__DIR__) . '/public' . $path;

if ($path !== '/' && !str_contains($path, '..') && is_file($file)) {
    return false;
}

/*
 * KRİTİK (CI'da yaşandı): yönlendirici betikle çalışan yerleşik sunucuda
 * `SCRIPT_NAME` İSTEK YOLUNA eşittir (`/panel` gibi). index.php ise K45 gereği
 * SCRIPT_NAME'den "alt klasör öneki" çıkarır ve Slim'e taban yol olarak verir —
 * sonuç: `/panel` rotası eşleşmez, 404 HTML yanıtı `/panel`e yönlendirir ve
 * tarayıcı ERR_TOO_MANY_REDIRECTS ile döngüye girer.
 * Gerçek dağıtımda SCRIPT_NAME `/index.php`tir; burada onu birebir taklit ediyoruz.
 */
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__) . '/public/index.php';
