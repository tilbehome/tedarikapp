<?php

declare(strict_types=1);

namespace App\Services;

/**
 * SSRF kapısı (docs/04 §2d — derinleştirilmiş medya güvenliği).
 *
 * Sunucu, dışarıdan gelen bir URL'yi KENDİSİ çektiği için bu akış klasik SSRF yüzeyidir:
 * saldırgan `http://127.0.0.1/admin` veya bir bulut metadata adresi verirse sunucu onu
 * kendi ağından ister. Kurallar sırayla uygulanır; biri düşerse indirme yapılmaz.
 *
 * Kurallar tek yerdedir çünkü yönlendirme (redirect) hedefi de AYNI denetimden geçmek
 * zorundadır — cURL'ün kör redirect takibi bu yüzden kullanılmaz.
 */
final class UrlGuard
{
    /**
     * @param list<string> $allowedHosts Örn. ['alicdn.com', '1688.com']
     */
    public function __construct(private readonly array $allowedHosts)
    {
    }

    /** @throws MediaException */
    public function assertAllowed(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new MediaDeniedException('Geçersiz adres.');
        }

        // docs/04 §2d: yalnız HTTPS. Düz HTTP, araya girenin görseli değiştirmesine açıktır.
        if (strtolower($parts['scheme']) !== 'https') {
            throw new MediaDeniedException('Yalnızca https adreslerinden indirme yapılır.');
        }

        $host = strtolower($parts['host']);
        if (!$this->hostAllowed($host)) {
            throw new MediaDeniedException(sprintf('Bu alan adından indirme yapılmaz: %s', $host));
        }

        $this->assertPublicAddress($host);
    }

    /**
     * Tam sonek eşleşmesi: `alicdn.com` izinliyse `cbu01.alicdn.com` GEÇER,
     * `alicdn.com.evil.com` GEÇMEZ. Düz `str_contains` denetimi bu tuzağa düşer.
     */
    public function hostAllowed(string $host): bool
    {
        foreach ($this->allowedHosts as $allowed) {
            $allowed = strtolower(trim($allowed));
            if ($allowed === '') {
                continue;
            }
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Alan adının çözüldüğü IP'ler herkese açık aralıkta mı?
     *
     * Saldırgan kendi alan adını 127.0.0.1'e veya 169.254.169.254'e (bulut metadata)
     * yönlendirebilir; ad izinli listede olsa bile hedef iç ağ olabilir.
     *
     * @throws MediaException
     */
    public function assertPublicAddress(string $host): void
    {
        foreach ($this->resolve($host) as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new MediaDeniedException('Adres iç ağa işaret ediyor, indirme reddedildi.');
            }
        }
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        // Doğrudan IP verilmişse çözümlemeye gerek yok.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false || $records === []) {
            throw new MediaException('Alan adı çözümlenemedi.');
        }

        $addresses = [];
        foreach ($records as $record) {
            if (isset($record['ip']) && is_string($record['ip'])) {
                $addresses[] = $record['ip'];
            }
            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        if ($addresses === []) {
            throw new MediaException('Alan adı çözümlenemedi.');
        }

        return $addresses;
    }
}
