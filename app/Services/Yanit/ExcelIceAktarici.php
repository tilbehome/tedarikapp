<?php

declare(strict_types=1);

namespace App\Services\Yanit;

use App\Services\Tur\TurIslemiReddedildi;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;
use ZipArchive;

/**
 * FİRMADAN DÖNEN EXCEL'İN ÖNİZLEMESİ (spec §6-§9). HİÇBİR ŞEY YAZILMAZ.
 *
 * Sıra spec §7'deki gibidir ve her adım FAIL-CLOSED'dur:
 *   0. Güvenlik: zip değil / şifreli OLE / makro / dış bağlantı / gömülü nesne → ret.
 *   1. `schema_version` destekleniyor mu?
 *   2. `supplier_round_id` bu tur mu? (başka tur/firma dosyası → tüm içe aktarım ret)
 *   3. `rfq_snapshot_id` turun kilitli RFQ'su mu?
 *   4. Manifest imzası doğru mu? (kimlik alanları elle değiştirilmiş → ret)
 *   5. Satır: kimlik bu turda var mı (YABANCI) · aynı kimlik iki kez (MÜKERRER,
 *      "sonuncuyu al" yok) · satır imzası (bozuksa önizlenir, uygulanamaz)
 *   6. Alan kuralları (`YanitAlanKurallari`) — Excel'in kendi doğrulaması
 *      aşılmış olabilir, sunucu yeniden uygular.
 *
 * Formül HİÇ HESAPLANMAZ: "=" ile başlayan hücre değeri olarak değil,
 * güvenlik bulgusu olarak raporlanır (§6.8). Boş hücre "değişiklik yok"tur,
 * temizleme değildir (§8).
 *
 * Çıktı gruplar halinde: uygulanabilir · uyarili · hatali · belirsiz ·
 * degisiklik_yok. Varsayılan seçim yalnız uygulanabilir olanlardır (§9).
 */
final class ExcelIceAktarici
{
    private const EN_COK_BAYT = 3 * 1024 * 1024;
    private const RISKLI_YOLLAR = ['xl/vbaProject', 'xl/externalLinks/', 'xl/embeddings/', 'xl/activeX', 'xl/media/', 'xl/pivotCache', 'xl/queryTables', 'xl/connections', 'customXml/'];

    public function __construct(
        private readonly SatirImzasi $imza,
        private readonly string $gecicilDizin,
        private readonly YanitAlanKurallari $kurallar = new YanitAlanKurallari(),
    ) {
    }

    /**
     * @param  array<string, mixed>                $tur
     * @param  list<array<string, mixed>>          $rfqSatirlari
     * @param  array<string, array<string, mixed>> $mevcut  rfq_satir_id → kanonik (DB'deki taslak)
     * @return array<string, mixed>
     */
    public function onizle(string $bytes, array $tur, array $rfqSatirlari, array $mevcut): array
    {
        $parmakIzi = hash('sha256', $bytes);
        $this->guvenlikTarama($bytes);
        $kitap = $this->yukle($bytes);

        try {
            $manifest = $this->manifest($kitap, $tur, count($rfqSatirlari));
            $rfq = [];
            foreach ($rfqSatirlari as $r) {
                $rfq[(string) $r['rfq_satir_id']] = $r;
            }
            $kademeler = $this->kademeleriOku($kitap, (int) $tur['id']);
            $satirlar = $this->satirlariOku($kitap, $tur, $rfq, $mevcut, $kademeler);
        } finally {
            $kitap->disconnectWorksheets();
        }

        $ozet = ['uygulanabilir' => 0, 'uyarili' => 0, 'hatali' => 0, 'belirsiz' => 0, 'degisiklik_yok' => 0];
        foreach ($satirlar as $s) {
            $ozet[$s['grup']]++;
        }

        return [
            'parmak_izi' => $parmakIzi,
            'manifest' => $manifest,
            'ozet' => $ozet,
            'satirlar' => $satirlar,
        ];
    }

    // ── güvenlik ───────────────────────────────────────────────────────

