<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Core\Clock;
use App\Models\TranslationCacheRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * K56 KATMAN 2 — LLM ÇEVİRMENİ (İE#20 C4).
 *
 * TASARIM KARARLARI VE GEREKÇELERİ:
 *
 * **Ürünün TAMAMI tek istekte.** Alan alan çeviri bağlamı kaybeder: "白色" tek
 * başına "Beyaz"dır ama "白色 ABS 材质" içinde gövde rengidir. Ayrıca 30 alan için
 * 30 istek, kotayı da süreyi de otuza katlar. JSON girer, JSON çıkar.
 *
 * **TR ve EN AYNI İSTEKTE üretilir** (Ürün Sahibi kararı, 22 Ağu: sistem TAM ÜÇ
 * DİLLİDİR). İki ayrı istek atmak hem iki kat pahalıdır hem de iki dilin
 * birbirinden bağımsız sapmasına yol açar — "Paslanmaz çelik" ile "Stainless
 * steel" aynı kararın iki yüzü olmalıdır. Hedef dil listesi AYARDAN gelir; kodda
 * sabit dil yoktur (yeni dil = ayar, migration değil).
 *
 * **Sözlük isteme GÖMÜLÜR.** Katman 1 kapalı küme terimlerini zaten belirlenimci
 * çeviriyor; aynı terimleri LLM'e de bildirmek, ÜRÜN ADI içinde geçtiklerinde de
 * aynı karşılığın kullanılmasını sağlar. Yoksa sözlük "Paslanmaz çelik" derken
 * başlık "Paslanmaz Çelik" ya da "İnox" olur.
 *
 * **TALİMAT SERTTİR:** pazarlama sıfatı ekleme, ölçü/marka/model/ilan no değiştirme,
 * bilmediğini uydurma. Bunlar süsleme değil, ticari risktir: firmaya giden belgede
 * "premium kalite" yazması bir taahhüttür; "50 cm"in "500 mm" olması bir hatadır.
 *
 * **K54 KORUNUR:** çıktı ÖNERİDİR. Hiçbir alan kendiliğinden yazılmaz.
 * **K61 KORUNUR:** bu sınıf BELGE ÜRETİMİNDE ÇAĞRILMAZ. Belge yalnız önbellekten
 * okur; ağ beklemek, aynı içerikten iki farklı belge üretme riskidir.
 */
final class LlmTranslator implements TranslatorInterface
{
    public const SAGLAYICI_OPENAI = 'openai';
    public const SAGLAYICI_ANTHROPIC = 'anthropic';
    public const SAGLAYICI_DEEPSEEK = 'deepseek';

    /** @var list<string> */
    public const SAGLAYICILAR = [self::SAGLAYICI_OPENAI, self::SAGLAYICI_ANTHROPIC, self::SAGLAYICI_DEEPSEEK];

    /** Güven işaretleri (K56): kullanıcı neye ne kadar güveneceğini bilmeli. */
    public const GUVEN_KESIN = 'sozluk';
    public const GUVEN_ONERI = 'llm';
    public const GUVEN_YEDEK = 'makine';
    public const GUVEN_HAM = 'ham';

    /** İsteme gömülecek en çok sözlük terimi (istem şişmesin). */
    private const SOZLUK_LIMITI = 120;

    public function __construct(
        private readonly Glossary $glossary,
        private readonly CeviriAyarlari $ayarlar,
        private readonly LlmIstemci $istemci,
        private readonly TranslationCacheRepository $cache,
        private readonly Clock $clock,
        private readonly LoggerInterface $logger,
        /** LLM kullanılamadığında düşülecek katman (sözlük + makine). */
        private readonly TranslatorInterface $yedek,
    ) {
    }

    public function name(): string
    {
        return 'llm:' . $this->ayarlar->saglayici();
    }

    /** @return array<string, string> */
    public function getGlossary(string $sourceLang = 'zh'): array
    {
        return $this->glossary->all($sourceLang);
    }

    public static function varsayilanModel(string $saglayici): string
    {
        return match ($saglayici) {
            self::SAGLAYICI_ANTHROPIC => 'claude-sonnet-5',
            self::SAGLAYICI_DEEPSEEK => 'deepseek-chat',
            default => 'gpt-4.1-mini',
        };
    }

    /**
     * Ürünün tamamını çevirir; TR ve EN aynı istekte üretilir.
     *
     * @param array<string, mixed> $urun
     *
     * @return array<string, mixed>
     */
    public function translateProduct(array $urun): array
    {
        $anahtar = $this->ayarlar->anahtar();
        if (!$this->ayarlar->acikMi() || $anahtar === null) {
            // Yapılandırılmamış sistemde çeviri KAYBOLMAZ: katmanlı yedeğe düşer.
            return $this->yedek->translateProduct($urun);
        }

        $diller = $this->ayarlar->hedefDiller();
        $kaynakDil = is_string($urun['source_lang'] ?? null) && $urun['source_lang'] !== ''
            ? (string) $urun['source_lang']
            : Glossary::detect((string) ($urun['name'] ?? ''));

        try {
            $ham = $this->istemci->sor(
                $this->ayarlar->saglayici(),
                $anahtar,
                $this->ayarlar->model(),
                $this->sistemIstemi($kaynakDil, $diller),
                $this->kullaniciIstemi($urun, $kaynakDil, $diller),
            );
            $cozulmus = $this->yanitiCoz($ham, $diller);
        } catch (Throwable $hata) {
            // Sağlayıcı hatası çeviriyi TAMAMEN kaybettirmemeli: yedek katman
            // (sözlük + makine) devreye girer ve sonuç "yedek" olarak işaretlenir.
            $this->logger->warning('LLM çevirisi başarısız, yedek katmana düşüldü', [
                'saglayici' => $this->ayarlar->saglayici(),
                'hata' => $hata->getMessage(),
            ]);

            return $this->yedek->translateProduct($urun);
        }

        return $this->sonucuBirlestir($urun, $cozulmus, $kaynakDil, $diller);
    }

    /** @param list<string> $diller */
    private function sistemIstemi(string $kaynakDil, array $diller): string
    {
        $dilListesi = implode(', ', array_map(static fn (string $d): string => strtoupper($d), $diller));

        return <<<ISTEM
            Sen bir TİCARİ ÜRÜN KATALOĞU çevirmenisin. Kaynak dil: {$kaynakDil}.
            Hedef diller: {$dilListesi}.

            MUTLAK KURALLAR — bunlara uymayan çıktı KULLANILAMAZ:
            1. PAZARLAMA SIFATI EKLEME. "premium", "kaliteli", "şık", "en iyi" gibi
               kaynakta OLMAYAN hiçbir niteleme yazma. Firmaya giden belgede bu bir
               taahhüttür.
            2. SAYI, ÖLÇÜ VE BİRİMLERİ AYNEN KORU. "50cm" → "50 cm" olabilir ama
               "500 mm" OLAMAZ. Dönüştürme yapma.
            3. MARKA, MODEL KODU, İLAN NUMARASI ve STOK KODUNU ÇEVİRME; olduğu gibi bırak.
            4. UYDURMA. Bilmediğin bir terimi tahmin etme; kaynaktaki hâliyle bırak.
            5. Kaynakta olmayan bilgi EKLEME, olan bilgiyi ÇIKARMA.

            SÖZLÜK: aşağıda verilen terimler için VERİLEN karşılığı kullan; başka
            karşılık üretme. Sözlük, kurumsal terminolojidir.

            ÇIKTI: yalnızca JSON. Şema:
            {"ceviriler": {"<dil_kodu>": {"name": "...", "category": "...",
             "attributes": {"<özgün anahtar>": "..."}, "variants": ["..."]}}}
            Girdide olmayan alanı çıktıya KOYMA. Açıklama, yorum, kod bloğu yazma.
            ISTEM;
    }

    /**
     * @param array<string, mixed> $urun
     * @param list<string> $diller
     */
    private function kullaniciIstemi(array $urun, string $kaynakDil, array $diller): string
    {
        $sozluk = $this->glossary->all($kaynakDil);
        if (count($sozluk) > self::SOZLUK_LIMITI) {
            $sozluk = array_slice($sozluk, 0, self::SOZLUK_LIMITI, true);
        }

        $girdi = [
            'hedef_diller' => $diller,
            'sozluk' => $sozluk,
            'urun' => array_filter([
                'name' => $urun['name'] ?? null,
                'category' => $urun['category'] ?? null,
                'attributes' => $urun['attributes'] ?? null,
                'variants' => $urun['variants'] ?? null,
            ], static fn (mixed $v): bool => $v !== null && $v !== [] && $v !== ''),
        ];

        return (string) json_encode($girdi, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    /**
     * Sağlayıcı yanıtını çözer. Model bazen JSON'u kod bloğuna sarar — temizlenir.
     *
     * @param list<string> $diller
     *
     * @return array<string, array<string, mixed>> dil → alanlar
     */
    private function yanitiCoz(string $ham, array $diller): array
    {
        $metin = trim($ham);
        if (str_starts_with($metin, '```')) {
            $metin = (string) preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $metin);
        }

        $veri = json_decode($metin, true);
        if (!is_array($veri) || !is_array($veri['ceviriler'] ?? null)) {
            throw new RuntimeException('LLM yanıtı beklenen JSON şemasında değil.');
        }

        $sonuc = [];
        foreach ($diller as $dil) {
            if (is_array($veri['ceviriler'][$dil] ?? null)) {
                $sonuc[$dil] = $veri['ceviriler'][$dil];
            }
        }

        if ($sonuc === []) {
            throw new RuntimeException('LLM yanıtında istenen dillerin hiçbiri yok.');
        }

        return $sonuc;
    }

    /**
     * LLM çıktısını SÖZLÜKLE HARMANLAR ve güven işaretlerini üretir.
     *
     * Sıra bilinçlidir: sözlükte KESİN karşılığı olan bir terim, LLM ne derse desin
     * sözlükten gelir. Sözlük kurumsal karardır; LLM önericidir.
     *
     * @param array<string, mixed> $urun
     * @param array<string, array<string, mixed>> $cevriler
     * @param list<string> $diller
     *
     * @return array<string, mixed>
     */
    private function sonucuBirlestir(array $urun, array $cevriler, string $kaynakDil, array $diller): array
    {
        $birincil = $diller[0] ?? 'tr';
        $kaynaklar = [];
        $ciktilar = [];

        foreach ($diller as $dil) {
            $dilCevirisi = $cevriler[$dil] ?? [];
            $alanlar = [];

            foreach (['name', 'category'] as $alan) {
                $hamDeger = is_string($urun[$alan] ?? null) ? trim((string) $urun[$alan]) : '';
                if ($hamDeger === '') {
                    continue;
                }

                // Sözlük yalnız BİRİNCİL hedef dil (TR) için kurumsal karardır;
                // diğer diller için sözlük yoktur, LLM çıktısı kullanılır.
                $sozlukten = $dil === 'tr' ? $this->glossary->lookup($hamDeger, $kaynakDil) : null;
                if ($sozlukten !== null) {
                    $alanlar[$alan] = $sozlukten;
                    $kaynaklar[$dil][$alan] = self::GUVEN_KESIN;

                    continue;
                }

                $llmDeger = is_string($dilCevirisi[$alan] ?? null) ? trim((string) $dilCevirisi[$alan]) : '';
                if ($llmDeger !== '') {
                    $alanlar[$alan] = $llmDeger;
                    $kaynaklar[$dil][$alan] = self::GUVEN_ONERI;
                    $this->onbellegeYaz($hamDeger, $llmDeger, $kaynakDil, $dil);

                    continue;
                }

                $alanlar[$alan] = $hamDeger;
                $kaynaklar[$dil][$alan] = self::GUVEN_HAM;
            }

            /** @var array<string, string> $ozellikler */
            $ozellikler = is_array($urun['attributes'] ?? null) ? $urun['attributes'] : [];
            if ($ozellikler !== []) {
                $cevrilenOzellikler = [];
                foreach ($ozellikler as $anahtar => $deger) {
                    $hamDeger = trim((string) $deger);
                    $sozlukten = $dil === 'tr' ? $this->glossary->lookup($hamDeger, $kaynakDil) : null;
                    $llmDeger = is_array($dilCevirisi['attributes'] ?? null)
                        && is_string($dilCevirisi['attributes'][$anahtar] ?? null)
                            ? trim((string) $dilCevirisi['attributes'][$anahtar])
                            : '';

                    $cevrilenOzellikler[(string) $anahtar] = $sozlukten ?? ($llmDeger !== '' ? $llmDeger : $hamDeger);
                }
                $alanlar['attributes'] = $cevrilenOzellikler;
            }

            $ciktilar[$dil] = $alanlar;
        }

        // Geriye dönük uyum: mevcut çağıranlar (panel önerisi, eklenti) düz alanlar
        // bekler. Birincil dil düz alanlara, tümü `ceviriler` altına konur.
        $sonuc = $ciktilar[$birincil] ?? [];
        $sonuc['ceviriler'] = $ciktilar;
        $sonuc['meta'] = [
            'provider' => $this->name(),
            'source_lang' => $kaynakDil,
            'target_langs' => $diller,
            'sources' => $kaynaklar[$birincil] ?? [],
            'sources_by_lang' => $kaynaklar,
        ];

        return $sonuc;
    }

    private function onbellegeYaz(string $kaynak, string $ceviri, string $kaynakDil, string $hedefDil): void
    {
        try {
            $this->cache->store(
                TranslationCacheRepository::hash($kaynak, $kaynakDil, $hedefDil),
                $kaynak,
                $ceviri,
                $this->name(),
                $kaynakDil,
                $hedefDil,
                $this->clock->now(),
            );
        } catch (Throwable $hata) {
            // Önbellek yazımı BAŞARISIZ olsa da çeviri geçerlidir; yalnız bir
            // sonraki sefer yeniden sorulur. Sessizce yutmuyoruz — loga düşer.
            $this->logger->warning('Çeviri önbelleğe yazılamadı', ['hata' => $hata->getMessage()]);
        }
    }
}
