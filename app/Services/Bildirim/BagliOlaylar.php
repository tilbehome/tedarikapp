<?php

declare(strict_types=1);

namespace App\Services\Bildirim;

/**
 * BAĞLANMIŞ OLAYLAR SİCİLİ (V3-B A3).
 *
 * Katalog 37 olay tanımlıyor; bunların bir kısmının kaynağı bugün YOK
 * (firma portalı, teklif geçerliliği, yakalama metrik tablosu, kalite kapısı,
 * sağlayıcı kotası). Nöbet Raporu 5 mutabakatı: **28 tetiklenebilir + 9
 * tetiklenemez = 37**; C3 sözlük içe aktarımıyla NTF-GLOSSARY-IMPORTED de
 * bağlandı → **29 bağlı + 8 bekleyen**.
 *
 * Bu dosya niçin var: "kaç olay bağlandı?" sorusunun cevabı yorumla değil
 * SAYIMLA verilmelidir. D12'de tam bu tür bir belirsizlik "üç dil zaten
 * tamamdı" yalanını üretmişti — panel bir şey söylüyordu, kod başka bir şey
 * yapıyordu. BildirimBagliSayimTest bu iki listeyi katalogla karşılaştırır;
 * bir olay bağlanır ama buraya yazılmazsa, ya da buraya yazılır ama katalogda
 * yoksa, CI KIRMIZI olur.
 */
final class BagliOlaylar
{
    /**
     * Kodda bir tetik noktası OLAN olaylar. Sıra katalogla aynı tutulur.
     *
     * @var list<string>
     */
    public const BAGLI = [
        // — kuyruk —
        'NTF-CAPTURE-ACCEPTED',
        'NTF-JOB-RETRY-SCHEDULED',
        'NTF-JOB-RECOVERED',
        'NTF-JOB-DEAD',
        'NTF-JOB-REPLAYED',
        'NTF-QUEUE-STALLED',
        'NTF-DUPLICATE-SUPPRESSED',
        // — liste —
        'NTF-LIST-CREATED',
        'NTF-LIST-PRODUCTS-ADDED',
        'NTF-LIST-PRODUCTS-REMOVED',
        'NTF-LIST-STATUS-CHANGED',
        'NTF-LIST-RATE-LOCKED',
        'NTF-LIST-RATE-DRIFT',
        'NTF-LIST-SENT',
        'NTF-LIST-REVISION-CREATED',
        'NTF-LIST-ARCHIVED',
        // — paylaşım —
        'NTF-SHARE-CREATED',
        'NTF-SHARE-KEY-RENEWED',
        'NTF-SHARE-INVALID-ACCESS',
        'NTF-SHARE-RATE-LIMITED',
        'NTF-SHARE-REVOKED',
        // — sistem —
        'NTF-FX-UPDATED',
        'NTF-TOKEN-INVALID',
        'NTF-SETTINGS-CHANGED',
        // — çeviri —
        'NTF-TRANSLATION-BATCH-COMPLETE',
        'NTF-TRANSLATION-JOB-FAILED',
        'NTF-GLOSSARY-IMPORTED',
    ];

    /**
     * Kaynağı OLMAYAN olaylar ve sebebi. Boş bir gerekçe kabul edilmez:
     * "neden bağlanmadı?" sorusunun cevabı burada yazılı olmalı ki bir sonraki
     * fazda kimse yeniden araştırmasın.
     *
     * @var array<string, string>
     */
    public const BEKLEYEN = [
        // V3-B UYGULAMA BULGUSU (PM'e yükseltildi): Nöbet Raporu 5 bu ikisini
        // "bağlanabilir" saymıştı; kodu yazarken yanlış olduğu görüldü.
        // Sunucu bu olayları BUGÜNKÜ sözleşmeyle üretemez ve capture şeması
        // PM kararıdır (CLAUDE.md §6) — sicile "bağlı" yazmak, hiçbir şey
        // yayımlamayan bir satırı yeşil göstermek olurdu.
        'NTF-CAPTURE-BATCH-ACCEPTED' => 'API tarafında "oturum grubu" kavramı yok; her yakalama tekil istektir. '
            . 'Toplu kabul, capture şemasına grup alanı eklenmeden üretilemez (PM kararı).',
        'NTF-OFFLINE-QUEUED' => 'Olay eklenti ÇEVRİMDIŞIYKEN doğar; sunucuya o an ulaşamaz. '
            . 'Kuyruk boşalırken geriye dönük bildirilebilmesi için capture yükünde '
            . '"kuyrukta bekledi" işareti gerekir — şema değişikliği, PM kararı.',
        'NTF-LIST-READY-BLOCKED' => 'HAZIR kapısı V3 liste durumudur; bugünkü durum makinesinde yok (V3-C).',
        'NTF-SUPPLIER-RESPONSE-RECEIVED' => 'Firma portalı yok (V3-C).',
        'NTF-LIST-EXPIRED' => 'Teklif geçerlilik süresi kavramı yok (V3-C).',
        'NTF-CAPTURE-HEALTH-LOW' => 'Yakalama başarım metriği tutulmuyor; yalnız activity_log var.',
        'NTF-CAPTURE-NO-ACTIVITY' => 'Aynı metrik eksikliği — "son başarılı yakalama" ölçülmüyor.',
        'NTF-TRANSLATION-QUALITY-BLOCKED' => 'Görev #4A kritik kalite kapısı uygulanmadı.',
        'NTF-TRANSLATION-QUOTA-LOW' => 'Sağlayıcı kalan kotayı bildirmiyor; tahmin yürütmek "bilinmeyen ≠ sıfır" ilkesini çiğner (K67).',
        'NTF-TRANSLATION-QUOTA-EXHAUSTED' => 'Aynı sebep; HataSinifi yalnız hata METNİNDEN 429/kota sezgisi yapar, bu bir ölçüm değildir.',
    ];
}
