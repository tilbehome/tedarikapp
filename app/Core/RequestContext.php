<?php

declare(strict_types=1);

namespace App\Core;

/**
 * İstek başına bağlam taşıyıcısı (K27).
 *
 * `RequestId` middleware bunu doldurur; logger processor'ı ve `ActivityLog` okur.
 * Böylece bir kullanıcı "şu saatte hata aldım" dediğinde `X-Request-Id` değeriyle
 * hem log satırları hem de activity_log kayıtları tek sorguda bulunur.
 *
 * CLI (migrate, user-create) çağrılarında doldurulmaz; alanlar null kalır.
 */
final class RequestContext
{
    private ?string $id = null;

    private ?string $userAgent = null;

    public function fill(string $id, ?string $userAgent): void
    {
        $this->id = $id;
        // activity_log.user_agent VARCHAR(255) — sınırı aşan değer kırpılır.
        $this->userAgent = $userAgent === null || $userAgent === '' ? null : mb_substr($userAgent, 0, 255);
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }
}
