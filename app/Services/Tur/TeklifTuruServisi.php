<?php

declare(strict_types=1);

namespace App\Services\Tur;

use App\Core\Connection;
use App\Core\Dates;
use App\Models\FirmaRepository;
use App\Models\ListRepository;
use App\Models\ProductRepository;
use App\Models\RateSnapshotRepository;
use App\Models\SettingsRepository;
use App\Models\ShareRepository;
use App\Models\TeklifTuruRepository;
use App\Services\ActivityLog;
use App\Services\Bildirim\BildirimYayinci;
use App\Services\Share\ShareKeyService;
use App\Services\Translation\AdCozumleyici;
use DateTimeImmutable;

/**
 * TEKLİF TURU — ÜRÜN SAHİBİ DÖNGÜSÜ (V3-C Aşama 2.1, İE#23 Blok B kalanı).
 *
 * Birim `liste × firma × tur` (K103). Servis dört sahip eylemini bilir:
 * AÇ (DRAFT) · GÖNDER (SENT) · KARAR (APPROVED / REVISION_REQUESTED / ABANDONED).
 * Hangi geçişin izinli olduğu `TurDurumMakinesi`nin işidir; buradaki her
 * geçiş önce oradan geçer, sonra depoda CAS ile yazılır.
 *
 * GÖNDERİM ANINDA ÜÇ ŞEY DONAR ve üçü TEK TRANSACTION'dadır:
 *   1. RFQ SNAPSHOT — firmaya gösterilen satırlar `rfq_lines`e kopyalanır.
 *      Liste sonradan değişse de tur aynı kalır: "firma neyi gördü?"
 *      sorusunun tek cevabı bu kopyadır (#15 §1 `rfq_snapshot_id`).
 *   2. KUR DÖRTLÜSÜ (K104) — tur açılış anında ZATEN kopyalanmıştır; gönderim
 *      onu değiştirmez, `kur_kilit_at` yazar. `rate_snapshot_id` provenance.
 *   3. PAYLAŞIM — `shares` satırı TUR kimliğiyle açılır (liste geneli değil),
 *      6 haneli anahtar üretilir, gönderim günlüğüne düşer. Tam token ve
 *      anahtar YALNIZ bu yanıtta döner; DB'de hash + panel gösterimi için
 *      şifreli kopya durur (v1.2.1 D8).
 *
 * Üçü bir arada başarısız olursa hiçbiri olmaz: snapshot'sız paylaşım
 * firmaya boş sayfa, paylaşımsız snapshot ise kimsenin görmeyeceği bir kopya
 * demektir.
 */
final class TeklifTuruServisi
{
    public const ETIKET_ONEKI = 'R';

    public function __construct(
        private readonly Connection $connection,
        private readonly TeklifTuruRepository $turlar,
        private readonly FirmaRepository $firmalar,
        private readonly ListRepository $listeler,
        private readonly ProductRepository $urunler,
        private readonly ShareRepository $paylasimlar,
        private readonly ShareKeyService $anahtar,
        private readonly SettingsRepository $ayarlar,
        private readonly RateSnapshotRepository $kurlar,
        private readonly ActivityLog $aktivite,
        private readonly TurDurumMakinesi $makine = new TurDurumMakinesi(),
        private readonly ?AdCozumleyici $adCozumleyici = null,
        private readonly ?BildirimYayinci $bildirim = null,
    ) {
    }

