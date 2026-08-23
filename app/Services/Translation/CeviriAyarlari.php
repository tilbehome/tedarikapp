<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Core\Encrypter;
use App\Models\SettingsRepository;
use SensitiveParameter;
use Throwable;

/**
 * ÇEVİRİ AYARLARI (İE#20 C4) — Ayarlar > Çeviri ekranının veri katmanı.
 *
 * ANAHTAR ŞİFRELİ SAKLANIR: LLM sağlayıcı anahtarı bir sırdır ve `settings`
 * tablosuna DÜZ yazılamaz — veritabanı yedeği elden ele dolaşır, panel ekranı
 * omuz üstünden okunur. APP_KEY ile şifrelenir (2FA secret'larıyla aynı mekanizma)
 * ve panele HİÇBİR ZAMAN geri verilmez; yalnız "tanımlı mı?" ve son 4 hane döner.
 *
 * HEDEF DİLLER YAPILANDIRILABİLİR (PM notu, 22 Ağu): şema hedef dilleri SABİT
 * VARSAYMAZ. Yeni dil eklemek bir migration değil bir AYAR değişikliğidir.
 * Varsayılan `tr,en`: Ürün Sahibi kararı gereği sistem TAM ÜÇ DİLLİDİR
 * (ZH orijinal + TR + EN) ve EN üretimi VARSAYILAN AÇIKTIR.
 */
final class CeviriAyarlari
{
    public const KEY_SAGLAYICI = 'ceviri_saglayici';
    public const KEY_ANAHTAR = 'ceviri_api_anahtari';
    public const KEY_ANAHTAR_ONIZLEME = 'ceviri_api_anahtar_onizleme';
    public const KEY_MODEL = 'ceviri_model';
    public const KEY_HEDEF_DILLER = 'ceviri_hedef_diller';
    public const KEY_ACIK = 'ceviri_llm_acik';

    /** Ürün Sahibi kararı: TR + EN birlikte, tek istekte. */
    public const VARSAYILAN_HEDEF_DILLER = 'tr,en';

    /** Orijinal (kaynak) dil dahil panelde gösterilebilecek diller. */
    public const KAYNAK_DILLER = ['zh', 'en'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly Encrypter $encrypter,
    ) {
    }

    /**
     * Yapılandırılmamış sistemin sağlayıcısı (İE#20 D1).
     *
     * VARSAYILAN DEEPSEEK'TİR. Gerekçe: bu iş TİCARİ KATALOG ÇEVİRİSİDİR — yüksek
     * hacimli, düşük yaratıcılık gerektiren, maliyete duyarlı bir yük. DeepSeek bu
     * profilde belirgin biçimde ucuzdur ve varsayılanın kullanıcının cebini
     * koruması gerekir; pahalı bir varsayılan, "denedim, kotayı yaktı" deneyimidir.
     * Kullanıcı Ayarlar > Çeviri'den istediği sağlayıcıya geçebilir.
     */
    public function saglayici(): string
    {
        $deger = trim((string) ($this->settings->get(self::KEY_SAGLAYICI) ?? ''));

        return $deger === '' ? LlmTranslator::SAGLAYICI_DEEPSEEK : $deger;
    }

    /** İstekte KULLANILAN model: ayar boşsa sağlayıcının varsayılanı. */
    public function model(): string
    {
        $ham = $this->modelHam();

        return $ham === '' ? LlmTranslator::varsayilanModel($this->saglayici()) : $ham;
    }

    /**
     * Ayarda YAZAN değer (boş olabilir) — panel "boş mu, yazılmış mı" ayrımını
     * bilmeden gri yer tutucu gösteremez. `model()` etkin değeri, bu ham değeri verir.
     */
    public function modelHam(): string
    {
        return trim((string) ($this->settings->get(self::KEY_MODEL) ?? ''));
    }

    /**
     * Hedef diller — SABİT DEĞİL, ayardan gelir.
     *
     * @return list<string>
     */
    public function hedefDiller(): array
    {
        $ham = trim((string) ($this->settings->get(self::KEY_HEDEF_DILLER) ?? ''));
        if ($ham === '') {
            $ham = self::VARSAYILAN_HEDEF_DILLER;
        }

        $diller = [];
        foreach (explode(',', $ham) as $dil) {
            $dil = strtolower(trim($dil));
            // İki-üç harfli ISO kodu; uydurma değer ayarı bozmasın.
            if (preg_match('/^[a-z]{2,3}$/', $dil) === 1 && !in_array($dil, $diller, true)) {
                $diller[] = $dil;
            }
        }

        return $diller === [] ? ['tr'] : $diller;
    }

