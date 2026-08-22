<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Dates;
use App\Models\ListRepository;
use App\Models\ProductRepository;
use App\Models\SettingsRepository;
use DateTimeZone;

/**
 * docs/10 §3–§4 nesnelerini üretir — API'nin dışa gösterdiği TEK biçim burasıdır.
 *
 * Para: TL değerleri DB'de saklanmaz, kurla burada hesaplanır (K24).
 * KUR SEÇİMİ (K4 — canlı vaka düzeltmesi): kur KİLİTLİYSE listenin kilitli kuru,
 * TASLAKTAYSA ayarlardaki GÜNCEL kur kullanılır. Eskiden taslak da oluşturma
 * anındaki kopyayı gösteriyordu; kullanıcı kuru güncelleyip listeyi iletince
 * bayat kur kilitleniyor, hesaplar "tutarsız" görünüyordu.
 * Hesap MoneyService'ten geçer; bu sınıfta bcmath çağrısı YOKTUR (K29).
 */
final class ListPresenter
{
    public function __construct(
        private readonly ListRepository $lists,
        private readonly ProductRepository $products,
        private readonly MoneyService $money,
        private readonly DateTimeZone $timezone,
        private readonly ?SettingsRepository $settings = null,
    ) {
    }

    /**
     * K4: kilitliyse listenin kuru, taslaktaysa ayarlardaki güncel kur.
     *
     * @param array<string, mixed> $listRow
     *
     * @return array{string, string} [yuanRate, usdRate]
     */
    private function effectiveRates(array $listRow): array
    {
        $locked = ($listRow['rate_locked_at'] ?? null) !== null;
        if (!$locked && $this->settings !== null) {
            return [
                $this->money->formatRate($this->settings->yuanRate()),
                $this->money->formatRate($this->settings->usdRate()),
            ];
        }

        return [
            $this->money->formatRate((string) $listRow['yuan_rate']),
            $this->money->formatRate((string) $listRow['usd_rate']),
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public function list(array $row): array
    {
        $listId = (int) $row['id'];
        $productRows = $this->products->forList($listId);
        [$yuanRate, $usdRate] = $this->effectiveRates($row);

        $lastExport = $this->lists->lastExport($listId);
        $revision = (int) $row['revision'];

        return [
            'id' => $listId,
            'name' => (string) $row['name'],
            'period' => $this->nullableString($row['period']),
            'supplier_name' => $this->nullableString($row['supplier_name']),
            'note' => $this->nullableString($row['note']),
            'status' => (string) $row['status'],
            'visibility' => (string) $row['visibility'],
            'yuan_rate' => $yuanRate,
            'usd_rate' => $usdRate,
            'rate_locked_at' => $this->nullableDate($row['rate_locked_at']),
            'revision' => $revision,
            'share_token_prefix' => $this->nullableString($row['share_token_prefix']),
            'share_expires_at' => $this->nullableDate($row['share_expires_at']),
            'product_count' => count($productRows),
            'progress' => $this->progress($productRows),
            'totals' => $this->totals($productRows, $yuanRate, $usdRate),
            'last_export' => $lastExport === null ? null : [
                'format' => (string) $lastExport['format'],
                'created_at' => Dates::toIso((string) $lastExport['created_at'], $this->timezone),
                'list_revision' => (int) $lastExport['list_revision'],
            ],
            // K25: "çıktı güncel değil" artık revision karşılaştırmasıdır, updated_at değil.
            'is_export_stale' => $lastExport !== null && (int) $lastExport['list_revision'] !== $revision,
            'created_at' => Dates::toIso((string) $row['created_at'], $this->timezone),
            'updated_at' => Dates::toIso((string) $row['updated_at'], $this->timezone),
            'archived_at' => $this->nullableDate($row['archived_at']),
            'deleted_at' => $this->nullableDate($row['deleted_at']),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    public function lists(array $rows): array
    {
        return array_map(fn (array $row): array => $this->list($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $listRow
     *
     * @return array<string, mixed>
     */
    public function product(array $row, array $listRow): array
    {
        [$yuanRate, $usdRate] = $this->effectiveRates($listRow);
        $priceYuan = $this->money->format((string) $row['price_yuan']);
        $priceDdp = $this->money->format((string) $row['price_ddp_usd']);
        $qty = (int) $row['qty'];
        $priceTargetRaw = $this->nullableString($row['price_target_try'] ?? null);
        [$priceTarget, $unitProfit, $lineProfit] = $this->profit(
            $priceTargetRaw === null ? null : $this->money->format($priceTargetRaw),
            $this->money->convert($priceDdp, $usdRate),
            $this->money->convert($priceYuan, $yuanRate),
            $qty,
        );

        return [
            'id' => (int) $row['id'],
            'list_id' => (int) $row['list_id'],
            'sort_no' => (int) $row['sort_no'],
            'category_id' => $this->nullableInt($row['category_id']),
            'platform' => $this->nullableString($row['platform']),
            'external_id' => $this->nullableString($row['external_id']),
            'name' => (string) $row['name'],
            'name_original' => $this->nullableString($row['name_original']),
            'detail' => $this->nullableString($row['detail']),
            'url' => $this->nullableString($row['url']),
            'vendor_name' => $this->nullableString($row['vendor_name']),
            'vendor_url' => $this->nullableString($row['vendor_url']),
            'sku_selection' => $this->decodeJson($row['sku_selection']),
            'sku_matrix' => $this->decodeJson($row['sku_matrix']),
            'main_image' => $this->nullableString($row['main_image']),
            'video_url' => $this->nullableString($row['video_url']),
            // İE#13 F1: MOQ gibi alanlar yakalamanın RAW bloğundan okunur (uydurma kolon yok).
            'raw_attributes' => $this->nullableString($row['raw_attributes'] ?? null),
            'qty' => $qty,
            'price_yuan' => $priceYuan,
            'price_ddp_usd' => $priceDdp,
            // Birim fiyatın TL karşılığı (referans Excel'deki TL sütunu): ¥9,00 × 7,04 = ₺63,36
            'price_yuan_tl' => $this->money->convert($priceYuan, $yuanRate),
            'price_ddp_tl' => $this->money->convert($priceDdp, $usdRate),
            'line_total_yuan' => $this->money->lineTotal($priceYuan, $qty),
            'line_total_yuan_tl' => $this->money->lineTotalInTl($priceYuan, $qty, $yuanRate),
            // İE#13 F5 — hedef satış ve kâr: YALNIZ iç kopya çıktısında basılır,
            // firma kopyasında ve paylaşım sayfasında ASLA görünmez.
            'price_target_try' => $priceTarget,
            'unit_profit_try' => $unitProfit,
            'line_profit_try' => $lineProfit,
            'units_per_carton' => $this->nullableInt($row['units_per_carton']),
            'tracking_no' => $this->nullableString($row['tracking_no']),
            'status' => (string) $row['status'],
            'note' => $this->nullableString($row['note']),
            'images' => $this->products->images((int) $row['id']),
            'created_at' => Dates::toIso((string) $row['created_at'], $this->timezone),
            'updated_at' => Dates::toIso((string) $row['updated_at'], $this->timezone),
            'deleted_at' => $this->nullableDate($row['deleted_at']),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed> $listRow
     *
     * @return list<array<string, mixed>>
     */
    public function productsOf(array $rows, array $listRow): array
    {
        return array_map(fn (array $row): array => $this->product($row, $listRow), $rows);
    }

    /**
     * @param list<array<string, mixed>> $productRows
     *
     * @return array<string, int>
     */
    private function progress(array $productRows): array
    {
        $progress = array_fill_keys(array_keys(StateMachine::PRODUCT_TRANSITIONS), 0);
        foreach ($productRows as $row) {
            $status = (string) $row['status'];
            if (array_key_exists($status, $progress)) {
                $progress[$status]++;
            }
        }

        return $progress;
    }

    /**
     * Liste toplamları — K24: her satır ayrı yuvarlanır, sonra toplanır.
     *
     * @param list<array<string, mixed>> $productRows
     *
     * @return array<string, int|string>
     */
    /**
     * F5 kâr hesabı — MALİYET TABANI: birim DDP ₺ (KDV dahil, kapıya teslim).
     * DDP girilmemişse (0) taban birim Yuan ₺'dir; bu durum çıktıda "DDP yok" demektir
     * ve kârı olduğundan büyük göstermemek için en azından mal bedeli düşülür.
     * Hedef girilmemişse üçü de null döner — çıktıda "—" basılır.
     *
     * @return array{0: string|null, 1: string|null, 2: string|null}
     */
    private function profit(?string $priceTarget, string $ddpTl, string $yuanTl, int $qty): array
    {
        if ($priceTarget === null) {
            return [null, null, null];
        }

        // İE#19 E13 — DDP YOKSA KÂR HESAPLANMAZ.
        //
        // Eskiden DDP boşsa maliyet yerine Yuan'ın kur karşılığı konuyordu. O sayı
        // ürünün Çin'deki etiket fiyatıdır: nakliye, gümrük, vergi ve DDP hizmet
        // bedeli İÇİNDE YOKTUR. Ondan çıkarılan fark "kâr" değildir — gerçek kârdan
        // sistematik olarak YÜKSEKTİR ve fiyat kararlarını yanlış yöne çeker.
        // Veri yoksa sayı uydurmayız: hücre "—" basar (aynı disiplin: skor, menşe).
        if (!$this->money->isPositive($ddpTl)) {
            return [$priceTarget, null, null];
        }

        $birimKar = $this->money->subtract($priceTarget, $ddpTl);
        $satirKar = $this->money->times($birimKar, $qty);

        return [$priceTarget, $birimKar, $satirKar];
    }

    /**
     * @param list<array<string, mixed>> $productRows
     *
     * @return array<string, string|int>
     */
    private function totals(array $productRows, string $yuanRate, string $usdRate): array
    {
        $qty = 0;
        $yuanLines = [];
        $yuanTlLines = [];
        $ddpLines = [];
        $ddpTlLines = [];

        foreach ($productRows as $row) {
            // İptal edilen ürün toplamlara girmez: sipariş edilmeyecek mala para bağlanmaz.
            if ((string) $row['status'] === StateMachine::PRODUCT_CANCELLED) {
                continue;
            }

            $lineQty = (int) $row['qty'];
            $priceYuan = $this->money->format((string) $row['price_yuan']);
            $priceDdp = $this->money->format((string) $row['price_ddp_usd']);

            $qty += $lineQty;
            $yuanLines[] = $this->money->lineTotal($priceYuan, $lineQty);
            $yuanTlLines[] = $this->money->lineTotalInTl($priceYuan, $lineQty, $yuanRate);
            $ddpLines[] = $this->money->lineTotal($priceDdp, $lineQty);
            $ddpTlLines[] = $this->money->lineTotalInTl($priceDdp, $lineQty, $usdRate);
        }

        return [
            'qty' => $qty,
            'yuan' => $this->money->sum($yuanLines),
            'yuan_tl' => $this->money->sum($yuanTlLines),
            'ddp_usd' => $this->money->sum($ddpLines),
            'ddp_tl' => $this->money->sum($ddpTlLines),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function nullableDate(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : Dates::toIso((string) $value, $this->timezone);
    }

    private function decodeJson(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return json_decode($value, true) ?? null;
    }
}
