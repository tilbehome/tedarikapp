<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Models\ProductRepository;
use App\Models\TranslationCacheRepository;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * SENKRON ÇEVİRİ YÜRÜTÜCÜSÜ (D12) — "çevir" dendiğinde ÇEVİRİR.
 *
 * SAHA GERÇEĞİ (28 Ağu): "Toplu çevir" düğmesi yalnız kuyruğa yazıyordu ve
 * kuyruğu işleyen kimse yoktu — cron iki kez kurulmadı, işler 1432 dakika
 * bekledi. Kullanıcı düğmeye bastı, "kuyruğa alındı" yazısını okudu ve hiçbir
 * şey olmadı. Ürün Sahibi kararı: KULLANICI HİÇBİR CRON KURMADAN çeviri uçtan
 * uca çalışacak; kuyruk/cron kavramı kullanıcıya GÖRÜNMEYECEK.
 *
 * Bu sınıf o kararın çekirdeğidir: bir ürünü ŞİMDİ, çağıran isteğin içinde
 * çevirir. Kuyruk ortadan kalkmaz — büyük yığınlar ve fırsatçı tetikler için
 * durur — ama artık tek yol o değildir.
 *
 * NE YAZAR: yalnız çeviri ÖNBELLEĞİ (K54 — çeviri öneridir, ürün alanlarına
 * yazılmaz). Panel ve belgeler önbellekten okur; belge üretimi bu yüzden ağ
 * beklemez (K61).
 */
