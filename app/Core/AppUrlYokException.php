<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * APP_URL yapılandırılmamışken dış bağlantı istendi (rc8-04 / dış denetim F-08).
 *
 * Üretimde istemcinin `Host` başlığına düşmek, saldırganın belirlediği bir adresi
 * QR'a ve firmaya giden belgeye basmak demektir. Sessiz bir yedek yerine AÇIK bir
 * hata veriyoruz: kullanıcı ayarı girer, biz yanlış link üretmeyiz.
 */
final class AppUrlYokException extends RuntimeException
{
}
