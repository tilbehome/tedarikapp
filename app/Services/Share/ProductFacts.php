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
     * Alan tanımı: [TR etiket, 中文 etiket, RAW anahtar adayları].
     *
     * @var list<array{0: string, 1: string, 2: list<string>}>
     */
    private const FIELDS = [
        ['Marka', '品牌', ['品牌', 'brand']],
        ['Model', '型号', ['型号', 'model', '货号']],
        ['Malzeme', '材质', ['材质', 'material']],
        ['Ölçü', '尺寸', ['尺寸', '规格尺寸', 'size']],
        ['Ağırlık', '净重', ['净重', '重量', 'weight']],
        ['Renk', '颜色', ['颜色', 'color']],
        ['Set adedi', '套件', ['套件', '件数', '数量/套']],
        ['Menşe', '产地', ['产地', '原产地', '货源地']],
        ['Kapasite', '容量', ['容量', 'capacity']],
        ['Güç', '功率', ['功率', 'power']],
        ['Garanti', '保修', ['保修', '质保']],
        ['Sertifika', '认证', ['认证', 'certificate']],
    ];

    /**
     * @param array<string, mixed> $product ListPresenter::product çıktısı
     *
     * @return list<array{0: string, 1: string, 2: string|null}> [TR, 中文, değer|null]
     */
    public static function build(array $product): array
    {
        $raw = self::rawAttributes($product['raw_attributes'] ?? null);

        $out = [];
        foreach (self::FIELDS as [$tr, $cjk, $adaylar]) {
            $deger = null;
            foreach ($adaylar as $aday) {
                if (isset($raw[$aday]) && $raw[$aday] !== '') {
                    $deger = $raw[$aday];
                    break;
                }
            }
            $out[] = [$tr, $cjk, $deger];
        }

        // Ürün kolonlarından gelen kesin bilgiler — RAW'a bakmaya gerek yok.
        $out[] = ['Koli içi', '装箱', self::metin($product['units_per_carton'] ?? null)];
        $out[] = ['İlan no', '编号', self::metin($product['external_id'] ?? null)];
        $out[] = ['Kaynak', '来源', self::metin($product['platform'] ?? null)];
        $out[] = [
            'Video',
            '视频',
            is_string($product['video_url'] ?? null) && $product['video_url'] !== '' ? 'Var' : null,
        ];

        return $out;
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

    private static function metin(mixed $deger): ?string
    {
        if ($deger === null || $deger === '' || !is_scalar($deger)) {
            return null;
        }

        return (string) $deger;
    }
}
