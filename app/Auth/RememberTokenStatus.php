<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * "Beni hatırla" çerezinin doğrulama sonucu.
 */
enum RememberTokenStatus
{
    /** Çerez yok veya biçimi bozuk. */
    case Absent;

    /** Selector veritabanında bulunamadı (token iptal edilmiş ya da uydurma). */
    case Unknown;

    /** Token bulundu ama süresi dolmuş. */
    case Expired;

    /**
     * Selector doğru, validator yanlış: çerez çalınmış ve kopyalanmış demektir.
     * Bu durumda kullanıcının TÜM token'ları iptal edilir (İE#4 §3).
     */
    case Stolen;

    case Valid;
}
