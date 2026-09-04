<?php

declare(strict_types=1);

namespace App\Services\Yanit;

use App\Core\Connection;
use App\Models\TeklifTuruRepository;
use App\Models\YanitRepository;
use App\Services\ActivityLog;
use App\Services\Tur\TurDurumMakinesi;
use App\Services\Tur\TurIslemiReddedildi;
use DateTimeImmutable;

/**
 * ÖNİZLEMEDEN SEÇİLEN SATIRLARI FİRMA YANITI TASLAĞINA YAZAR (V3-C 2.2).
 *
 * KAPILAR (hepsi sunucuda, K37):
 *   1. Tur yanıt kabul eden durumda olmalı (SENT/VIEWED/PRICING); gönderilmiş/
 *      kilitli turda değişiklik uygulanmaz — sahibe "yeni revizyon turu aç"
 *      denir (spec §8 son satır).
 *   2. Her satır bu turun RFQ snapshot'ında olmalı (yabancı satır → hiçbir
 *      şey yazılmaz, 422).
 *   3. Her satır alan kurallarından geçmeli; tek satır bile hatalıysa HİÇBİRİ
 *      yazılmaz — kısmi uygulama "hangi yarısı yazıldı" sorusunu doğurur.
 *   4. Aynı parmak izi ikinci kez gelirse yazım YOK, önceki sonuç döner
 *      (tek kullanımlık idempotency anahtarı, spec §6.7/§9).
 *
 * BOŞ ALAN TEMİZLEMEZ: eski değer korunur; temizleme yalnız satır bazlı açık
 * `temizle` listesiyle olur (spec §8). Uygulama sonrası tur PRICING olur;
 * nihai gönderim kapısı AYRICA çalışır (§10) — Ürün Sahibinin uyguladığı yanıt
 * firma eylemi gibi gösterilmez: aktivite `actor=product_owner source=<kanal>`.
 */
final class YanitUygulayici
{
    public const KANAL_YAPISTIR = 'panel_yapistir';
    public const KANAL_EXCEL = 'panel_excel';

    private const YANIT_KABUL_EDEN = ['SENT', 'VIEWED', 'PRICING'];

    public function __construct(
        private readonly Connection $connection,
        private readonly TeklifTuruRepository $turlar,
        private readonly YanitRepository $yanitlar,
        private readonly ActivityLog $aktivite,
        private readonly YanitAlanKurallari $kurallar = new YanitAlanKurallari(),
        private readonly TurDurumMakinesi $makine = new TurDurumMakinesi(),
    ) {
    }

    /**
     * Turun mevcut taslağı, rfq_satir_id anahtarlı kanonik satırlar.
     *
     * @return array<string, array<string, mixed>>
     */
    public function mevcutSatirlar(int $turId): array
    {
        $taslak = $this->yanitlar->turunTaslagi($turId);
        if ($taslak === null) {
            return [];
        }
        $sonuc = [];
        foreach ($this->yanitlar->satirlar((int) $taslak['id']) as $id => $paket) {
            $sonuc[$id] = YanitDonusturucu::veritabanindan($paket['satir'], $paket['kademeler'], $paket['alternatif']);
        }

        return $sonuc;
    }

