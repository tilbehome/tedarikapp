<?php

declare(strict_types=1);

namespace App\Services\Inbox;

use App\Models\SettingsRepository;

/**
 * SİSTEM LİSTESİ KORUMASI (İE#21 B4 — PM şartı, 23 Ağu 2026).
 *
 * Keşif havuzu bir SİSTEM listesidir: kullanıcı onu oluşturmadı, adını koymadı ve
 * bir sipariş listesi gibi kullanmayacak. `products.list_id` zorunlu olduğu için
 * var; yani bir uygulama detayıdır ve kullanıcıya detay olarak bile görünmemelidir.
 *
 * PM iki şart koydu ve ikisi de burada yaşar:
 *
 *  (a) HİÇBİR YERDE GÖRÜNMEZ — liste ekranı, liste seçicileri, Panorama'daki
 *      "aktif liste" sayısı. Görünseydi kullanıcı "bu liste de ne?" diye sorar,
 *      açar, içine ürün ekler ve havuz bir çöplüğe dönerdi.
 *  (b) SİLİNEMEZ · İLETİLEMEZ · PAYLAŞILAMAZ — silinirse havuzdaki bütün ürünler
 *      yetim kalır; iletilir/paylaşılırsa firmaya "sipariş listesi" diye bir
 *      araştırma havuzu gider. İkisi de sessiz bir felakettir, o yüzden kapı
 *      SUNUCUDADIR: arayüzü atlayan istemci de aynı reddi alır.
 */
final class SistemListesi
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    /** Havuz listesinin kimliği; hiç oluşturulmadıysa null. */
    public function havuzId(): ?int
    {
        $kayitli = $this->settings->get(DesteEylemi::KEY_HAVUZ_LISTE);

        return is_string($kayitli) && ctype_digit($kayitli) ? (int) $kayitli : null;
    }

    public function sistemMi(int $listeId): bool
    {
        return $this->havuzId() === $listeId;
    }

    /** Kullanıcıya gösterilecek red gerekçesi — ne olduğunu ve neden olmadığını söyler. */
    public function redMesaji(string $eylem): string
    {
        return sprintf(
            'Keşif Havuzu bir SİSTEM listesidir; %s. Havuzdaki ürünü kullanmak için '
            . 'önce bir sipariş listesine taşıyın.',
            $eylem,
        );
    }
}