    /**
     * TUR AÇ (DRAFT).
     *
     * Kur dörtlüsü BURADA kopyalanır (K104): açılış anındaki kur, turun kuru
     * olur. Gönderimde yalnız kilit anı yazılır. Revizyonda `inherit` eski
     * turun kopyasını taşır, `refresh` yeniden okur.
     *
     * @param array{gecerlilik_gun?: int|null, portal_dili?: string|null, parent_round_id?: int|null,
     *              rate_policy?: string, kur?: array{para_birimi: string, deger: string, kaynak: string, snapshot_id: int|null}|null} $secenekler
     * @return array<string, mixed> tur satırı
     *
     * @throws TurIslemiReddedildi liste kapalı / firma yok / açık tur var / liste boş
     * @throws TurGecisiReddedildi durum makinesi geçişi reddederse
     */
    public function ac(int $listId, int $supplierId, DateTimeImmutable $now, ?int $actorId = null, ?string $ip = null, array $secenekler = []): array
    {
        $liste = $this->listeler->find($listId);
        if ($liste === null) {
            throw new TurIslemiReddedildi('LISTE_YOK', 'Liste bulunamadı.');
        }
        if (in_array((string) $liste['status'], ['completed', 'cancelled'], true)) {
            throw new TurIslemiReddedildi('LISTE_KAPALI', 'Kapalı listeye teklif turu açılamaz; listeyi kopyalayın.');
        }
        $firma = $this->firmalar->find($supplierId);
        if ($firma === null) {
            throw new TurIslemiReddedildi('FIRMA_YOK', 'Firma bulunamadı.');
        }
        if ($this->turlar->firmaninAcikTuru($listId, $supplierId) !== null) {
            throw new TurIslemiReddedildi('TUR_ACIK', 'Bu firma için zaten açık bir tur var; önce onu sonuçlandırın.');
        }
        if ($this->urunler->countForList($listId) === 0) {
            throw new TurIslemiReddedildi('LISTE_BOS', 'Ürünsüz listeye tur açılamaz; firmaya boş sayfa gider.');
        }

        $kur = $secenekler['kur'] ?? $this->guncelKur($liste);
        $turId = $this->turlar->ac([
            'list_id' => $listId,
            'supplier_id' => $supplierId,
            'tur_no' => $this->turlar->sonrakiTurNo($listId, $supplierId),
            'parent_round_id' => $secenekler['parent_round_id'] ?? null,
            'rate_policy' => $secenekler['rate_policy'] ?? 'inherit',
            'gecerlilik_gun' => $secenekler['gecerlilik_gun'] ?? (isset($firma['varsayilan_gecerlilik_gun']) ? (int) $firma['varsayilan_gecerlilik_gun'] : null),
            'portal_dili' => $secenekler['portal_dili'] ?? (string) ($firma['varsayilan_dil'] ?? 'zh'),
            'kur_para_birimi' => $kur['para_birimi'],
            'kur_degeri' => $kur['deger'],
            'kur_kaynagi' => $kur['kaynak'],
            'kur_kilit_at' => null,
            'rate_snapshot_id' => $kur['snapshot_id'],
        ], $now);

        $this->aktivite->record('supplier_round', $turId, 'round_drafted', sprintf('%s · R%d taslak', (string) $firma['ad'], $this->turlar->find($turId)['tur_no'] ?? 1), $ip, $now, ActivityLog::ACTOR_ADMIN, $actorId);

        $tur = $this->turlar->find($turId);
        assert($tur !== null);

        return $tur;
    }

