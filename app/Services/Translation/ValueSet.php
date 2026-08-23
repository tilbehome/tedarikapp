<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * Değer kümesi yardımcısı (İE#14 A3) — varyasyon ve öznitelik DEĞERLERİ.
 *
 * İki iş yapar:
 *  1) DEĞERİ ÇEVİRİR: A2 hattının belirlenimci katmanından geçirir (sözlük → ham).
 *  2) UZUN LİSTEYİ ÖZETLER: ilk 3 + kalanın sayısı.
 *
 * İKİ BELİRLENİMCİ KATMAN — AĞA ÇIKILMAZ (K61):
 * Bu sınıf BELGE/SAYFA ÜRETİM hattında çalışır; render sırasında dış servise
 * gitmek bir Excel'i dakikalarca bekletir, kotayı tüketir ve aynı belgenin iki
 * üretimini FARKLI metinlerle doldurur (K50 snapshot ilkesine aykırı). Bu yüzden
 * hat yalnız ANINDA ve BELİRLENİMCİ okunabilen iki kaynağı kullanır:
 *
 *   1. SÖZLÜK  — dosya tabanlı, kesin karşılık (K56 Katman 1).
 *   2. ÖNBELLEK — kuyruğun daha önce ürettiği LLM çevirisi (İE#21 B9/B12).
 *      Okuma tek satırlık bir SELECT'tir; ağ yoktur, süre öngörülebilirdir.
 *
 * İE#21 B9 — SESSİZ MELEZ YASAK: eskiden ikisi de bulamayınca HAM Çince değer
 * basılıyordu ve sayfa "yarı Türkçe yarı Çince" çıkıyordu (canlı kanıt:
 * TDK-2026-0001'de Renk/Marka/varyasyonlar ham Çince). Kullanıcı bunu "çeviri
 * bozuk" diye okur ve haklıdır. Artık çevrilemeyen değer İŞARETLENİR: metin
 * korunur (veri asla kaybolmaz) ama `bekliyorMu()` true döner ve sunum katmanı
 * "çeviri bekliyor" rozetini basar. İş kuyruğa alınmıştır; sayfa yenilendiğinde
 * yerini gerçek çeviri alır.
 */
final class ValueSet
{
    /** Detay panelinde katlanmadan gösterilecek varyasyon sayısı. */
    public const LIMIT = 3;

    /** Tek varyasyonlu üründe satır hücresine yazılabilecek en uzun değer (İE#17 G8-b). */
    private const TEK_DEGER_SINIRI = 40;

    /**
     * HAM METİN NORMALİZASYONU (İE#17 G9) — sunum hattının TEK giriş noktası.
     *
     * Canlı belirti: varyasyonlarda "英文版&gt;1" görünüyordu. Kaynak sayfadaki
     * değer ZATEN entity içeriyor ("&gt;"); sunum bir kez daha kaçırınca entity
     * ekrana düşüyor. Çözüm: değer sunuma girmeden ÖNCE bir kez çözülür,
     * çıktıda normal `htmlspecialchars` kaçışı AYNEN uygulanır.
     *
     * GÜVENLİK: bu bir kaçış DEĞİLDİR, kaçışın YERİNE DE GEÇMEZ. "&lt;script&gt;"
     * çözülüp "<script>" olur, çıkışta yeniden kaçar ve zararsız kalır
     * (CLAUDE.md §5 — XSS regresyon testi bunu sabitler).
     *
     * Görünmez boşluklar da temizlenir: 1688 değerleri sık sık NBSP taşır.
     */
    public static function normalize(string $ham): string
    {
        $cozulmus = html_entity_decode($ham, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cozulmus = str_replace(["\u{00A0}", "\u{200B}", "\u{FEFF}"], [' ', '', ''], $cozulmus);

        return trim(preg_replace('/\s+/u', ' ', $cozulmus) ?? $cozulmus);
    }

    /** Bu üretimde çevirisi bulunamayan ham değerler — sunum bunları işaretler. */
    /** @var array<string, true> */
    private array $bekleyenler = [];

    /**
     * @param \App\Models\TranslationCacheRepository|null $cache Kuyruğun doldurduğu
     *        önbellek. null verilirse yalnız sözlük çalışır (eski davranış) —
     *        böylece önbelleği olmayan bağlamlar (testler, CLI) kırılmaz.
     * @param string $hedefDil Belgenin dili: tr · en (zh KAYNAKTIR, çevrilmez).
     * @param string $surumAnahtari CeviriSurumu::anahtar() — B12 sürümlü bellek.
     */
    public function __construct(
        private readonly ?Glossary $glossary = null,
        private readonly ?\App\Models\TranslationCacheRepository $cache = null,
        private readonly string $hedefDil = 'tr',
        private readonly string $surumAnahtari = '',
    ) {
    }

    /**
     * Aynı yapılandırmayla BAŞKA bir hedef dile geçer.
     *
     * Paylaşım sayfası tek istekte tek dil basar ama aynı süreçte üç dil de
     * istenebilir (dil seçici). Kurulum sırasında dil bilinmediği için nesne
     * burada klonlanır — her dil kendi "bekleyenler" sayacını tutar, yoksa
     * Türkçe eksikleri İngilizce sayfada da rozetlenirdi.
     */
    public function withDil(string $hedefDil): self
    {
        return new self($this->glossary, $this->cache, $hedefDil, $this->surumAnahtari);
    }

    /** Bu üretimde en az bir değer çevrilemedi mi? (kısmi çeviri göstergesi — B12) */
    public function bekleyenVar(): bool
    {
        return $this->bekleyenler !== [];
    }

    /** @return list<string> çevirisi bekleyen HAM değerler (kuyruğa verilecek küme) */
    public function bekleyenler(): array
    {
        return array_keys($this->bekleyenler);
    }

    /** Verilen ham değer bu üretimde çevrilemedi mi? */
    public function bekliyorMu(string $ham): bool
    {
        return isset($this->bekleyenler[self::normalize($ham)]);
    }

    /** Tek değer: sözlük/önbellekte varsa çevirisi, yoksa ham değer (veri ASLA kaybolmaz). */
    public function value(string $ham): string
    {
        // İE#17 G9: entity artıkları burada bir kez çözülür (tek merkez).
        $ham = self::normalize($ham);
        // İE#21 B9: kaynak ARTIK İKİ TANE. Eskiden yalnız sözlük vardı ve sözlüksüz
        // kurulumda metot hiç çalışmıyordu; önbellek dolu olsa bile okunmuyordu.
        if ($ham === '' || ($this->glossary === null && $this->cache === null)) {
            return $ham;
        }

        // "Renk: 灰色" gibi etiketli değerlerde etiket ve değer ayrı çevrilir.
        $ayrac = mb_strpos($ham, ': ');
        if ($ayrac !== false) {
            $etiket = mb_substr($ham, 0, $ayrac);
            $deger = mb_substr($ham, $ayrac + 2);

            return $this->tek($etiket) . ': ' . $this->tek($deger);
        }

        // "灰色 / L" gibi birleşik varyasyon adlarında her parça ayrı çevrilir.
        if (str_contains($ham, ' / ')) {
            $parcalar = array_map(fn (string $p): string => $this->tek($p), explode(' / ', $ham));

            return implode(' / ', $parcalar);
        }

        return $this->tek($ham);
    }

    /**
     * Tek parçanın çevirisi: SÖZLÜK → ÖNBELLEK → (bulunamadı: ham + işaret).
     *
     * Sıra bilinçlidir. Sözlük insan kararıdır ve LLM'i BAĞLAR (K70): "其他 → Diğer"
     * yazdıysak model ne derse desin "Diğer" basılır. Önbellek ikinci sıradadır
     * çünkü makine üretimidir. Üçüncü bir sıra yoktur — ağ bu hatta girmez.
     */
    private function tek(string $ham): string
    {
        $ham = self::normalize($ham);
        if ($ham === '') {
            return $ham;
        }

        // ZH KAYNAKTIR, çeviri değildir (K70): Çince görünümde değer olduğu gibi
        // doğrudur ve "çeviri bekliyor" işareti anlamsız olurdu.
        if ($this->hedefDil === 'zh') {
            return $ham;
        }

        // Sözlük TÜRKÇE karşılık tutar (sozluk-zh-tr.php). İngilizce görünümde
        // ondan okumak "Gri" basardı — bu yüzden sözlük yalnız TR'de sorulur.
        $sozlukten = $this->hedefDil === 'tr' ? $this->glossary?->lookup($ham) : null;
        if ($sozlukten !== null) {
            return $sozlukten;
        }

        // Zaten hedef dildeyse (Latin harfli, çevrilecek bir şey yok) dokunulmaz:
        // "ABS" ya da "058" için çeviri beklemek anlamsız bir uyarı üretirdi.
        if (!$this->cevrilmeliMi($ham)) {
            return $ham;
        }

        $onbellekten = $this->onbellekten($ham);
        if ($onbellekten !== null) {
            return $onbellekten;
        }

        $this->bekleyenler[$ham] = true;

        return $ham;
    }

    /**
     * Değer çeviri gerektiriyor mu?
     *
     * Yalnız CJK içeren metinler çevrilir. Sayı, ölçü, model kodu ve Latin harfli
     * değerler ("ABS", "058", "5 kg") olduğu gibi doğrudur; onları "çeviri bekliyor"
     * diye işaretlemek göstergeyi gürültüye boğar ve gerçek eksikleri gizlerdi.
     */
    private function cevrilmeliMi(string $deger): bool
    {
        return Glossary::detect($deger) === 'zh';
    }

    private function onbellekten(string $ham): ?string
    {
        if ($this->cache === null) {
            return null;
        }

        try {
            $kayit = $this->cache->find(\App\Models\TranslationCacheRepository::hash(
                $ham,
                'zh',
                $this->hedefDil,
                $this->surumAnahtari,
            ));
        } catch (\Throwable) {
            // Önbellek okunamıyorsa (tablo yok, bağlantı düştü) belge ÜRETİLMEYE
            // DEVAM EDER; değer ham kalır ve "bekliyor" işaretlenir. Bir çeviri
            // eksikliği yüzünden Excel üretimini durdurmak orantısız olurdu.
            return null;
        }

        $metin = $kayit['suggested_text'] ?? null;

        return is_string($metin) && $metin !== '' ? $metin : null;
    }

    /**
     * @param list<string> $degerler
     *
     * @return list<string>
     */
    public function values(array $degerler): array
    {
        $out = [];
        foreach ($degerler as $deger) {
            $cevrilen = $this->value($deger);
            if ($cevrilen !== '' && !in_array($cevrilen, $out, true)) {
                $out[] = $cevrilen;
            }
        }

        return $out;
    }

    /**
     * SATIR HÜCRESİ ÖZETİ (İE#17 G8-b) — kompakt rozet.
     *
     * Eski davranış "ilk 3 + … (N seçenek)" idi; 40 varyantlı üründe bu bile
     * satırda gereksiz yer kaplıyordu. Yeni kural: hücrede YALNIZ "N seçenek"
     * yazar. TEK varyasyon varsa ve kısaysa (≤ 40 karakter) değerin kendisi
     * basılır — "1 seçenek" demek, "Gri" demekten daha az bilgi verirdi.
     *
     * Tam liste detay panelindeki VARYASYONLAR bölümündedir (+N katlamasıyla).
     *
     * @param list<string> $degerler
     */
    public function ozet(array $degerler): ?string
    {
        $degerler = $this->values($degerler);
        if ($degerler === []) {
            return null;
        }
        if (count($degerler) === 1 && mb_strlen($degerler[0]) <= self::TEK_DEGER_SINIRI) {
            return $degerler[0];
        }

        return count($degerler) . ' seçenek';
    }
}
