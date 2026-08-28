<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * LLM İSTEMCİ SÖZLEŞMESİ (İE#22 E2).
 *
 * NEDEN ARAYÜZ: `LlmIstemci` `final` bir sınıftı ve mock'lanamıyordu. D6
 * turunda bu bir eksiklik olarak görüldü, D12'nin kanıt turunda ise fiilen
 * engele dönüştü — tekil "Çevir" akışını uçtan uca sınamak için ya üretim
 * koduna geçici yama atmak ya da çevirmenin tamamını taklit etmek gerekiyordu.
 * İkisi de kanıtın değerini düşürür: biri üretimi kirletir, diğeri sınanan
 * kodu atlar.
 *
 * Arayüz TEK METOTLUDUR ve mevcut imzayı aynen taşır: üretim davranışı
 * değişmez, yalnız yerine başka bir uygulama konabilir hâle gelir.
 */
interface LlmIstemciInterface
{
    /**
     * Sağlayıcıya sorar ve HAM yanıt gövdesini döndürür.
     *
     * @param string $saglayici   `CeviriAyarlari::saglayici()` değeri
     * @param string $apiAnahtari çözülmüş anahtar — LOGLANMAZ
     * @param string $model       sağlayıcı model adı
     *
     * @throws \RuntimeException ağ/servis hatası ya da tanınmayan sağlayıcı
     */
    public function sor(
        string $saglayici,
        #[\SensitiveParameter] string $apiAnahtari,
        string $model,
        string $sistemIstemi,
        string $kullaniciIstemi,
    ): string;
}
