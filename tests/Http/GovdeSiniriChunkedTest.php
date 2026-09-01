<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\JsonRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * SERTLEŞTİRME v1.2.1 BLOK D7 — BİLİNMEYEN BOYUTLU GÖVDE SINIRSIZ OKUNMAZ (TDR-023).
 *
 * KUSUR: sınır iki kaynağa bakıyordu — `Content-Length` başlığı ve akışın
 * bildirdiği boyut. İKİSİ DE BİLİNMİYORSA istek GEÇİYORDU ve yorumda gerekçe
 * yazılıydı: "chunked istek geçer; gövde ayrıştırıcısı zaten PHP'nin kendi
 * sınırlarına tabidir."
 *
 * Gerekçe sahada tutmaz: `Transfer-Encoding: chunked` gönderen bir istemci
 * boyut BİLDİRMEZ, dolayısıyla iki denetim de sessizce atlanır. PHP'nin kendi
 * sınırları (`post_max_size`) JSON gövdesi için devreye GİRMEZ — `php://input`
 * akış olarak okunur. Yani sınır, onu atlamak isteyen için hiç yoktu; yalnız
 * dürüst istemciler için vardı.
 *
 * YENİ KURAL: boyut bilinmiyorsa AYRIŞTIRMADAN ÖNCE sınırlı okuma yapılır
 * (tavan + 1 bayt). Fazlası varsa 413. Akış geri sarılabiliyorsa geri sarılır
 * ve aşağı akış hiçbir şey kaybetmez; sarılamıyorsa istek reddedilir —
 * ölçemediğimiz bir gövdeyi geçirmek, sınırı kaldırmakla aynı şeydir.
 */
final class GovdeSiniriChunkedTest extends TestCase
{
    private function kapi(int $tavanKb = 1): JsonRequest
    {
        return new JsonRequest(new ResponseFactory(), $tavanKb);
    }

    private function isleyici(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public ?string $gorulenGovde = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->gorulenGovde = (string) $request->getBody();

                return (new ResponseFactory())->createResponse(204);
            }
        };
    }

    /** Boyutunu BİLDİRMEYEN akış — chunked isteğin taklidi. */
    private function boyutsuzAkis(string $icerik): \Psr\Http\Message\StreamInterface
    {
        $temel = (new StreamFactory())->createStream($icerik);

        return new class ($temel) implements \Psr\Http\Message\StreamInterface {
            public function __construct(private readonly \Psr\Http\Message\StreamInterface $ic)
            {
            }

            public function getSize(): ?int
            {
                return null; // chunked: boyut bilinmez
            }

            public function __toString(): string
            {
                return (string) $this->ic;
            }

            public function close(): void
            {
                $this->ic->close();
            }

            public function detach()
            {
                return $this->ic->detach();
            }

            public function tell(): int
            {
                return $this->ic->tell();
            }

            public function eof(): bool
            {
                return $this->ic->eof();
            }

            public function isSeekable(): bool
            {
                return $this->ic->isSeekable();
            }

            public function seek(int $offset, int $whence = SEEK_SET): void
            {
                $this->ic->seek($offset, $whence);
            }

            public function rewind(): void
            {
                $this->ic->rewind();
            }

            public function isWritable(): bool
            {
                return $this->ic->isWritable();
            }

            public function write(string $string): int
            {
                return $this->ic->write($string);
            }

            public function isReadable(): bool
            {
                return $this->ic->isReadable();
            }

            public function read(int $length): string
            {
                return $this->ic->read($length);
            }

            public function getContents(): string
            {
                return $this->ic->getContents();
            }

            public function getMetadata(?string $key = null)
            {
                return $this->ic->getMetadata($key);
            }
        };
    }

    private function istek(string $govde, bool $boyutBildir): ServerRequestInterface
    {
        $istek = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/lists')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($boyutBildir ? (new StreamFactory())->createStream($govde) : $this->boyutsuzAkis($govde));

        return $boyutBildir ? $istek->withHeader('Content-Length', (string) strlen($govde)) : $istek;
    }

    public function testBILDIRILENBUYUKGOVDE413(): void
    {
        $yanit = $this->kapi(1)->process($this->istek(str_repeat('a', 4096), true), $this->isleyici());

        self::assertSame(413, $yanit->getStatusCode());
    }

    public function testBOYUTSUZBUYUKGOVDE413(): void
    {
        // ASIL KUSUR: boyut bildirilmediğinde istek sınırsız geçiyordu.
        $yanit = $this->kapi(1)->process($this->istek(str_repeat('a', 8192), false), $this->isleyici());

        self::assertSame(413, $yanit->getStatusCode(), 'Boyutu bilinmeyen büyük gövde GEÇMEMELİ.');
    }

    public function testBOYUTSUZKUCUKGOVDEGECER(): void
    {
        // Koruma dürüst istemciyi engellememeli.
        $isleyici = $this->isleyici();
        $yanit = $this->kapi(1)->process($this->istek('{"name":"kisa"}', false), $isleyici);

        self::assertNotSame(413, $yanit->getStatusCode());
    }

    public function testSINIRLIOKUMAGOVDEYITUKETMEZ(): void
    {
        // Sınır denetimi için okuduğumuz baytlar AŞAĞI AKIŞTAN çalınmamalı;
        // yoksa denetim, koruduğu isteği bozar (en sinsi türden regresyon).
        $isleyici = $this->isleyici();
        $this->kapi(1)->process($this->istek('{"name":"kisa"}', false), $isleyici);

        self::assertSame('{"name":"kisa"}', $isleyici->gorulenGovde, 'Gövde aşağı akışa EKSİKSİZ ulaşmalı.');
    }
}
