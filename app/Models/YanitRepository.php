<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Connection;
use DateTimeImmutable;

/**
 * FİRMA YANITI DEPOSU — `quote_responses` / `quote_lines` / `quote_price_tiers` /
 * `quote_alternatives` (0036; V3-C Aşama 2.2).
 *
 * Tur başına TEK taslak yanıt satırı vardır (surum 1); yapıştır ve Excel
 * kanalları aynı taslağa yazar. `ham_kaynak` uygulanan KAYNAKLARIN JSON
 * listesidir (kanal · parmak izi · zaman · aktör): hem denetim izi hem
 * mükerrer uygulama kilidi (aynı parmak izi ikinci kez yazılmaz).
 *
 * Alternatif AYRI nesnedir (#28): `quote_alternatives` satırı `quote_lines`
 * satırına bağlıdır; asıl satır "bulunamadı/alternatif var" olarak kalır.
 */
final class YanitRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return ?array<string, mixed> */
    public function turunTaslagi(int $turId): ?array
    {
        $st = $this->connection->pdo()->prepare('SELECT * FROM quote_responses WHERE supplier_round_id = :tur ORDER BY surum DESC, id DESC LIMIT 1');
        $st->execute(['tur' => $turId]);
        $row = $st->fetch();

        return is_array($row) ? $row : null;
    }

    public function taslakAc(int $turId, string $kanal, DateTimeImmutable $now): int
    {
        $pdo = $this->connection->pdo();
        $st = $pdo->prepare(
            'INSERT INTO quote_responses (supplier_round_id, surum, kanal, ham_kaynak, kismi, created_at, updated_at)
             VALUES (:tur, 1, :kanal, :kaynak, 1, :now, :now2)',
        );
        $st->execute(['tur' => $turId, 'kanal' => $kanal, 'kaynak' => '[]', 'now' => $now->format('Y-m-d H:i:s'), 'now2' => $now->format('Y-m-d H:i:s')]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Uygulanan kaynak izi: `ham_kaynak` JSON listesine eklenir, `kanal` son kaynağı gösterir.
     *
     * @param array<string, mixed> $kaynak
     */
    public function kaynakEkle(int $responseId, string $kanal, array $kaynak, DateTimeImmutable $now): void
    {
        $liste = $this->kaynaklar($responseId);
        $liste[] = $kaynak;
        $st = $this->connection->pdo()->prepare('UPDATE quote_responses SET kanal = :kanal, ham_kaynak = :kaynak, updated_at = :now WHERE id = :id');
        $st->execute([
            'kanal' => $kanal,
            'kaynak' => json_encode($liste, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'now' => $now->format('Y-m-d H:i:s'),
            'id' => $responseId,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function kaynaklar(int $responseId): array
    {
        $st = $this->connection->pdo()->prepare('SELECT ham_kaynak FROM quote_responses WHERE id = :id');
        $st->execute(['id' => $responseId]);
        $ham = $st->fetchColumn();
        if (!is_string($ham) || $ham === '') {
            return [];
        }
        $liste = json_decode($ham, true);

        return is_array($liste) ? array_values($liste) : [];
    }

    /** Bu parmak izi daha önce bu taslağa uygulandı mı? */
    public function parmakIziVar(int $responseId, string $parmakIzi): bool
    {
        foreach ($this->kaynaklar($responseId) as $k) {
            if (($k['parmak_izi'] ?? null) === $parmakIzi) {
                return true;
            }
        }

        return false;
    }

    /**
     * Taslağın satırları (kademeler + alternatif gömülü), rfq_satir_id anahtarlı.
     *
     * @return array<string, array{satir: array<string, mixed>, kademeler: list<array<string, mixed>>, alternatif: ?array<string, mixed>}>
     */
    public function satirlar(int $responseId): array
    {
        $pdo = $this->connection->pdo();
        $st = $pdo->prepare('SELECT * FROM quote_lines WHERE quote_response_id = :id ORDER BY id');
        $st->execute(['id' => $responseId]);
        $sonuc = [];
        $idler = [];
        foreach ($st->fetchAll() ?: [] as $row) {
            $sonuc[(string) $row['rfq_satir_id']] = ['satir' => $row, 'kademeler' => [], 'alternatif' => null];
            $idler[(int) $row['id']] = (string) $row['rfq_satir_id'];
        }
        if ($idler === []) {
            return $sonuc;
        }
        $yer = implode(',', array_fill(0, count($idler), '?'));
        $k = $pdo->prepare('SELECT * FROM quote_price_tiers WHERE quote_line_id IN (' . $yer . ') ORDER BY quote_line_id, sira, id');
        $k->execute(array_keys($idler));
        foreach ($k->fetchAll() ?: [] as $row) {
            $sonuc[$idler[(int) $row['quote_line_id']]]['kademeler'][] = $row;
        }
        $a = $pdo->prepare('SELECT * FROM quote_alternatives WHERE quote_line_id IN (' . $yer . ') ORDER BY id');
        $a->execute(array_keys($idler));
        foreach ($a->fetchAll() ?: [] as $row) {
            $sonuc[$idler[(int) $row['quote_line_id']]]['alternatif'] = $row;
        }

        return $sonuc;
    }

    /**
     * Kanonik satırı yazar (varsa günceller), kademeleri ve alternatifi bütünüyle değiştirir.
     *
     * @param array<string, mixed> $s
     */
    public function satirYaz(int $responseId, array $s, DateTimeImmutable $now): int
    {
        $pdo = $this->connection->pdo();
        $zaman = $now->format('Y-m-d H:i:s');
        $kolonlar = [
            'yanit_durumu' => $s['yanit_durumu'],
            'ddp_birim_fiyat' => $s['ddp_birim_fiyat'],
            'para_birimi' => $s['para_birimi'],
            'ddp_kdv_dahil_onayi' => $s['ddp_kdv_dahil_onayi'] === null ? null : (int) $s['ddp_kdv_dahil_onayi'],
            'moq_deger' => $s['moq_deger'],
            'moq_birim' => $s['moq_birim'],
            'termin_baslangici' => $s['termin_baslangici'],
            'termin_baslangici_aciklamasi' => $s['termin_baslangici_aciklamasi'],
            'termin_suresi' => $s['termin_suresi'],
            'termin_birimi' => $s['termin_birimi'],
            'koli_ici_adet' => $s['koli_ici_adet'],
            'koli_uzunluk_cm' => $s['koli_uzunluk_cm'],
            'koli_genislik_cm' => $s['koli_genislik_cm'],
            'koli_yukseklik_cm' => $s['koli_yukseklik_cm'],
            'koli_cbm' => $s['koli_cbm'],
            'koli_brut_kg' => $s['koli_brut_kg'],
            'koli_net_kg' => $s['koli_net_kg'],
            'ambalaj' => $s['ambalaj'],
            'firma_notu' => $s['firma_notu'],
        ];

        $bul = $pdo->prepare('SELECT id FROM quote_lines WHERE quote_response_id = :r AND rfq_satir_id = :s');
        $bul->execute(['r' => $responseId, 's' => $s['rfq_satir_id']]);
        $mevcut = $bul->fetchColumn();

        if ($mevcut !== false) {
            $lineId = (int) $mevcut;
            $set = implode(', ', array_map(static fn (string $k): string => $k . ' = :' . $k, array_keys($kolonlar)));
            $st = $pdo->prepare('UPDATE quote_lines SET ' . $set . ', updated_at = :updated WHERE id = :id');
            $st->execute($kolonlar + ['updated' => $zaman, 'id' => $lineId]);
        } else {
            $st = $pdo->prepare(
                'INSERT INTO quote_lines (quote_response_id, rfq_satir_id, ' . implode(', ', array_keys($kolonlar)) . ', created_at, updated_at)
                 VALUES (:response, :rfq, ' . implode(', ', array_map(static fn (string $k): string => ':' . $k, array_keys($kolonlar))) . ', :created, :updated)',
            );
            $st->execute($kolonlar + ['response' => $responseId, 'rfq' => $s['rfq_satir_id'], 'created' => $zaman, 'updated' => $zaman]);
            $lineId = (int) $pdo->lastInsertId();
        }

        $pdo->prepare('DELETE FROM quote_price_tiers WHERE quote_line_id = :id')->execute(['id' => $lineId]);
        $ekle = $pdo->prepare(
            'INSERT INTO quote_price_tiers (quote_line_id, sira, min_adet, max_adet, birim_fiyat, para_birimi, kademe_tipi, created_at)
             VALUES (:line, :sira, :min, :max, :fiyat, :para, :tip, :now)',
        );
        foreach (array_values($s['kademeler'] ?? []) as $i => $k) {
            $ekle->execute([
                'line' => $lineId,
                'sira' => $i,
                'min' => $k['min_adet'],
                'max' => $k['max_adet'],
                'fiyat' => $k['birim_fiyat'],
                'para' => $k['para_birimi'] ?? $s['para_birimi'],
                'tip' => $k['kademe_tipi'] ?? 'esik',
                'now' => $zaman,
            ]);
        }

        $pdo->prepare('DELETE FROM quote_alternatives WHERE quote_line_id = :id')->execute(['id' => $lineId]);
        if ($s['alternatif_baglanti'] !== null || $s['alternatif_aciklama'] !== null) {
            $pdo->prepare(
                'INSERT INTO quote_alternatives (quote_line_id, baglanti, aciklama, ddp_birim_fiyat, para_birimi, created_at)
                 VALUES (:line, :baglanti, :aciklama, :fiyat, :para, :now)',
            )->execute([
                'line' => $lineId,
                'baglanti' => $s['alternatif_baglanti'],
                'aciklama' => $s['alternatif_aciklama'],
                'fiyat' => $s['yanit_durumu'] === 'alternative_available' ? $s['ddp_birim_fiyat'] : null,
                'para' => $s['yanit_durumu'] === 'alternative_available' ? $s['para_birimi'] : null,
                'now' => $zaman,
            ]);
        }

        return $lineId;
    }
}