    /**
     * GÖNDER (DRAFT → SENT): snapshot + kur kilidi + paylaşım, tek transaction.
     *
     * @param array{gecerlilik_gun?: int|null, kanal?: string|null, alici?: string|null, dil?: string|null} $secenekler
     * @return array{tur: array<string, mixed>, share_token: string, erisim_anahtari: string, satir_sayisi: int}
     */
    public function gonder(int $turId, DateTimeImmutable $now, ?int $actorId = null, ?string $ip = null, array $secenekler = []): array
    {
        $tur = $this->turlar->find($turId);
        if ($tur === null) {
            throw new TurIslemiReddedildi('TUR_YOK', 'Tur bulunamadı.');
        }
        $this->makine->dogrula((string) $tur['state'], 'SENT', 'gönderim');
        if (!$this->makine->sahipYapabilirMi((string) $tur['state'], 'SENT')) {
            throw new TurIslemiReddedildi('TUR_GECIS', 'Bu geçiş sahibin elinde değil.');
        }

        $liste = $this->listeler->find((int) $tur['list_id']);
        if ($liste === null) {
            throw new TurIslemiReddedildi('LISTE_YOK', 'Liste bulunamadı.');
        }
        $satirlar = $this->urunler->forList((int) $tur['list_id']);
        if ($satirlar === []) {
            throw new TurIslemiReddedildi('LISTE_BOS', 'Ürünsüz liste gönderilemez.');
        }

        $gecerlilikGun = $secenekler['gecerlilik_gun'] ?? (isset($tur['gecerlilik_gun']) ? (int) $tur['gecerlilik_gun'] : null);
        $token = bin2hex(random_bytes(32));
        $anahtar = $this->anahtar->uret();

        /** @var array{snapshot: int, share: int} $sonuc */
        $sonuc = $this->connection->transaction(function () use ($tur, $liste, $satirlar, $now, $actorId, $token, $anahtar, $gecerlilikGun, $secenekler): array {
            // 1) RFQ snapshot — firmanın göreceği satırlar donar.
            $snapshotId = $this->turlar->rfqSnapshotAc((int) $tur['list_id'], (int) $liste['revision'], count($satirlar), $actorId, $now);
            foreach ($satirlar as $sira => $urun) {
                $this->turlar->rfqSatiriEkle($snapshotId, $this->rfqSatiri($urun, $sira + 1), $now);
            }

            // 3) Paylaşım — TUR kimliğiyle. Anahtar hash + şifreli düz kopya (D8).
            $bitis = $gecerlilikGun !== null && $gecerlilikGun > 0
                ? Dates::toStorage($now->modify('+' . $gecerlilikGun . ' days'))
                : null;
            $shareId = $this->paylasimlar->ac([
                'list_id' => (int) $tur['list_id'],
                'supplier_round_id' => (int) $tur['id'],
                'token_hash' => hash('sha256', $token),
                'token_prefix' => substr($token, 0, 8),
                'expires_at' => $bitis,
            ], $now);
            $this->paylasimlar->anahtariYaz($shareId, $this->anahtar->hash($anahtar), $anahtar, $now);
            $this->paylasimlar->gonderimKaydet([
                'share_id' => $shareId,
                'supplier_round_id' => (int) $tur['id'],
                'kanal' => (string) ($secenekler['kanal'] ?? 'panel'),
                'alici' => $secenekler['alici'] ?? null,
                'dil' => $secenekler['dil'] ?? (string) $tur['portal_dili'],
                'gonderen_id' => $actorId,
            ], $now);

            // 2) Durum + kilit anı — CAS. Tutmazsa başka bir sekme önce davrandı.
            $yazildi = $this->turlar->durumGecisi((int) $tur['id'], (string) $tur['state'], 'SENT', $now, null, [
                'rfq_snapshot_id' => $snapshotId,
                'share_id' => $shareId,
                'sent_at' => Dates::toStorage($now),
                'kur_kilit_at' => Dates::toStorage($now),
                'gecerlilik_gun' => $gecerlilikGun,
                'valid_until' => $bitis,
            ]);
            if (!$yazildi) {
                throw new TurIslemiReddedildi('TUR_GECIS', 'Tur bu arada başka bir işlemle değişti; sayfayı yenileyin.');
            }

            return ['snapshot' => $snapshotId, 'share' => $shareId];
        });

        $auditId = $this->aktivite->record(
            'supplier_round',
            (int) $tur['id'],
            'round_sent',
            sprintf('%s · R%d gönderildi · %d satır · önek:%s', (string) $tur['firma_adi'], (int) $tur['tur_no'], count($satirlar), substr($token, 0, 8)),
            $ip,
            $now,
            ActivityLog::ACTOR_ADMIN,
            $actorId,
        );
        // Tur olayları için ayrı NTF kodu PM kararı bekliyor (#15 §8); en yakın
        // mevcut olay: liste gönderildi + paylaşım açıldı.
        $this->bildirim?->guvenliYayimla('NTF-LIST-SENT', [
            'liste_id' => (int) $tur['list_id'],
            'liste_adi' => (string) $tur['liste_adi'],
            'firma_adi' => (string) $tur['firma_adi'],
            'tur_no' => (int) $tur['tur_no'],
        ], $auditId);

        $guncel = $this->turlar->find((int) $tur['id']);
        assert($guncel !== null);

        return [
            'tur' => $guncel,
            'share_token' => $token,
            'erisim_anahtari' => $anahtar,
            'satir_sayisi' => count($satirlar),
        ];
    }

