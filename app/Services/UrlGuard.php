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
     * @param list<string>                $allowedHosts Örn. ['alicdn.com', '1688.com']
     * @param (callable(string): list<string>)|null $cozumleyici v1.2.1 D3 —
     *        DNS çözümleyici. Testlerde karışık A/AAAA ve rebinding senaryoları
     *        gerçek DNS beklemeden kurulabilsin diye enjekte edilebilir;
     *        üretimde `dns_get_record` kullanılır.
     */
    public function __construct(
        private readonly array $allowedHosts,
        private $cozumleyici = null,
    ) {
    }

    /**
     * ADRESİ ÇÖZ — HOP BAŞINA BİR KEZ (v1.2.1 D3 · TDR-013).
     *
     * Sonuç ÇAĞIRANA DÖNER ve cURL'e pinlenir. Eskiden kapı çözüyor, cURL AYNI
     * adı kendi başına YENİDEN çözüyordu; iki çözümleme arasındaki saniyelerde
     * saldırganın DNS'i farklı cevap verebilir (DNS rebinding). Denetim geçilir,
     * istek iç ağa gider.
     *
     * @return list<string>
     *
     * @throws MediaException
     */
    public function cozumle(string $host): array
    {
        return $this->resolve($host);
    }

    /**
     * Çözülen adreslerin HEPSİ herkese açık mı? Değilse istisna; evetse liste.
     *
     * KARIŞIK A/AAAA TAMAMEN REDDEDİLİR: bir ad hem açık hem özel adrese
     * çözülüyorsa "açık olanı kullan" demek, sıralamayı saldırgana seçtirmektir.
     *
     * @return list<string>
     *
     * @throws MediaException
     */
    public function guvenliAdresler(string $host): array
    {
        $adresler = $this->resolve($host);
        foreach ($adresler as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new MediaDeniedException('Adres iç ağa işaret ediyor, indirme reddedildi.');
            }
        }

        return $adresler;
    }

    /**
     * `CURLOPT_RESOLVE` girdisi: `host:port:ip[,ip...]`.
     *
     * BİÇİM ÖNEMLİ: yanlış biçim cURL tarafından SESSİZCE yok sayılır ve pin
     * hiç uygulanmaz — mekanizma çalışıyor sanılır. Bu yüzden biçim testlidir.
     *
     * Birden çok adres pinlenir: tek IP'ye pinlemek, o adres geçici olarak
     * düşünce indirmeyi imkânsız kılardı; hepsi zaten doğrulanmıştır.
     *
     * @param  list<string> $adresler
     * @return list<string>
     */
    public function pinSecenekleri(string $url, array $adresler): array
    {
        $parts = parse_url($url);
        $host = is_array($parts) && isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        if ($host === '' || $adresler === []) {
            return [];
        }

        $port = is_array($parts) && isset($parts['port']) ? (int) $parts['port'] : 443;

        return [$host . ':' . $port . ':' . implode(',', $adresler)];
    }

    /**
     * Gerçekten bağlanılan adres, onayladığımız listede mi? (SON SAVUNMA)
     *
     * Pin bir şekilde uygulanmadıysa (eski cURL sürümü, araya giren proxy)
     * istek yine de kesilmelidir. BOŞ değer GÜVENSİZ sayılır: cURL adresi
     * bildiremiyorsa "herhalde doğrudur" demeyiz — doğrulanamayan bağlantı,
     * doğrulanmamış bağlantıdır.
     *
     * @param list<string> $onaylanan
     */
    public function baglantiDogru(string $baglanilanIp, array $onaylanan): bool
    {
        $baglanilanIp = trim($baglanilanIp);
        if ($baglanilanIp === '') {
            return false;
        }

        return in_array($baglanilanIp, $onaylanan, true);
    }

    /**
     * Adresi denetler ve GÜVENLİ ÇÖZÜM SONUCUNU döndürür (v1.2.1 D3).
     *
     * Dönüş değeri eklendi: çağıran bu adresleri `CURLOPT_RESOLVE` ile pinler.
     * Eskiden metot `void` idi, cürül adı yeniden çözüyordu ve iki çözümleme
     * arasındaki fark SSRF'e kapı bırakıyordu.
     *
     * @return list<string> doğrulanmış IP adresleri
     *
     * @throws MediaException
     */
    public function assertAllowed(string $url): array
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

        // v1.2.1 D3: adresler BURADA çözülür ve ÇAĞIRANA DÖNER; indirici onları
        // cURL'e pinler. Tek çözümleme = DNS rebinding penceresi yok.
        return $this->guvenliAdresler($host);
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

        if ($this->cozumleyici !== null) {
            return ($this->cozumleyici)($host);
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
