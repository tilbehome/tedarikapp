<?php

declare(strict_types=1);

namespace App\Services\Tur;

/**
 * TEKLİF TURU DURUM MAKİNESİ (V3-C Blok B · #15).
 *
 * Kaynak belge: `docs/v3/hazirlik/v3-c/teklif-turu-durum-makinesi.md`.
 *
 * SUNUCUDA ZORLANIR, ARAYÜZDE DEĞİL (K37 / CLAUDE.md §4). Geçişlerin bir kısmını
 * FİRMA tetikler ve firma tarafı dış dünyadadır: portala giden bir istek,
 * arayüzde gizlenmiş bir düğmeyi umursamaz. Kural sunucuda yoksa hiç yoktur.
 *
 * TUR NUMARASI DURUM ADINA GÖMÜLMEZ (#15 §2). `tur_no=2, state=SENT` arayüzde
 * "R2 gönderildi" diye görünür. Gömülseydi ("TUR2_SENT") her yeni tur için yeni
 * enum değeri gerekir, durum makinesi tur sayısı kadar çoğalır ve üçüncü turda
 * kod değiştirmeden ilerlemek imkânsız olurdu.
 *
 * `state` kolonu VARCHAR'dır, enum değil: durum kümesi V3-N/V3-D ile büyüyecek
 * ve MySQL'de enum genişletmek tabloyu yeniden yazar.
 */
final class TurDurumMakinesi
{
    /**
     * On durum (#15 §2). Sıra belgedeki tabloyla aynıdır — okurken karşılaştırmak
     * kolay olsun diye.
     *
     * @var list<string>
     */
    public const DURUMLAR = [
        'DRAFT',
        'SENT',
        'VIEWED',
        'PRICING',
        'RESPONDED',
        'REVISION_REQUESTED',
        'APPROVED',
        'ABANDONED',
        'EXPIRED',
        'REVOKED',
    ];

    /**
     * İzinli geçişler: önceki durum → gidilebilecek durumlar.
     *
     * BURADA OLMAYAN GEÇİŞ YOKTUR. Beyaz liste bilinçli: kara liste tutmak,
     * yeni bir durum eklendiğinde onu yanlışlıkla "her yere gidebilir" yapardı.
     *
     * APPROVED / ABANDONED / REVOKED listede YOK — nihaidirler. Nihai durumdan
     * çıkış, kapanmış bir ticari kaydın sessizce yeniden açılması demektir.
     * EXPIRED ise TAM KAPALI DEĞİLDİR: süresi geçmiş teklif ticari olarak
     * yenilenebilir, o yüzden revizyona açılır (#15).
     *
     * @var array<string, list<string>>
     */
    private const GECISLER = [
        'DRAFT' => ['SENT', 'ABANDONED'],
        // Kısmi gönderim durumu DEĞİŞTİRMEZ; PRICING → PRICING geçerli olmalı
        // (#15 madde 5), yoksa her kısmi teslim reddedilirdi.
        'SENT' => ['VIEWED', 'PRICING', 'RESPONDED', 'ABANDONED', 'EXPIRED', 'REVOKED'],
        'VIEWED' => ['PRICING', 'RESPONDED', 'ABANDONED', 'EXPIRED', 'REVOKED'],
        'PRICING' => ['PRICING', 'RESPONDED', 'ABANDONED', 'EXPIRED', 'REVOKED'],
        'RESPONDED' => ['APPROVED', 'REVISION_REQUESTED', 'ABANDONED', 'EXPIRED', 'REVOKED'],
        // Revizyon istendiğinde SİSTEM yeni turu açar (#15 madde 9); bu turun
        // kendisi kapanır. Yeni tur AYRI bir satırdır ve DRAFT olarak doğar.
        'REVISION_REQUESTED' => ['ABANDONED', 'REVOKED'],
        'EXPIRED' => ['REVISION_REQUESTED', 'ABANDONED', 'REVOKED'],
    ];