    /**
     * RESPONDED → APPROVED.
     *
     * @return array<string, mixed>
     */
    public function onayla(int $turId, DateTimeImmutable $now, ?int $actorId = null, ?string $ip = null): array
    {
        return $this->sahipKarari($turId, 'APPROVED', $now, null, $actorId, $ip, ['approved_at' => Dates::toStorage($now)], 'round_approved');
    }

    /**
     * Herhangi bir açık durumdan ABANDONED (gerekçe zorunlu değil ama kaydedilir).
     *
     * @return array<string, mixed>
     */
    public function vazgec(int $turId, DateTimeImmutable $now, ?string $sebep, ?int $actorId = null, ?string $ip = null): array
    {
        $tur = $this->sahipKarari($turId, 'ABANDONED', $now, $sebep, $actorId, $ip, [], 'round_abandoned');
        // Açık paylaşım varsa kapanır: vazgeçilen turun linki ölmeli.
        $this->paylasimlar->iptalEt((int) $tur['list_id'], $now, (int) $tur['id']);

        return $tur;
    }

    /**
     * REVİZYON İSTE: eski tur REVISION_REQUESTED, yeni tur (tur_no+1) DRAFT.
     *
     * `rate_policy`: inherit → eski turun kur dörtlüsü KOPYALANIR (kör kıyas
     * aynı tabanda), refresh → güncel kur okunur. Seçim yeni turda yazılıdır.
     *
     * @return array<string, mixed> YENİ tur
     */
    public function revizyonIste(int $turId, DateTimeImmutable $now, ?string $sebep, string $ratePolicy = 'inherit', ?int $actorId = null, ?string $ip = null): array
    {
        $eski = $this->sahipKarari($turId, 'REVISION_REQUESTED', $now, $sebep, $actorId, $ip, ['revision_requested_at' => Dates::toStorage($now)], 'round_revision_requested');

        $kur = $ratePolicy === 'refresh' || $eski['kur_degeri'] === null
            ? null
            : [
                'para_birimi' => (string) $eski['kur_para_birimi'],
                'deger' => (string) $eski['kur_degeri'],
                'kaynak' => (string) $eski['kur_kaynagi'],
                'snapshot_id' => $eski['rate_snapshot_id'] === null ? null : (int) $eski['rate_snapshot_id'],
            ];

        return $this->ac((int) $eski['list_id'], (int) $eski['supplier_id'], $now, $actorId, $ip, [
            'parent_round_id' => (int) $eski['id'],
            'rate_policy' => $ratePolicy === 'refresh' ? 'refresh' : 'inherit',
            'gecerlilik_gun' => $eski['gecerlilik_gun'] === null ? null : (int) $eski['gecerlilik_gun'],
            'portal_dili' => (string) $eski['portal_dili'],
            'kur' => $kur,
        ]);
    }

