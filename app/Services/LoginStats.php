<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Connection;
use App\Models\SettingsRepository;

/**
 * Giriş ekranının vitrin rakamları (İE#13 EK-B).
 *
 * NEDEN SUNUCU TARAFI: bu değerler için GİRİŞSİZ BİR UÇ AÇILMAZ (PM şartı) — panel
 * index.html'ine render anında meta etiketi olarak gömülür. Böylece kimliksiz bir
 * istemci "kaç ürün var" diye API'ye soramaz.
 *
 * GİZLİLİK: değerler YUVARLANARAK gömülür ("248", "₺2,1M"); kesin ciro dışarı
 * sızmaz. Zaten dış dünyaya değil, giriş ekranını gören kişiye gösterilir.
 *
 * PARA: toplam bcmath ile hesaplanır (K14) — SUM(qty * price_yuan) DECIMAL olarak
 * SQL'de toplanır, kur çarpımı string aritmetiğiyle yapılır; float'a düşülmez.
 */
final class LoginStats
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{products: string, volume: string}
     */
    public function summary(): array
    {
        try {
            $pdo = $this->connection->pdo();

            $count = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE deleted_at IS NULL')->fetchColumn();

            $sum = $pdo->query('SELECT COALESCE(SUM(qty * price_yuan), 0) FROM products WHERE deleted_at IS NULL')
                ->fetchColumn();
            // Sürücüye göre string ya da sayı döner; false/null ise ürün yok demektir.
            $yuanToplam = is_scalar($sum) ? (string) $sum : '0';

            $rate = (new SettingsRepository($this->connection))->get(SettingsRepository::KEY_YUAN_RATE, '0') ?? '0';
            $tl = preg_match('/^\d+(\.\d+)?$/', $rate) === 1 && preg_match('/^\d+(\.\d+)?$/', $yuanToplam) === 1
                ? bcmul($yuanToplam, $rate, 2)
                : '0';

            return ['products' => self::sayi($count), 'volume' => '₺' . self::kisa($tl)];
        } catch (\Throwable) {
            // Veritabanı yoksa/bozuksa giriş ekranı YİNE AÇILMALI — rakamlar düşer, form kalır.
            return ['products' => '—', 'volume' => '—'];
        }
    }

    /** 1.248 gibi binlik ayraçlı tam sayı (TR biçimi). */
    private static function sayi(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    /**
     * Büyük tutarı okunur kısaltmaya çevirir: 2.145.300,00 → "2,1M" · 248.000 → "248B".
     * Yalnız GÖSTERİM içindir; hesaplarda kullanılmaz (K14).
     */
    private static function kisa(string $tutar): string
    {
        $tamKisim = explode('.', $tutar)[0];
        $basamak = strlen(ltrim($tamKisim, '0')) ?: 1;

        if ($basamak >= 7) {
            $milyon = bcdiv($tamKisim, '1000000', 1);

            return str_replace('.', ',', $milyon) . 'M';
        }
        if ($basamak >= 6) {
            return self::binlik(bcdiv($tamKisim, '1000', 0)) . 'B';
        }

        return self::binlik($tamKisim);
    }

    /** Basamakları float'a düşmeden noktayla gruplar: "1250000" → "1.250.000". */
    private static function binlik(string $rakamlar): string
    {
        $rakamlar = ltrim($rakamlar, '0');
        if ($rakamlar === '') {
            return '0';
        }

        return strrev(implode('.', str_split(strrev($rakamlar), 3)));
    }

    /**
     * Sistemde iki adımlı doğrulama kurulu mu? (İE#13 EK-B — güvenlik kartı yalnız
     * AÇIKSA görünür; K45 ile 2FA opsiyoneldir.) Kullanıcı adı/e-posta sızmaz.
     */
    public function twoFactorEnabled(): bool
    {
        try {
            $statement = $this->connection->pdo()->query('SELECT COUNT(*) FROM users WHERE totp_secret IS NOT NULL');

            return $statement !== false && (int) $statement->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