    /**
     * FİRMANIN tetikleyebileceği geçişler.
     *
     * Onay, vazgeçme ve revizyon isteme TİCARİ kararlardır ve yalnız Ürün
     * Sahibi'nindir. Firma kendi teklifini onaylayamaz — bu ayrım arayüzde
     * değil BURADA durmak zorunda, çünkü portal isteği panelden gelmez.
     *
     * @var array<string, list<string>>
     */
    private const FIRMA_GECISLERI = [
        'SENT' => ['VIEWED', 'PRICING', 'RESPONDED'],
        'VIEWED' => ['PRICING', 'RESPONDED'],
        'PRICING' => ['PRICING', 'RESPONDED'],
    ];

    /**
     * ÜRÜN SAHİBİNİN tetikleyemeyeceği geçişler.
     *
     * "Firma görüntüledi" bir GÖZLEMDİR, sahibin ilan edeceği bir şey değil:
     * sahip bunu yazabilseydi, firma hiç açmadan "açtı" görünebilirdi ve
     * bekleme süresi ölçümü yalan söylerdi.
     *
     * @var list<string>
     */
    private const SAHIBE_KAPALI = ['VIEWED'];

    /**
     * `cikti-terimleri.json:status.*` karşılıkları (#15 §2).
     *
     * TEK KAYNAK: ekranlar kendi metnini uydurursa aynı durum iki yerde iki
     * türlü yazılır. `SENT` bekleme bağlamında `status.waiting_supplier` de
     * gösterilebilir; buradaki değer BAĞLAYICI karşılıktır.
     *
     * @var array<string, string>
     */
    private const CIKTI_TERIMLERI = [
        'DRAFT' => 'status.preparing',
        'SENT' => 'status.sent',
        'VIEWED' => 'status.waiting_supplier',
        'PRICING' => 'status.waiting_price',
        'RESPONDED' => 'status.waiting_approval',
        'REVISION_REQUESTED' => 'status.preparing',
        'APPROVED' => 'status.approved',
        'ABANDONED' => 'status.cancelled',
        'EXPIRED' => 'status.expired',
        'REVOKED' => 'status.cancelled',
    ];

    /** Geçiş izinli mi? (soru — akışı durdurmaz) */
    public function gecebilirMi(string $onceki, string $hedef): bool
    {
        if (!in_array($hedef, self::DURUMLAR, true)) {
            return false;
        }

        return in_array($hedef, self::GECISLER[$onceki] ?? [], true);
    }

    /**
     * Geçiş kapısı — geçersizse akış DURUR.
     *
     * @throws TurGecisiReddedildi
     */
    public function dogrula(string $onceki, string $hedef, string $sebep = ''): void
    {
        if (!$this->gecebilirMi($onceki, $hedef)) {
            throw new TurGecisiReddedildi($onceki, $hedef, $sebep);
        }
    }

    /** Bu geçişi FİRMA tetikleyebilir mi? */
    public function firmaYapabilirMi(string $onceki, string $hedef): bool
    {
        return $this->gecebilirMi($onceki, $hedef)
            && in_array($hedef, self::FIRMA_GECISLERI[$onceki] ?? [], true);
    }

    /** Bu geçişi ÜRÜN SAHİBİ tetikleyebilir mi? */
    public function sahipYapabilirMi(string $onceki, string $hedef): bool
    {
        return $this->gecebilirMi($onceki, $hedef)
            && !in_array($hedef, self::SAHIBE_KAPALI, true);
    }

    /** Durumun bağlayıcı `status.*` karşılığı. */
    public function ciktiTerimi(string $durum): string
    {
        return self::CIKTI_TERIMLERI[$durum] ?? 'status.preparing';
    }

    /** Nihai mi? (hiçbir çıkışı yok) */
    public function nihaiMi(string $durum): bool
    {
        return in_array($durum, self::DURUMLAR, true) && (self::GECISLER[$durum] ?? []) === [];
    }
}