final class CeviriYurutucu
{
    public function __construct(
        private readonly ProductRepository $urunler,
        private readonly TranslationCacheRepository $onbellek,
        private readonly TranslatorInterface $cevirmen,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Bir ürünün EKSİK dillerini tamamlar.
     *
     * @return array{urun_id: int, kaynak_dil: string|null, eksikti: list<string>, cevrilen: list<string>, kalan: list<string>, zaten_vardi: list<string>, hata: string|null}
     */
    public function urunuTamamla(int $urunId): array
    {
        $urun = $this->urunler->find($urunId);
        if ($urun === null) {
            return $this->sonuc($urunId, null, [], [], [], [], 'Ürün bulunamadı.');
        }

        $kaynakDil = $this->kaynakDil($urun);
        $orijinal = trim((string) ($urun['name_original'] ?? ''));
        if ($orijinal === '') {
            // Orijinal metin yoksa çevrilecek bir şey de yoktur; bu bir hata
            // değildir (elle eklenmiş ürün böyledir).
            return $this->sonuc($urunId, $kaynakDil, [], [], [], [], null);
        }

        $eksikler = $this->eksikDiller($orijinal, $kaynakDil);
        if ($eksikler === []) {
            return $this->sonuc($urunId, $kaynakDil, [], [], [], KanonikDiller::uretilecekler($kaynakDil), null);
        }

        try {
            $this->cevirmen->translateProduct([
                // ÇEVRİLECEK METİN ORİJİNALDİR, EKRANDAKİ AD DEĞİL.
                //
                // Buraya `name` göndermek iki hata birden yapardı: (1) `name`
                // çoğu kayıtta zaten makine çevirisi bir Türkçe addır — Türkçeyi
                // Türkçeye çevirmek; (2) kalıcılık ölçütü `name_original`
                // üzerinden bakar, dolayısıyla üretilen satır BAŞKA bir anahtara
                // yazılır ve ürün sonsuza dek "eksik" kalırdı. Mock sağlayıcıyla
                // yapılan kanıt turunda tam olarak bu görüldü: satırlar yazıldı,
                // aday listesi hiç boşalmadı.
                'name' => $orijinal,
                'category' => null,
                'source_lang' => $kaynakDil,
                // D12: hedef diller ÜRÜNDEN gelir — ayarlardaki tek listeden değil.
                'target_langs' => $eksikler,
                'attributes' => CevrilecekDegerler::topla($urun),
            ]);
        } catch (Throwable $hata) {
            // Tek ürünün hatası turu durdurmaz: çağıran sonraki ürüne geçer ve
            // kullanıcı hangi üründe ne olduğunu raporda görür.
            $this->logger->warning('Ürün çevirisi başarısız', [
                'urun_id' => $urunId,
                'hata' => $hata->getMessage(),
            ]);

            return $this->sonuc($urunId, $kaynakDil, $eksikler, [], $eksikler, [], $hata->getMessage());
        }

        // SONUÇ ÖLÇÜLÜR, VARSAYILMAZ: çevirmen sessizce yedeğe düşmüş olabilir.
        // Önbelleğe gerçekten ne yazıldığına bakılır.
        $kalan = $this->eksikDiller($orijinal, $kaynakDil);
        $cevrilen = array_values(array_diff($eksikler, $kalan));

        // ÜÇ DURUM AYRI RAPORLANIR (D12 saha kanıtı, 28 Ağu):
        //   · eksik yoktu            → "zaten tamamdı"
        //   · eksik vardı, tamamlandı → "çevrildi: TR + EN"
        //   · eksik vardı, OLMADI     → "çevrilemedi" + sebep
        // Üçünü tek mesaja indirmek, çeviri üretilemediğinde kullanıcıya
        // "zaten tamamdı" demek demekti; ekran kanıtında tam olarak bu görüldü.
        return $this->sonuc(
            $urunId,
            $kaynakDil,
            $eksikler,
            $cevrilen,
            $kalan,
            array_values(array_diff(KanonikDiller::uretilecekler($kaynakDil), $eksikler)),
            null,
        );
    }

    /**
     * Ürünün kaynak dili — kayıtta yoksa metinden saptanır.
     *
     * Saptama SONUCU KAYDA YAZILIR: aynı soruyu her turda yeniden sormak, hem
     * boşuna iş hem de tur tur değişebilen bir cevap demektir.
     */
    /** @param array<string, mixed> $urun */
    private function kaynakDil(array $urun): ?string
    {
        $kayitli = is_string($urun['source_lang'] ?? null) ? trim((string) $urun['source_lang']) : '';
        if ($kayitli !== '') {
            return $kayitli;
        }

        $metin = trim((string) ($urun['name_original'] ?? $urun['name'] ?? ''));
        if ($metin === '') {
            return null;
        }

        return DilSaptayici::sapta($metin);
    }

    /**
     * Bu ürün için henüz KALICI çevirisi olmayan diller.
     *
     * Kalıcı = `llm:*` üretimi ya da `elle` onayı (K54). Makine çevirisi geçici
     * doldurmadır ve dili tamamlanmış saymaz — sahada "TR dolu" görünüp aslında
     * MyMemory kalıntısı olan satırlar bu yüzden yıllarca öyle kaldı.
     *
     * @return list<string>
     */
    private function eksikDiller(string $orijinal, ?string $kaynakDil): array
    {
        $eksik = [];
        foreach (KanonikDiller::uretilecekler($kaynakDil) as $dil) {
            if (!$this->onbellek->kaliciVarMi($orijinal, $dil)) {
                $eksik[] = $dil;
            }
        }

        return $eksik;
    }

    /**
     * @param list<string> $eksikti
     * @param list<string> $cevrilen
     * @param list<string> $kalan
     * @param list<string> $zatenVardi
     *
     * @return array{urun_id: int, kaynak_dil: string|null, eksikti: list<string>, cevrilen: list<string>, kalan: list<string>, zaten_vardi: list<string>, hata: string|null}
     */
    private function sonuc(
        int $urunId,
        ?string $kaynakDil,
        array $eksikti,
        array $cevrilen,
        array $kalan,
        array $zatenVardi,
        ?string $hata,
    ): array {
        return [
            'urun_id' => $urunId,
            'kaynak_dil' => $kaynakDil,
            'eksikti' => $eksikti,
            'cevrilen' => $cevrilen,
            'kalan' => $kalan,
            'zaten_vardi' => $zatenVardi,
            'hata' => $hata,
        ];
    }
}
