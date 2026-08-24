<?php

declare(strict_types=1);

namespace App\Services\Share;

/**
 * Detay panelindeki 16 ALANLIK bilgi ızgarası (İE#13 F4 — şartname örneği).
 *
 * Alanlar üç kaynaktan doldurulur: ürün kolonları, yakalamanın RAW bloğu
 * (`normalized_attributes` — Çince öznitelik adları) ve türetilmiş bilgiler.
 * VERİ YOKSA UYDURULMAZ: alan "—" ile basılır (şartname).
 *
 * Anahtar eşlemesi Çince öznitelik adlarına dayanır çünkü 1688 verisi böyle gelir;
 * eşleşme bulunamazsa alan boş kalır. Platform bağımsızdır: başka bir kaynak
 * sitede aynı alanlar farklı adlarla gelirse eşleme listesine eklenir.
 */
final class ProductFacts
{
    /**
     * Alan tanımı: [TR etiket, 中文 etiket, EN etiket, RAW anahtar adayları].
     *
     * İE#21 EK-5 (K81): etiketler ÜÇ DİLLİDİR ve panele/sayfaya SEÇİLEN DİLDE
     * tek dil olarak basılır. Önce TR+中文 çifti basılıyordu; İngilizce sayfada
     * "Marka 品牌" görünmesi K81'in yasakladığı karışık dildi.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: list<string>}>
     */
    private const FIELDS = [
        ['Marka', '品牌', 'Brand', ['品牌', 'brand']],
        // İE#14 A5: 货号 (stok kodu) Model adaylarından ÇIKARILDI — "155" gibi
        // anlamsız stok kodları Model diye basılıyordu. Stok kodu kendi alanındadır.
        ['Model', '型号', 'Model', ['型号', 'model', 'model number']],
        ['Malzeme', '材质', 'Material', ['材质', 'material']],
        ['Ölçü', '尺寸', 'Size', ['尺寸', '规格尺寸', 'size']],
        ['Ağırlık', '净重', 'Weight', ['净重', '重量', 'weight']],
        ['Renk', '颜色', 'Colour', ['颜色', 'color']],
        ['Set adedi', '套件', 'Pieces per set', ['套件', '件数', '数量/套']],
        ['Menşe', '产地', 'Origin', ['产地', '原产地', '货源地']],
        ['Kapasite', '容量', 'Capacity', ['容量', 'capacity']],
        ['Stok kodu', '货号', 'Item no', ['货号', 'item no', 'sku']],
        ['Güç', '功率', 'Power', ['功率', 'power']],
        ['Garanti', '保修', 'Warranty', ['保修', '质保']],
        ['Sertifika', '认证', 'Certificate', ['认证', 'certificate']],
    ];

    /**
     * @param array<string, mixed> $product ListPresenter::product çıktısı
     * @param \App\Services\Translation\ValueSet|null $values İE#14 A3 — DEĞERLER de
     *                                                          sözlükten geçer (灰色 → Gri)
     *
     * @param string $dil arayüz dili — etiket bu dilde döner (K81: tek dil)
     *
     * @return list<array{0: string, 1: string|null}> [etiket, değer|null]
     */
    public static function build(
        array $product,
        ?\App\Services\Translation\ValueSet $values = null,
        string $dil = 'tr',
    ): array {
        $raw = self::rawAttributes($product['raw_attributes'] ?? null);

        $out = [];
        foreach (self::FIELDS as [$tr, $cjk, $en, $adaylar]) {
            $deger = null;
            foreach ($adaylar as $aday) {
                if (isset($raw[$aday]) && $raw[$aday] !== '') {
                    $deger = self::anlamli($raw[$aday]);
                    if ($deger !== null) {
                        // İE#14 A3: değer de A2 hattının belirlenimci katmanından geçer.
                        $deger = $values !== null ? $values->value($deger) : $deger;
                        break;
                    }
                }
            }
            $out[] = [self::etiket($dil, $tr, $cjk, $en), $deger];
        }

        // Ürün kolonlarından gelen kesin bilgiler — RAW'a bakmaya gerek yok.
        // Koli içi SAYIDIR: "20" anlamsız değildir — A5 elemesi buraya UYGULANMAZ,
        // yalnız RAW'dan gelen model/stok kodu adaylarına uygulanır.
        $out[] = [self::etiket($dil, 'Koli içi', '装箱', 'Units per carton'), self::sayi($product['units_per_carton'] ?? null)];
        $out[] = [self::etiket($dil, 'İlan no', '编号', 'Listing no'), self::sayi($product['external_id'] ?? null)];
        $out[] = [self::etiket($dil, 'Kaynak', '来源', 'Source'), self::metin($product['platform'] ?? null)];
        $out[] = [
            self::etiket($dil, 'Video', '视频', 'Video'),
            is_string($product['video_url'] ?? null) && $product['video_url'] !== ''
                ? self::etiket($dil, 'Var', '有', 'Yes')
                : null,
        ];

        return $out;
    }