    public function acikMi(): bool
    {
        // Varsayılan AÇIK: anahtar tanımlıysa çeviri çalışır. Anahtar yoksa
        // zaten LLM katmanı devreye girmez (LayeredTranslator yedeğe düşer).
        return (string) ($this->settings->get(self::KEY_ACIK) ?? '1') !== '0';
    }

    /** Anahtar tanımlı mı? (değeri DÖNMEZ) */
    public function anahtarVarMi(): bool
    {
        return $this->anahtar() !== null;
    }

    /** Panelde gösterilecek maskeli önizleme: "sk-…4f2a". */
    public function anahtarOnizleme(): ?string
    {
        $deger = $this->settings->get(self::KEY_ANAHTAR_ONIZLEME);

        return is_string($deger) && $deger !== '' ? $deger : null;
    }

    /** Çözülmüş anahtar — YALNIZ istek anında kullanılır, hiçbir yere yazılmaz. */
    public function anahtar(): ?string
    {
        $sifreli = $this->settings->get(self::KEY_ANAHTAR);
        if (!is_string($sifreli) || $sifreli === '') {
            return null;
        }

        try {
            $cozulmus = $this->encrypter->decrypt($sifreli);
        } catch (Throwable) {
            // APP_KEY değişmişse eski anahtar çözülemez. Bu bir arıza değil,
            // beklenen sonuçtur (docs/config-referansi.md); çeviri yedeğe düşer.
            return null;
        }

        return $cozulmus === '' ? null : $cozulmus;
    }

    public function anahtariKaydet(#[SensitiveParameter] string $anahtar): void
    {
        $anahtar = trim($anahtar);
        if ($anahtar === '') {
            $this->settings->set(self::KEY_ANAHTAR, '');
            $this->settings->set(self::KEY_ANAHTAR_ONIZLEME, '');

            return;
        }

        $this->settings->set(self::KEY_ANAHTAR, $this->encrypter->encrypt($anahtar));
        $this->settings->set(
            self::KEY_ANAHTAR_ONIZLEME,
            mb_substr($anahtar, 0, 3) . '…' . mb_substr($anahtar, -4),
        );
    }

    public function saglayiciKaydet(string $saglayici): void
    {
        $this->settings->set(self::KEY_SAGLAYICI, $saglayici);
    }

    public function modelKaydet(string $model): void
    {
        $this->settings->set(self::KEY_MODEL, trim($model));
    }

    /** @param list<string> $diller */
    public function hedefDilleriKaydet(array $diller): void
    {
        $temiz = [];
        foreach ($diller as $dil) {
            $dil = strtolower(trim($dil));
            if (preg_match('/^[a-z]{2,3}$/', $dil) === 1 && !in_array($dil, $temiz, true)) {
                $temiz[] = $dil;
            }
        }
        $this->settings->set(self::KEY_HEDEF_DILLER, implode(',', $temiz === [] ? ['tr'] : $temiz));
    }

    public function acikKaydet(bool $acik): void
    {
        $this->settings->set(self::KEY_ACIK, $acik ? '1' : '0');
    }

    /**
     * Panele dönen özet — SIR İÇERMEZ.
     *
     * @return array{saglayici: string, model: string, model_ham: string, varsayilan_model: string, hedef_diller: list<string>, acik: bool, anahtar_tanimli: bool, anahtar_onizleme: string|null, saglayicilar: list<string>}
     */
    public function ozet(): array
    {
        return [
            'saglayici' => $this->saglayici(),
            // `model` ETKİN değerdir (istekte bu kullanılır); `model_ham` ayarda
            // yazandır (boş olabilir) ve panel yer tutucusunu buna göre gösterir.
            'model' => $this->model(),
            'model_ham' => $this->modelHam(),
            'varsayilan_model' => LlmTranslator::varsayilanModel($this->saglayici()),
            'hedef_diller' => $this->hedefDiller(),
            'acik' => $this->acikMi(),
            'anahtar_tanimli' => $this->anahtarVarMi(),
            'anahtar_onizleme' => $this->anahtarOnizleme(),
            'saglayicilar' => LlmTranslator::SAGLAYICILAR,
        ];
    }
}
