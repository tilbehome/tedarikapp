<?php

declare(strict_types=1);

namespace App\Services\Inbox;

use App\Models\ListRepository;
use App\Models\SettingsRepository;
use DateTimeImmutable;

/**
 * DESTE MODU EYLEMLERİ (İE#21 B4 · E2E-PNL-18/19).
 *
 * Deste modu, Gelen Kutusu'nu 40 üründe 2 dakikada elemek içindir: tek ürün büyük
 * görselle gelir, kullanıcı tek tuşla karar verir. Üç hedef vardır ve üçü de tek
 * bir veritabanı geçişine dönüşür:
 *
 *   ← ÇÖPE   — yakalama silinir (çöp kutusuna gider),
 *   ↓ HAVUZA — ürün olur ama bir SİPARİŞ listesine girmez,
 *   → LİSTEYE — seçili sipariş listesine eklenir.
 *
 * HAVUZ NEDİR (v1 kararı): `products.list_id` şemada zorunludur, yani "listesiz
 * ürün" diye bir kayıt yoktur. Havuz bu yüzden SİSTEM LİSTESİDİR — otomatik
 * oluşturulur, panelde sipariş listeleri arasında görünmez ve Keşif'in `listede`
 * süzgeci onu "listede değil" sayar. Alternatif, kolonu nullable yapıp listeye
 * bağlı onlarca sorguyu (toplamlar, revizyon, paylaşım, export) elden geçirmekti;
 * o değişiklik kendi iş emrini ister ve havuzun değerine hiçbir şey katmazdı.
 *
 * GERİ ALMA (E2E-PNL-19): her eylem, kendisini tersine çevirecek bilgiyi döner.
 * Geri alma İKİNCİ KEZ çalışmaz — çalışsaydı ürün iki kez geri gelir ya da sayaç
 * eksiye düşerdi.
 */
final class DesteEylemi
{
    public const HEDEF_COP = 'cop';
    public const HEDEF_HAVUZ = 'havuz';
    public const HEDEF_LISTE = 'liste';

    /** Havuz sistem listesinin kimliğini tutan ayar. */
    public const KEY_HAVUZ_LISTE = 'kesif.havuz_liste_id';

    /** Havuz listesinin görünen adı. */
    public const HAVUZ_ADI = 'Keşif Havuzu';

    public function __construct(
        private readonly ListRepository $lists,
        private readonly SettingsRepository $settings,
    ) {
    }

    /**
     * Havuz listesini döner; yoksa OLUŞTURUR.
     *
     * Kur alanları zorunlu olduğu için güncel ayar kuruyla açılır — havuz listesi
     * hiç iletilmeyeceği için kuru kilitlenmez ve güncel kuru izlemeye devam eder
     * (İE#21 B5).
     */
    public function havuzListesi(DateTimeImmutable $now): int
    {
        $kayitli = $this->settings->get(self::KEY_HAVUZ_LISTE);
        if (is_string($kayitli) && ctype_digit($kayitli)) {
            $liste = $this->lists->find((int) $kayitli);
            if ($liste !== null) {
                return (int) $kayitli;
            }
        }

        $id = $this->lists->create([
            'name' => self::HAVUZ_ADI,
            'period' => null,
            'supplier_name' => null,
            'note' => 'Sistem listesi — Keşif havuzu. Sipariş listesi DEĞİLDİR.',
            'status' => 'draft',
            // Sipariş listeleri arasında görünmesin diye PASİF açılır.
            'visibility' => 'passive',
            'yuan_rate' => $this->settings->yuanRate(),
            'usd_rate' => $this->settings->usdRate(),
        ], $now);

        $this->settings->set(self::KEY_HAVUZ_LISTE, (string) $id);

        return $id;
    }

    /** Havuz listesinin kimliği (yoksa null — sorgu tarafı bunu bekler). */
    public function havuzListesiVarsa(): ?int
    {
        $kayitli = $this->settings->get(self::KEY_HAVUZ_LISTE);

        return is_string($kayitli) && ctype_digit($kayitli) ? (int) $kayitli : null;
    }

}