    /**
     * İE#14 A6 — DOLU ALANLAR ÖNCE, boşlar ayrı kümede.
     *
     * Detay paneli 16 alanı sırayla basıp yarısını "—" ile dolduruyordu; göz dolu
     * bilgiyi bulamıyordu. Artık dolu alanlar üstte, boşlar "Eksik bilgileri göster (N)"
     * katlamasının içinde. Hepsi boşsa bölüm HİÇ BASILMAZ (çağıran `dolu === []` görür).
     *
     * @param array<string, mixed> $product
     *
     * @param string $dil arayüz dili (K81: etiketler tek dilde döner)
     *
     * @return array{dolu: list<array{0: string, 1: string}>, bos: list<string>}
     */
    public static function grouped(
        array $product,
        ?\App\Services\Translation\ValueSet $values = null,
        string $dil = 'tr',
    ): array {
        $dolu = [];
        $bos = [];
        foreach (self::build($product, $values, $dil) as [$etiket, $deger]) {
            if ($deger === null || $deger === '') {
                $bos[] = $etiket;
                continue;
            }
            $dolu[] = [$etiket, $deger];
        }

        return ['dolu' => $dolu, 'bos' => $bos];
    }

    /** Seçilen dildeki etiket — bilinmeyen dil Türkçeye düşer. */
    private static function etiket(string $dil, string $tr, string $cjk, string $en): string
    {
        return match ($dil) {
            'zh' => $cjk,
            'en' => $en,
            default => $tr,
        };
    }

    /**
     * @return array<string, string>
     */
    private static function rawAttributes(mixed $raw): array
    {
        if (is_string($raw)) {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($raw)) {
            return [];
        }

        $kaynak = $raw['normalized_attributes'] ?? $raw['attributes'] ?? $raw;
        if (!is_array($kaynak)) {
            return [];
        }

        $out = [];
        foreach ($kaynak as $anahtar => $deger) {
            if (is_string($anahtar) && is_scalar($deger)) {
                $out[$anahtar] = (string) $deger;
            }
        }

        return $out;
    }

    /** Sayısal kolon değeri: olduğu gibi basılır (anlamsızlık elemesi YOK). */
    private static function sayi(mixed $deger): ?string
    {
        if ($deger === null || $deger === '' || !is_scalar($deger)) {
            return null;
        }

        $metin = trim((string) $deger);

        return $metin === '' ? null : $metin;
    }

    private static function metin(mixed $deger): ?string
    {
        if ($deger === null || $deger === '' || !is_scalar($deger)) {
            return null;
        }

        return self::anlamli((string) $deger);
    }

    /**
     * İE#14 A5 — ANLAMSIZ DEĞER DENETİMİ: yalnızca rakamdan oluşan ve 3 karakterden
     * kısa değerler ("155", "12") bilgi taşımaz; alan boş sayılır ve "—" basılır.
     * Ölçü/sayı içeren ama birim taşıyan değerler ("350ml") korunur.
     */
    private static function anlamli(string $deger): ?string
    {
        $deger = trim($deger);
        if ($deger === '') {
            return null;
        }
        if (preg_match('/^\d+$/', $deger) === 1 && mb_strlen($deger) < 3) {
            return null;
        }

        return $deger;
    }
}
