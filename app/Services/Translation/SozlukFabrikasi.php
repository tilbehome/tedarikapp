<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * SÖZLÜK KURULUMUNUN TEK YERİ (v1.2.1 A6).
 *
 * NEDEN VAR: sözlük iki yerde ayrı ayrı kuruluyordu ve ikisi AYRI ŞEYLER
 * okuyordu.
 *
 *   · senkron yol  — `ValueSetFabrikasi`: `new Glossary($kok.'/config', $kok.'/storage')`
 *   · kuyruk yolu  — `KuyrukIsleyicileri`: `new Glossary($kok)`
 *
 * İkincisi iki hata taşıyordu: `config` eki yoktu (repo KÖKÜNDE
 * `sozluk-zh-tr.php` arıyordu; öyle bir dosya yok) ve `storage` hiç
 * verilmemişti (kullanıcının panelden girdiği terimler görünmüyordu).
 * Sonuç: KUYRUKLA ÇEVRİLEN ÜRÜNLER BOŞ SÖZLÜKLE ÇEVRİLİYORDU. Aynı ürün
 * senkron çevrildiğinde sözlüklü, toplu çeviriyle çevrildiğinde sözlüksüz
 * sonuç veriyordu.
 *
 * HATA SESSİZDİ ve bu tesadüf değil: sözlükte terim bulunamaması NORMAL bir
 * durumdur — sistem ham değeri döndürür ve devam eder. Boş bir sözlük ile
 * "bu terim sözlükte yok" ayırt edilemez. Üstelik `Glossary::surum()` de
 * farklı çıktığı için iki yol FARKLI önbellek satırlarına yazıyordu; ayrışma
 * kendi kendini besliyordu.
 *
 * Kurulum tek satır olduğu için "fabrikaya ne gerek var" denebilir. Gerek şu:
 * tek satır olduğu için iki yerde kopyalanması KOLAYDI ve kopyalardan biri
 * yanlıştı. Bekçi testi (`SozlukTekFabrikaTest`) elle kurulumun geri
 * gelmesini engeller.
 */
final class SozlukFabrikasi
{
    /**
     * @param string $basePath uygulama kökü (`config/` ve `storage/` bunun altında)
     */
    public static function kur(string $basePath): Glossary
    {
        // İKİSİ BİRDEN: `config/` depoyla gelen salt-okunur varsayılan,
        // `storage/` panelden yazılan üstyazım. Biri eksikse sözlük eksiktir.
        return new Glossary($basePath . '/config', $basePath . '/storage');
    }
}