    /**
     * Sunum: satır + `etiket` ("R2 gönderildi") + `kur` dörtlüsü + bekleme.
     *
     * @param array<string, mixed> $tur
     * @return array<string, mixed>
     */
    public function sun(array $tur, DateTimeImmutable $now): array
    {
        $durum = (string) $tur['state'];
        $sentAt = is_string($tur['sent_at'] ?? null) ? new DateTimeImmutable((string) $tur['sent_at']) : null;

        return [
            'id' => (int) $tur['id'],
            'list_id' => (int) $tur['list_id'],
            'liste_adi' => (string) ($tur['liste_adi'] ?? ''),
            'supplier_id' => (int) $tur['supplier_id'],
            'firma_adi' => (string) ($tur['firma_adi'] ?? ''),
            'tur_no' => (int) $tur['tur_no'],
            'parent_round_id' => $tur['parent_round_id'] === null ? null : (int) $tur['parent_round_id'],
            'state' => $durum,
            'etiket' => self::ETIKET_ONEKI . (int) $tur['tur_no'] . ' ' . $this->durumSozu($durum),
            'cikti_terimi' => $this->makine->ciktiTerimi($durum),
            'nihai' => $this->makine->nihaiMi($durum),
            'state_reason' => $tur['state_reason'],
            'rfq_snapshot_id' => $tur['rfq_snapshot_id'] === null ? null : (int) $tur['rfq_snapshot_id'],
            'rate_snapshot_id' => $tur['rate_snapshot_id'] === null ? null : (int) $tur['rate_snapshot_id'],
            'rate_policy' => (string) $tur['rate_policy'],
            'kur' => [
                'para_birimi' => $tur['kur_para_birimi'],
                'deger' => $tur['kur_degeri'],
                'kaynak' => $tur['kur_kaynagi'],
                'kilit_at' => $tur['kur_kilit_at'],
            ],
            'share_id' => $tur['share_id'] === null ? null : (int) $tur['share_id'],
            'gecerlilik_gun' => $tur['gecerlilik_gun'] === null ? null : (int) $tur['gecerlilik_gun'],
            'valid_until' => $tur['valid_until'],
            'portal_dili' => (string) $tur['portal_dili'],
            'goruntulendi' => $tur['first_viewed_at'] !== null,
            'bekleme_gun' => $sentAt === null || in_array($durum, ['APPROVED', 'ABANDONED', 'REVOKED', 'REVISION_REQUESTED'], true)
                ? null
                : max(0, (int) floor(($now->getTimestamp() - $sentAt->getTimestamp()) / 86400)),
            'drafted_at' => $tur['drafted_at'],
            'sent_at' => $tur['sent_at'],
            'first_viewed_at' => $tur['first_viewed_at'],
            'responded_at' => $tur['responded_at'],
            'approved_at' => $tur['approved_at'],
            'revision_requested_at' => $tur['revision_requested_at'],
            'partial_submission_count' => (int) $tur['partial_submission_count'],
            'created_at' => $tur['created_at'],
            'updated_at' => $tur['updated_at'],
        ];
    }

    // ── iç yardımcılar ─────────────────────────────────────────────────

    /**
     * @param array<string, string|int|null> $ekAlanlar
     * @return array<string, mixed>
     */
    private function sahipKarari(int $turId, string $hedef, DateTimeImmutable $now, ?string $sebep, ?int $actorId, ?string $ip, array $ekAlanlar, string $eylem): array
    {
        $tur = $this->turlar->find($turId);
        if ($tur === null) {
            throw new TurIslemiReddedildi('TUR_YOK', 'Tur bulunamadı.');
        }
        $this->makine->dogrula((string) $tur['state'], $hedef, $eylem);
        if (!$this->makine->sahipYapabilirMi((string) $tur['state'], $hedef)) {
            throw new TurIslemiReddedildi('TUR_GECIS', 'Bu geçiş sahibin elinde değil.');
        }
        if (!$this->turlar->durumGecisi($turId, (string) $tur['state'], $hedef, $now, $sebep, $ekAlanlar)) {
            throw new TurIslemiReddedildi('TUR_GECIS', 'Tur bu arada değişti; sayfayı yenileyin.');
        }

        $this->aktivite->record(
            'supplier_round',
            $turId,
            $eylem,
            sprintf('%s · R%d → %s%s', (string) $tur['firma_adi'], (int) $tur['tur_no'], $hedef, $sebep !== null && $sebep !== '' ? ' · ' . mb_substr($sebep, 0, 200) : ''),
            $ip,
            $now,
            ActivityLog::ACTOR_ADMIN,
            $actorId,
        );

        $guncel = $this->turlar->find($turId);
        assert($guncel !== null);

        return $guncel;
    }

    /**
     * Güncel kur dörtlüsü: listenin kilitli kuru varsa O (liste zaten
     * kilitlenmişse tur da aynı tabanı kullanmalı), yoksa aktif snapshot,
     * o da yoksa ayar değeri. Kaynak alanı hangisinin kazandığını yazar.
     *
     * @param array<string, mixed> $liste
     * @return array{para_birimi: string, deger: string, kaynak: string, snapshot_id: int|null}
     */
    private function guncelKur(array $liste): array
    {
        $snapshot = $this->kurlar->aktif('CNY');
        if ($liste['rate_locked_at'] !== null) {
            return ['para_birimi' => 'CNY', 'deger' => $this->dortHane((string) $liste['yuan_rate']), 'kaynak' => 'liste', 'snapshot_id' => $snapshot['id'] ?? null];
        }
        if ($snapshot !== null) {
            return ['para_birimi' => 'CNY', 'deger' => $this->dortHane($snapshot['rate']), 'kaynak' => 'snapshot', 'snapshot_id' => $snapshot['id']];
        }

        return ['para_birimi' => 'CNY', 'deger' => $this->dortHane($this->ayarlar->yuanRate()), 'kaynak' => 'ayar', 'snapshot_id' => null];
    }