    /**
     * @param  array<string, mixed>              $tur
     * @param  list<array<string, mixed>>        $satirlar  İstemciden gelen kanonik satırlar (+ isteğe bağlı `temizle`)
     * @param  array{kanal: string, parmak_izi: string, etiket?: string} $kaynak
     * @return array{tekrar: bool, yazilan: int, satirlar: list<string>, tur: array<string, mixed>}
     */
    public function uygula(array $tur, array $satirlar, array $kaynak, ?int $actorId, ?string $ip, DateTimeImmutable $now): array
    {
        $turId = (int) $tur['id'];
        $durum = (string) $tur['state'];
        if (!in_array($durum, self::YANIT_KABUL_EDEN, true)) {
            throw new TurIslemiReddedildi('TUR_KILITLI', 'Bu tur yanıt kabul etmiyor (' . $durum . '). Değişiklik için yeni revizyon turu açın.');
        }
        if ($tur['rfq_snapshot_id'] === null) {
            throw new TurIslemiReddedildi('TUR_GONDERILMEMIS', 'Tur henüz gönderilmemiş; RFQ satırları donmadan yanıt yazılamaz.');
        }
        if ($satirlar === []) {
            throw new TurIslemiReddedildi('SATIR_YOK', 'Uygulanacak satır seçilmedi.');
        }

        $rfq = [];
        foreach ($this->turlar->rfqSatirlari((int) $tur['rfq_snapshot_id']) as $r) {
            $rfq[(string) $r['rfq_satir_id']] = $r;
        }
        $mevcut = $this->mevcutSatirlar($turId);

        // Birleştir + doğrula — HEPSİ geçmeden HİÇBİRİ yazılmaz.
        $hazir = [];
        $hatalar = [];
        foreach ($satirlar as $girdi) {
            $yeni = YanitDonusturucu::istemciden($girdi);
            $id = $yeni['rfq_satir_id'];
            if (!isset($rfq[$id])) {
                throw new TurIslemiReddedildi('SATIR_YABANCI', 'Satır bu turun RFQ snapshot\'ında yok: ' . $id);
            }
            if (isset($hazir[$id])) {
                throw new TurIslemiReddedildi('SATIR_MUKERRER', 'Aynı satır iki kez gönderildi: ' . $id);
            }
            $temizle = is_array($girdi['temizle'] ?? null) ? array_values(array_filter($girdi['temizle'], 'is_string')) : [];
            $birlesik = YanitDonusturucu::birlestir($mevcut[$id] ?? YanitDonusturucu::bos($id), $yeni, $temizle);
            $sonuc = $this->kurallar->dogrula($birlesik, (string) $rfq[$id]['talep_miktar']);
            foreach ($sonuc['hatalar'] as $h) {
                $hatalar[] = ['satir_id' => $id] + $h;
            }
            $hazir[$id] = $birlesik;
        }
        if ($hatalar !== []) {
            throw new TurIslemiReddedildi('YANIT_GECERSIZ', 'Satırlarda alan hatası var; hiçbiri uygulanmadı.', $hatalar);
        }

        return $this->connection->transaction(function () use ($tur, $turId, $hazir, $kaynak, $actorId, $ip, $now): array {
            $taslak = $this->yanitlar->turunTaslagi($turId);
            $responseId = $taslak === null ? $this->yanitlar->taslakAc($turId, $kaynak['kanal'], $now) : (int) $taslak['id'];

            if ($this->yanitlar->parmakIziVar($responseId, $kaynak['parmak_izi'])) {
                return ['tekrar' => true, 'yazilan' => 0, 'satirlar' => array_keys($hazir), 'tur' => $this->turlar->find($turId) ?? $tur];
            }

            foreach ($hazir as $satir) {
                $this->yanitlar->satirYaz($responseId, $satir, $now);
            }
            $this->yanitlar->kaynakEkle($responseId, $kaynak['kanal'], [
                'kanal' => $kaynak['kanal'],
                'parmak_izi' => $kaynak['parmak_izi'],
                'etiket' => $kaynak['etiket'] ?? null,
                'satirlar' => array_keys($hazir),
                'aktor' => 'product_owner',
                'aktor_id' => $actorId,
                'at' => $now->format('Y-m-d H:i:s'),
            ], $now);

            // SENT/VIEWED → PRICING (PRICING → PRICING de geçerli: kısmi teslim durum değiştirmez).
            $onceki = (string) $tur['state'];
            if ($this->makine->sahipYapabilirMi($onceki, 'PRICING')) {
                $this->turlar->durumGecisi($turId, $onceki, 'PRICING', $now, 'Yanıt panelden uygulandı (' . $kaynak['kanal'] . ')');
            }

            $this->aktivite->record(
                'supplier_round',
                $turId,
                'quote_imported',
                sprintf('actor=product_owner source=%s satır=%d', $kaynak['kanal'] === self::KANAL_EXCEL ? 'excel_import' : 'paste_parse', count($hazir)),
                $ip,
                $now,
                ActivityLog::ACTOR_ADMIN,
                $actorId,
            );

            return ['tekrar' => false, 'yazilan' => count($hazir), 'satirlar' => array_keys($hazir), 'tur' => $this->turlar->find($turId) ?? $tur];
        });
    }
}
