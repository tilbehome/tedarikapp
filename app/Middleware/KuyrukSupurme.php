<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Kuyruk\KuyrukTetikleyici;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PANEL ZİYARETİ = KUYRUK TURU (D12 madde 3).
 *
 * Ürün Sahibi kararı: kullanıcı hiçbir cron kurmadan sistem uçtan uca
 * çalışacak. Cron'un yerini tek bir şey tutamaz; bu ara katman EMNİYETTİR:
 * oturumlu her panel isteğinde arkada bir tur denenir. Kullanıcı düğmeye
 * basmasa bile, panele girdiği her seferde çevrilmemişler biraz daha erir.
 *
 * KULLANICI BEKLETİLMEZ: tur, yanıt gönderildikten sonra (kapanış kancasında)
 * koşar. Üst üste binmesin diye tetikleyicinin soğuma penceresi vardır; gerçek
 * çakışma koruması ise her zaman kuyruğun kira token'ıdır (B11) — iki tur aynı
 * anda koşsa bile bir iş iki kez işlenmez.
 *
 * SESSİZ BAŞARISIZLIK KABULDÜR: tur açılamazsa istek yine de normal yanıtını
 * verir. Bu ara katmanın görevi kullanıcının işini yapmak değil, arkada iş
 * biriktirmemektir.
 */
final class KuyrukSupurme implements MiddlewareInterface
{
    public function __construct(private readonly KuyrukTetikleyici $tetikleyici)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Yalnız okuma isteklerinde tetiklenir: yazma isteklerinin kendi
        // tetikleyicileri vardır (yakalama, toplu çeviri) ve bir POST'un
        // ardından ikinci bir tur açmak gereksiz gürültüdür.
        if (strtoupper($request->getMethod()) === 'GET') {
            $this->tetikleyici->yanittanSonraDene();
        }

        return $response;
    }
}