    /** DECIMAL(12,4) — bcmath ile, float'a düşmeden (K14). */
    private function dortHane(string $deger): string
    {
        return bcadd(trim($deger) === '' ? '0' : trim($deger), '0', 4);
    }

    /**
     * Ürün satırı → RFQ satırı (rfq-alan-sozlesmesi.json §rfq_satir_alanlari).
     *
     * @param array<string, mixed> $urun
     * @return array{rfq_satir_id: string, product_id: int|null, sira: int, urun_kodu: string|null,
     *              urun_adi_json: string, kaynak_urun_json: string|null, talep_varyant_json: string|null,
     *              talep_miktar: string, talep_birim: string, alici_notu_json: string|null, gorsel_url: string|null}
     */
    private function rfqSatiri(array $urun, int $sira): array
    {
        // D11b: panel, paylaşım ve belge AYNI ad çözümünü kullanır; RFQ de.
        // ZH = kaynak ad (firma kendi dilinde görür), TR/EN = çözümlenmiş.
        $ad = static fn (?array $cozum, string $yedek): string => is_string($cozum['ad'] ?? null) && $cozum['ad'] !== '' ? (string) $cozum['ad'] : $yedek;
        $yedekAd = (string) ($urun['name'] ?? '');
        $adJson = json_encode([
            'tr' => $ad($this->adCozumleyici?->coz($urun, 'tr'), $yedekAd),
            'en' => $ad($this->adCozumleyici?->coz($urun, 'en'), $yedekAd),
            'zh' => (string) ($urun['name_original'] ?? '') !== '' ? (string) $urun['name_original'] : $yedekAd,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $kaynak = array_filter([
            'platform' => $urun['platform'] ?? null,
            'external_id' => $urun['external_id'] ?? null,
            'url' => $urun['url'] ?? null,
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        $varyant = is_string($urun['sku_selection'] ?? null) && $urun['sku_selection'] !== '' ? (string) $urun['sku_selection'] : null;
        $not = is_string($urun['note'] ?? null) && trim((string) $urun['note']) !== ''
            ? json_encode(['tr' => trim((string) $urun['note'])], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            : null;

        return [
            'rfq_satir_id' => $this->uuid(),
            'product_id' => (int) $urun['id'],
            'sira' => $sira,
            'urun_kodu' => 'P' . str_pad((string) (int) $urun['id'], 5, '0', STR_PAD_LEFT),
            'urun_adi_json' => $adJson,
            'kaynak_urun_json' => $kaynak === [] ? null : json_encode($kaynak, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'talep_varyant_json' => $varyant,
            'talep_miktar' => bcadd((string) ((int) ($urun['qty'] ?? 0)), '0', 3),
            'talep_birim' => 'adet',
            'alici_notu_json' => $not,
            'gorsel_url' => is_string($urun['main_image'] ?? null) && $urun['main_image'] !== '' ? (string) $urun['main_image'] : null,
        ];
    }

    private function durumSozu(string $durum): string
    {
        return match ($durum) {
            'DRAFT' => 'taslak',
            'SENT' => 'gönderildi',
            'VIEWED' => 'görüntülendi',
            'PRICING' => 'fiyatlanıyor',
            'RESPONDED' => 'yanıtlandı',
            'REVISION_REQUESTED' => 'revizyon istendi',
            'APPROVED' => 'onaylandı',
            'ABANDONED' => 'vazgeçildi',
            'EXPIRED' => 'süresi doldu',
            'REVOKED' => 'erişim iptal',
            default => mb_strtolower($durum),
        };
    }

    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