    private function guvenlikTarama(string $bytes): void
    {
        if ($bytes === '' || strlen($bytes) > self::EN_COK_BAYT) {
            throw new TurIslemiReddedildi('DOSYA_BOYUT', 'Dosya boş ya da 3 MB sınırını aşıyor.');
        }
        if (str_starts_with($bytes, "\xD0\xCF\x11\xE0")) {
            throw new TurIslemiReddedildi('DOSYA_GUVENLIK', 'Şifreli ya da eski biçim (.xls/OLE) dosya içe alınmaz; makrosuz .xlsx gönderin.');
        }
        if (!str_starts_with($bytes, "PK")) {
            throw new TurIslemiReddedildi('DOSYA_BICIM', 'Dosya .xlsx değil.');
        }
        $yol = $this->geciciYaz($bytes);
        try {
            $zip = new ZipArchive();
            if ($zip->open($yol) !== true) {
                throw new TurIslemiReddedildi('DOSYA_BICIM', 'Dosya açılamadı (.xlsx bekleniyor).');
            }
            $riskli = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $ad = (string) ($zip->statIndex($i)['name'] ?? '');
                foreach (self::RISKLI_YOLLAR as $yasak) {
                    if (str_starts_with($ad, $yasak)) {
                        $riskli[] = $ad;
                    }
                }
            }
            $zip->close();
            if ($riskli !== []) {
                throw new TurIslemiReddedildi('DOSYA_GUVENLIK', 'Makro, dış bağlantı ya da gömülü nesne içeren dosya içe alınmaz: ' . implode(', ', array_slice($riskli, 0, 3)));
            }
        } finally {
            @unlink($yol);
        }
    }

    private function yukle(string $bytes): Spreadsheet
    {
        $yol = $this->geciciYaz($bytes);
        try {
            $okuyucu = IOFactory::createReader('Xlsx');
            $okuyucu->setReadDataOnly(true)->setIncludeCharts(false);
            $okuyucu->setLoadSheetsOnly([ExcelSema::SAYFA_QUOTATION, ExcelSema::SAYFA_TIERS, ExcelSema::SAYFA_MANIFEST]);

            return $okuyucu->load($yol);
        } catch (Throwable $e) {
            throw new TurIslemiReddedildi('DOSYA_BICIM', 'Excel dosyası okunamadı: ' . $e->getMessage());
        } finally {
            @unlink($yol);
        }
    }

    private function geciciYaz(string $bytes): string
    {
        if (!is_dir($this->gecicilDizin) && !mkdir($this->gecicilDizin, 0770, true) && !is_dir($this->gecicilDizin)) {
            throw new TurIslemiReddedildi('DOSYA_GECICI', 'Geçici dizin yazılamıyor.');
        }
        $yol = $this->gecicilDizin . '/ice-aktar-' . bin2hex(random_bytes(8)) . '.xlsx';
        if (file_put_contents($yol, $bytes) === false) {
            throw new TurIslemiReddedildi('DOSYA_GECICI', 'Geçici dosya yazılamadı.');
        }

        return $yol;
    }

    // ── manifest ───────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed> $tur
     * @return array<string, mixed>
     */
    private function manifest(Spreadsheet $kitap, array $tur, int $satirSayisi): array
    {
        $sayfa = $kitap->getSheetByName(ExcelSema::SAYFA_MANIFEST);
        if ($sayfa === null) {
            throw new TurIslemiReddedildi('DOSYA_YABANCI', 'MANIFEST sayfası yok; bu dosya TedarikApp şablonu değil.');
        }
        $alanlar = [];
        for ($r = 1; $r <= 6; $r++) {
            $anahtar = $this->deger($sayfa, 'A' . $r);
            if (is_string($anahtar) && $anahtar !== '') {
                $alanlar[$anahtar] = $this->deger($sayfa, 'B' . $r);
            }
        }
        if ((string) ($alanlar['schema_version'] ?? '') !== (string) ExcelSema::SURUM) {
            throw new TurIslemiReddedildi('DOSYA_SEMA', 'Desteklenmeyen şablon sürümü: ' . (string) ($alanlar['schema_version'] ?? '?'));
        }
        $turId = (int) $tur['id'];
        if ((int) ($alanlar['supplier_round_id'] ?? 0) !== $turId) {
            throw new TurIslemiReddedildi('DOSYA_YABANCI', sprintf('Dosya başka bir tura ait (dosyada tur %s, seçili tur %d). Doğru turu açın ya da o tur için yeni şablon indirin.', (string) ($alanlar['supplier_round_id'] ?? '?'), $turId));
        }
        if ((int) ($alanlar['rfq_snapshot_id'] ?? 0) !== (int) $tur['rfq_snapshot_id']) {
            throw new TurIslemiReddedildi('DOSYA_ESKI', 'Dosyanın RFQ snapshot\'ı bu turun kilitli RFQ\'suyla aynı değil; yeni şablon indirin.');
        }
        $beklenen = $this->imza->manifest($turId, (int) $tur['rfq_snapshot_id'], (int) ($alanlar['row_count'] ?? -1), (string) ($alanlar['exported_at'] ?? ''));
        if (!$this->imza->dogru($beklenen, $alanlar['manifest_signature'] ?? null)) {
            throw new TurIslemiReddedildi('DOSYA_IMZA', 'Manifest imzası doğrulanamadı; kimlik alanları değiştirilmiş görünüyor.');
        }

        return [
            'schema_version' => (int) $alanlar['schema_version'],
            'exported_at' => (string) $alanlar['exported_at'],
            'supplier_round_id' => $turId,
            'rfq_snapshot_id' => (int) $tur['rfq_snapshot_id'],
            'row_count' => (int) $alanlar['row_count'],
            'tur_satir_sayisi' => $satirSayisi,
        ];
    }

    // ── kademeler ──────────────────────────────────────────────────────

    /**
     * PRICE_TIERS → rfq_satir_id → {kademeler, hatalar, formul}.
     *
     * @return array<string, array{kademeler: list<array<string, mixed>>, hatalar: list<string>, hucreler: list<string>}>
     */
    private function kademeleriOku(Spreadsheet $kitap, int $turId): array
    {
        $sayfa = $kitap->getSheetByName(ExcelSema::SAYFA_TIERS);
        if ($sayfa === null) {
            return [];
        }
        $sonuc = [];
        $son = min($sayfa->getHighestDataRow(), ExcelSema::VERI_BASLANGIC + ExcelSema::EN_COK_SATIR * 20);
        for ($r = ExcelSema::VERI_BASLANGIC; $r <= $son; $r++) {
            $id = $this->metin($sayfa, 'A' . $r);
            $min = $this->sayi($sayfa, 'C' . $r);
            $max = $this->sayi($sayfa, 'D' . $r);
            $fiyat = $this->sayi($sayfa, 'E' . $r);
            $para = $this->metin($sayfa, 'F' . $r);
            if ($id === null && $min === null && $fiyat === null) {
                continue;
            }
            $anahtar = $id ?? '?';
            $sonuc[$anahtar] ??= ['kademeler' => [], 'hatalar' => [], 'hucreler' => []];
            $sonuc[$anahtar]['hucreler'][] = 'PRICE_TIERS!C' . $r;
            if ($id === null) {
                $sonuc[$anahtar]['hatalar'][] = 'PRICE_TIERS!A' . $r . ': satır kimliği boş.';
                continue;
            }
            foreach (['C', 'D', 'E'] as $h) {
                if ($this->formulMu($sayfa, $h . $r)) {
                    $sonuc[$anahtar]['hatalar'][] = 'PRICE_TIERS!' . $h . $r . ': formül içeriyor; formül çalıştırılmaz.';
                    continue 2;
                }
            }
            $sonuc[$anahtar]['kademeler'][] = [
                'min_adet' => $min ?? '',
                'max_adet' => $max,
                'birim_fiyat' => $fiyat ?? '',
                'para_birimi' => $para === null ? null : strtoupper($para),
                'kademe_tipi' => $max === null ? 'esik' : 'aralik',
                'hucre' => 'PRICE_TIERS!A' . $r,
            ];
        }
        foreach ($sonuc as &$paket) {
            usort($paket['kademeler'], static fn (array $a, array $b): int => bccomp((string) ($a['min_adet'] === '' ? '0' : $a['min_adet']), (string) ($b['min_adet'] === '' ? '0' : $b['min_adet']), 3));
        }

        return $sonuc;
    }

    // ── satırlar ───────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>                $tur
     * @param  array<string, array<string, mixed>> $rfq
     * @param  array<string, array<string, mixed>> $mevcut
     * @param  array<string, array{kademeler: list<array<string, mixed>>, hatalar: list<string>, hucreler: list<string>}> $kademeler
     * @return list<array<string, mixed>>
     */
    private function satirlariOku(Spreadsheet $kitap, array $tur, array $rfq, array $mevcut, array $kademeler): array
    {
        $sayfa = $kitap->getSheetByName(ExcelSema::SAYFA_QUOTATION);
        if ($sayfa === null) {
            throw new TurIslemiReddedildi('DOSYA_YABANCI', 'QUOTATION sayfası yok.');
        }
        $turId = (int) $tur['id'];
        $snapshotId = (int) $tur['rfq_snapshot_id'];
        $yanit = ExcelSema::yanitSutunlari();
        $son = min($sayfa->getHighestDataRow(), ExcelSema::VERI_BASLANGIC + ExcelSema::EN_COK_SATIR);

        $gorulen = [];
        $ham = [];
        for ($r = ExcelSema::VERI_BASLANGIC; $r <= $son; $r++) {
            $id = $this->metin($sayfa, 'A' . $r);
            if ($id === null) {
                continue;
            }
            $gorulen[$id] = ($gorulen[$id] ?? 0) + 1;
            $ham[] = ['satir' => $r, 'id' => $id];
        }

        $sonuc = [];
        foreach ($ham as ['satir' => $r, 'id' => $id]) {
            $satir = $this->satirOku($sayfa, $r, $id, $yanit, $turId, $snapshotId, $rfq, $mevcut, $kademeler[$id] ?? null, ($gorulen[$id] ?? 0) > 1);
            $sonuc[] = $satir;
        }
        // Kademe sayfasında geçen ama QUOTATION'da olmayan kimlikler.
        foreach ($kademeler as $id => $paket) {
            if (!isset($gorulen[$id])) {
                $sonuc[] = $this->bloklu($id, 'PRICE_TIERS', 'hatali', ['Kademe satırının kimliği QUOTATION sayfasında yok: ' . $id], $rfq[$id] ?? null);
            }
        }

        return $sonuc;
    }

    /**
     * @param  array<string, string>               $yanit
     * @param  array<string, array<string, mixed>> $rfq
     * @param  array<string, array<string, mixed>> $mevcut
     * @param  ?array{kademeler: list<array<string, mixed>>, hatalar: list<string>, hucreler: list<string>} $kademePaketi
     * @return array<string, mixed>
     */
    private function satirOku(Worksheet $sayfa, int $r, string $id, array $yanit, int $turId, int $snapshotId, array $rfq, array $mevcut, ?array $kademePaketi, bool $mukerrer): array
    {
        $hucre = 'QUOTATION!A' . $r;
        if (!isset($rfq[$id])) {
            return $this->bloklu($id, $hucre, 'hatali', ['YABANCI: satır kimliği bu turda yok; hiçbir ürüne uygulanmaz.'], null);
        }
        $rfqSatir = $rfq[$id];
        if ($mukerrer) {
            return $this->bloklu($id, $hucre, 'hatali', ['MÜKERRER: aynı satır kimliği birden çok satırda; "sonuncuyu al" yapılmaz.'], $rfqSatir);
        }
        $varyant = $rfqSatir['talep_varyant_json'] === null ? null : (string) $rfqSatir['talep_varyant_json'];
        $beklenenImza = $this->imza->satir($turId, $snapshotId, $id, (string) $rfqSatir['talep_miktar'], $varyant);
        $imzaBozuk = !$this->imza->dogru($beklenenImza, $this->metin($sayfa, 'C' . $r));

        // Formül içeren hücreler: değer olarak okunmaz, güvenlik bulgusu.
        $guvenlik = [];
        $yeni = YanitDonusturucu::bos($id);
        $bosMu = true;
        foreach ($yanit as $alan => $harf) {
            $koordinat = $harf . $r;
            if ($this->formulMu($sayfa, $koordinat)) {
                $guvenlik[] = 'QUOTATION!' . $koordinat . ': formül içeriyor; çalıştırılmadı, değer alınmadı.';
                continue;
            }
            $deger = $this->hucreDegeri($sayfa, $koordinat, $alan);
            if ($deger === null) {
                continue;
            }
            $bosMu = false;
            $yeni[$this->kanonikAd($alan)] = $deger;
        }
        $kademeHatalari = $kademePaketi['hatalar'] ?? [];
        if ($kademePaketi !== null && $kademePaketi['kademeler'] !== []) {
            $bosMu = false;
            $yeni['kademeler'] = array_map(static function (array $k): array {
                unset($k['hucre']);

                return $k;
            }, $kademePaketi['kademeler']);
        }

        if ($bosMu && $guvenlik === [] && $kademeHatalari === []) {
            return $this->satirSonucu($id, $hucre, 'degisiklik_yok', $rfqSatir, $mevcut[$id] ?? null, null, [], [], [], [], $imzaBozuk);
        }

        $eski = $mevcut[$id] ?? null;
        $birlesik = YanitDonusturucu::birlestir($eski ?? YanitDonusturucu::bos($id), $yeni);
        $dogrulama = $this->kurallar->dogrula($birlesik, (string) $rfqSatir['talep_miktar']);
        $hatalar = array_map(static fn (array $h): string => $h['alan'] . ': ' . $h['kural'], $dogrulama['hatalar']);
        $uyarilar = array_map(static fn (array $u): string => $u['alan'] . ': ' . $u['kural'], $dogrulama['uyarilar']);
        $belirsiz = [];

        // Para birimsiz fiyat: uygulanmaz, belirsiz alan (§8).
        if ($birlesik['ddp_birim_fiyat'] !== null && $birlesik['para_birimi'] === null) {
            $belirsiz[] = 'Fiyat var, para birimi yok (M' . $r . '): fiyat uygulanmaz.';
            $hatalar = array_values(array_filter($hatalar, static fn (string $h): bool => !str_starts_with($h, 'para_birimi:')));
        }
        foreach ($guvenlik as $g) {
            $hatalar[] = $g;
        }
        foreach ($kademeHatalari as $k) {
            $hatalar[] = $k;
        }
        // Kısmen doldurulmuş bulundu/alternatif satırı hata listesine gider (§8).
        $kismi = $dogrulama['eksik'] !== [] && in_array($birlesik['yanit_durumu'], ['found', 'alternative_available'], true);
        if ($kismi) {
            $hatalar[] = 'Eksik zorunlu alan: ' . implode(', ', $dogrulama['eksik']);
        }
        if ($imzaBozuk) {
            $hatalar[] = 'Satır imzası bozuk: kaynak alanlar (kimlik/miktar/varyant) değiştirilmiş görünüyor; önizlenir, uygulanamaz.';
        }

        $degisen = $this->degisenAlanlar($eski, $birlesik);
        $grup = match (true) {
            $hatalar !== [] => 'hatali',
            $belirsiz !== [] => 'belirsiz',
            $degisen === [] => 'degisiklik_yok',
            $uyarilar !== [] => 'uyarili',
            default => 'uygulanabilir',
        };

        return $this->satirSonucu($id, $hucre, $grup, $rfqSatir, $eski, $birlesik, $degisen, $hatalar, $uyarilar, $belirsiz, $imzaBozuk);
    }

    /**
     * @param  ?array<string, mixed> $eski
     * @param  array<string, mixed>  $yeni
     * @return list<string>
     */
    private function degisenAlanlar(?array $eski, array $yeni): array
    {
        $eski ??= YanitDonusturucu::bos((string) $yeni['rfq_satir_id']);
        $degisen = [];
        foreach (YanitDonusturucu::ALANLAR as $alan) {
            $a = $eski[$alan] ?? null;
            $b = $yeni[$alan] ?? null;
            $ayni = is_string($a) && is_string($b) && preg_match('/^\d+(\.\d+)?$/', $a) === 1 && preg_match('/^\d+(\.\d+)?$/', $b) === 1
                ? bccomp($a, $b, 6) === 0
                : $a == $b;
            if (!$ayni) {
                $degisen[] = $alan;
            }
        }
        if (!YanitDonusturucu::ayni(['kademeler' => $eski['kademeler'] ?? []] + array_fill_keys(YanitDonusturucu::ALANLAR, null), ['kademeler' => $yeni['kademeler'] ?? []] + array_fill_keys(YanitDonusturucu::ALANLAR, null))) {
            $degisen[] = 'kademeler';
        }

        return $degisen;
    }

    /**
     * @param  array<string, mixed>  $rfqSatir
     * @param  ?array<string, mixed> $eski
     * @param  ?array<string, mixed> $yeni
     * @param  list<string>          $degisen
     * @param  list<string>          $hatalar
     * @param  list<string>          $uyarilar
     * @param  list<string>          $belirsiz
     * @return array<string, mixed>
     */
    private function satirSonucu(string $id, string $hucre, string $grup, array $rfqSatir, ?array $eski, ?array $yeni, array $degisen, array $hatalar, array $uyarilar, array $belirsiz, bool $imzaBozuk): array
    {
        $adlar = json_decode((string) $rfqSatir['urun_adi_json'], true);

        return [
            'rfq_satir_id' => $id,
            'hucre' => $hucre,
            'urun_kodu' => $rfqSatir['urun_kodu'],
            'urun_adi' => is_array($adlar) ? $adlar : null,
            'talep_miktar' => YanitDonusturucu::sade((string) $rfqSatir['talep_miktar']),
            'grup' => $grup,
            'secilebilir' => in_array($grup, ['uygulanabilir', 'uyarili'], true),
            'varsayilan_secili' => $grup === 'uygulanabilir',
            'imza_bozuk' => $imzaBozuk,
            'eski' => $eski,
            'yeni' => $yeni,
            'degisen' => $degisen,
            'hatalar' => $hatalar,
            'uyarilar' => $uyarilar,
            'belirsiz' => $belirsiz,
        ];
    }

    /**
     * @param list<string> $hatalar
     * @param ?array<string, mixed> $rfqSatir
     * @return array<string, mixed>
     */
    private function bloklu(string $id, string $hucre, string $grup, array $hatalar, ?array $rfqSatir): array
    {
        return [
            'rfq_satir_id' => $id,
            'hucre' => $hucre,
            'urun_kodu' => $rfqSatir['urun_kodu'] ?? null,
            'urun_adi' => $rfqSatir === null ? null : json_decode((string) $rfqSatir['urun_adi_json'], true),
            'talep_miktar' => $rfqSatir === null ? null : YanitDonusturucu::sade((string) $rfqSatir['talep_miktar']),
            'grup' => $grup,
            'secilebilir' => false,
            'varsayilan_secili' => false,
            'imza_bozuk' => false,
            'eski' => null,
            'yeni' => null,
            'degisen' => [],
            'hatalar' => $hatalar,
            'uyarilar' => [],
            'belirsiz' => [],
        ];
    }

    // ── hücre okuma ────────────────────────────────────────────────────

    private function kanonikAd(string $sutunAdi): string
    {
        return match ($sutunAdi) {
            'ddp_birim_fiyat_kdv_dahil' => 'ddp_birim_fiyat',
            'ddp_turkiye_kdv_dahil_onayi' => 'ddp_kdv_dahil_onayi',
            'alternatif_urun_baglantisi' => 'alternatif_baglanti',
            'alternatif_aciklamasi' => 'alternatif_aciklama',
            default => $sutunAdi,
        };
    }

    private function hucreDegeri(Worksheet $sayfa, string $koordinat, string $alan): mixed
    {
        return match ($alan) {
            'ddp_turkiye_kdv_dahil_onayi' => $this->evetHayir($this->metin($sayfa, $koordinat)),
            'ddp_birim_fiyat_kdv_dahil', 'moq_deger', 'koli_uzunluk_cm', 'koli_genislik_cm', 'koli_yukseklik_cm', 'koli_cbm', 'koli_brut_kg', 'koli_net_kg' => $this->sayi($sayfa, $koordinat),
            'termin_suresi', 'koli_ici_adet' => $this->tamSayi($sayfa, $koordinat),
            'yanit_durumu', 'para_birimi', 'moq_birim', 'termin_baslangici', 'termin_birimi' => $this->kod($this->metin($sayfa, $koordinat), $alan),
            default => $this->metin($sayfa, $koordinat),
        };
    }

    private function deger(Worksheet $sayfa, string $koordinat): mixed
    {
        $v = $sayfa->getCell($koordinat)->getValue();
        if ($v instanceof RichText) {
            return $v->getPlainText();
        }

        return $v;
    }

    private function formulMu(Worksheet $sayfa, string $koordinat): bool
    {
        $v = $this->deger($sayfa, $koordinat);

        return is_string($v) && str_starts_with(ltrim($v), '=');
    }

    private function metin(Worksheet $sayfa, string $koordinat): ?string
    {
        $v = $this->deger($sayfa, $koordinat);
        if ($v === null) {
            return null;
        }
        if (is_float($v)) {
            $v = YanitDonusturucu::sade(sprintf('%.6F', $v));
        }
        $s = trim((string) $v);
        // Formül enjeksiyonu: "=" ile başlayan metin formül olarak DA metin olarak DA alınmaz.
        if ($s === '' || str_starts_with($s, '=')) {
            return null;
        }

        return mb_substr($s, 0, 5000);
    }

    /** Sayısal hücre → noktalı ondalık string (K14: float aritmetiği yok, yalnız gösterim çevrimi). */
    private function sayi(Worksheet $sayfa, string $koordinat): ?string
    {
        $v = $this->deger($sayfa, $koordinat);
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v)) {
            return (string) $v;
        }
        if (is_float($v)) {
            return YanitDonusturucu::sade(sprintf('%.6F', $v));
        }
        $s = str_replace(' ', '', trim((string) $v));
        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $s) === 1) {
            $s = str_replace(',', '', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }

        return $s === '' ? null : $s;
    }

    private function tamSayi(Worksheet $sayfa, string $koordinat): int|string|null
    {
        $s = $this->sayi($sayfa, $koordinat);
        if ($s === null) {
            return null;
        }

        return preg_match('/^\d+$/', $s) === 1 ? (int) $s : $s;
    }

    private function evetHayir(?string $v): ?bool
    {
        if ($v === null) {
            return null;
        }
        $v = mb_strtoupper($v);

        return match (true) {
            in_array($v, ['YES', 'EVET', 'TRUE', '1', 'Y', '是', 'DAHIL', 'DAHİL'], true) => true,
            in_array($v, ['NO', 'HAYIR', 'FALSE', '0', 'N', '否', 'HARIC', 'HARİÇ'], true) => false,
            default => null,
        };
    }

    /** Liste sütunları: makine kodu ya da görünen değer → makine kodu; tanınmayan değer olduğu gibi (kural hatası verir). */
    private function kod(?string $v, string $alan): ?string
    {
        if ($v === null) {
            return null;
        }
        $k = mb_strtolower(trim($v));
        $esleme = match ($alan) {
            'yanit_durumu' => ['found' => 'found', 'bulundu' => 'found', '有货' => 'found', 'not_found' => 'not_found', 'bulunamadı' => 'not_found', '无货' => 'not_found', 'alternative_available' => 'alternative_available', 'alternatif var' => 'alternative_available', '有替代' => 'alternative_available'],
            'para_birimi' => ['usd' => 'USD', 'cny' => 'CNY', 'rmb' => 'CNY', 'try' => 'TRY', 'tl' => 'TRY', 'eur' => 'EUR'],
            'termin_birimi' => ['calendar_day' => 'calendar_day', 'takvim günü' => 'calendar_day', 'working_day' => 'working_day', 'iş günü' => 'working_day', 'week' => 'week', 'hafta' => 'week'],
            default => [],
        };

        return $esleme[$k] ?? ($alan === 'para_birimi' ? mb_strtoupper($k) : $k);
    }
}
