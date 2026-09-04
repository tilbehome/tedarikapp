<?php

declare(strict_types=1);

namespace App\Core;

use Psr\Http\Message\ResponseInterface;

/**
 * docs/10 §1 yanıt zarfı üreticisi — her API yanıtı aynı biçimdedir:
 * { success, data, error, meta }
 */
final class Response
{
    /** @param array<string, mixed>|list<mixed>|null $data
     *  @param array<string, mixed> $meta */
    public static function success(
        ResponseInterface $response,
        array|null $data,
        array $meta = [],
        int $status = 200,
    ): ResponseInterface {
        return self::write($response, [
            'success' => true,
            'data' => $data,
            'error' => null,
            'meta' => (object) $meta,
        ], $status);
    }

    /** @param array<string, string> $fields
     *  @param array<string, mixed> $meta */
    public static function error(
        ResponseInterface $response,
        string $code,
        string $message,
        int $status,
        array $fields = [],
        array $meta = [],
        /**
         * v1.2.1 C4 — teknik ayrıntı yerine TUTAMAK. Kullanıcıya ham istisna
         * metni verilmez; günlükteki satırla eşleşen kısa kimlik verilir.
         * Kimlik olmadan "bir şeyler ters gitti" mesajı hiçbir işe yaramaz.
         */
        ?string $hataKimligi = null,
    ): ResponseInterface {
        $error = ['code' => $code, 'message' => $message];
        if ($fields !== []) {
            $error['fields'] = $fields;
        }
        if ($hataKimligi !== null) {
            $error['hata_kimligi'] = $hataKimligi;
        }

        return self::write($response, [
            'success' => false,
            'data' => null,
            'error' => $error,
            'meta' => (object) $meta,
        ], $status);
    }

    /** @param array<string, mixed> $envelope */
    private static function write(ResponseInterface $response, array $envelope, int $status): ResponseInterface
    {
        $json = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $response->getBody()->write($json);

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
