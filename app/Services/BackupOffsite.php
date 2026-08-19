<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Off-site yedek gönderimi (İE#10.5 Blok 1c) — K8: dış istek YALNIZ cURL.
 *
 * İki hedef desteklenir; ikisi de YAPILANDIRILMIŞSA önce FTP denenir (dosya bütünlüğü
 * e-postadan sağlamdır), o düşerse SMTP. Kimlik bilgileri SIR'dır ve yalnız dosya
 * yapılandırmasından okunur (config.php/.env — CLAUDE.md §5; DB'ye yazılmaz):
 *   FTP : BACKUP_FTP_URL (örn. ftps://sunucu/yol/), BACKUP_FTP_USER, BACKUP_FTP_PASS
 *   SMTP: BACKUP_SMTP_URL (örn. smtps://smtp.gmail.com:465), BACKUP_SMTP_USER,
 *         BACKUP_SMTP_PASS, BACKUP_SMTP_TO
 * Hedef yapılandırılmamışsa gönderim atlanır ve panel "yedek indirme hatırlatması"
 * modelinde çalışır (son yedek 24 saatten eskiyse rozet).
 */
final class BackupOffsite
{
    public function __construct(private readonly Config $config)
    {
    }

    public function configured(): bool
    {
        return $this->ftpConfigured() || $this->smtpConfigured();
    }

    /** @return array{attempted: bool, sent: bool, via: ?string, error: ?string} */
    public function send(string $filePath, string $fileName): array
    {
        if (!$this->configured()) {
            return ['attempted' => false, 'sent' => false, 'via' => null, 'error' => null];
        }

        $errors = [];
        if ($this->ftpConfigured()) {
            $error = $this->sendFtp($filePath, $fileName);
            if ($error === null) {
                return ['attempted' => true, 'sent' => true, 'via' => 'ftp', 'error' => null];
            }
            $errors[] = 'FTP: ' . $error;
        }
        if ($this->smtpConfigured()) {
            $error = $this->sendSmtp($filePath, $fileName);
            if ($error === null) {
                return ['attempted' => true, 'sent' => true, 'via' => 'smtp', 'error' => null];
            }
            $errors[] = 'SMTP: ' . $error;
        }

        return ['attempted' => true, 'sent' => false, 'via' => null, 'error' => implode(' · ', $errors)];
    }

    private function ftpConfigured(): bool
    {
        return $this->config->get('BACKUP_FTP_URL', '') !== '';
    }

    private function smtpConfigured(): bool
    {
        return $this->config->get('BACKUP_SMTP_URL', '') !== ''
            && $this->config->get('BACKUP_SMTP_TO', '') !== '';
    }

    /** @return string|null hata (başarıda null) */
    private function sendFtp(string $filePath, string $fileName): ?string
    {
        $stream = fopen($filePath, 'r');
        if ($stream === false) {
            return 'yedek dosyası okunamadı';
        }

        $url = rtrim($this->config->get('BACKUP_FTP_URL', ''), '/') . '/' . $fileName;
        $handle = curl_init($url);
        if ($handle === false) {
            fclose($stream);

            return 'cURL başlatılamadı';
        }
        curl_setopt_array($handle, [
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => $stream,
            CURLOPT_INFILESIZE => (int) filesize($filePath),
            CURLOPT_USERPWD => $this->config->get('BACKUP_FTP_USER', '') . ':' . $this->config->get('BACKUP_FTP_PASS', ''),
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FTP_CREATE_MISSING_DIRS => true,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $ok = curl_exec($handle);
        $error = curl_error($handle);
        curl_close($handle);
        fclose($stream);

        // Hata metni kimlik İÇERMEZ (cURL mesajları bağlantı düzeyindedir); şifre asla eklenmez.
        return $ok === false ? ($error !== '' ? $error : 'bilinmeyen cURL hatası') : null;
    }

    /** @return string|null hata (başarıda null) */
    private function sendSmtp(string $filePath, string $fileName): ?string
    {
        $to = $this->config->get('BACKUP_SMTP_TO', '');
        $from = $this->config->get('BACKUP_SMTP_USER', '');
        $boundary = 'tdk-' . bin2hex(random_bytes(8));
        $content = base64_encode((string) file_get_contents($filePath));

        $message = implode("\r\n", [
            'From: tedarikapp <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: tedarikapp yedek: ' . $fileName,
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
            '',
            '--' . $boundary,
            'Content-Type: text/plain; charset=utf-8',
            '',
            'Şifreli veritabanı yedeği ektedir. Çözme, uygulamanın APP_KEY değerini gerektirir.',
            '',
            '--' . $boundary,
            'Content-Type: application/octet-stream; name="' . $fileName . '"',
            'Content-Disposition: attachment; filename="' . $fileName . '"',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split($content, 76, "\r\n") . '--' . $boundary . '--',
            '',
        ]);

        $handle = curl_init($this->config->get('BACKUP_SMTP_URL', ''));
        if ($handle === false) {
            return 'cURL başlatılamadı';
        }
        $payload = fopen('php://temp', 'r+');
        if ($payload === false) {
            curl_close($handle);

            return 'ileti akışı açılamadı';
        }
        fwrite($payload, $message);
        rewind($payload);

        curl_setopt_array($handle, [
            CURLOPT_MAIL_FROM => '<' . $from . '>',
            CURLOPT_MAIL_RCPT => ['<' . $to . '>'],
            CURLOPT_USERNAME => $from,
            CURLOPT_PASSWORD => $this->config->get('BACKUP_SMTP_PASS', ''),
            CURLOPT_USE_SSL => CURLUSESSL_ALL,
            CURLOPT_UPLOAD => true,
            CURLOPT_READDATA => $payload,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $ok = curl_exec($handle);
        $error = curl_error($handle);
        curl_close($handle);
        fclose($payload);

        return $ok === false ? ($error !== '' ? $error : 'bilinmeyen cURL hatası') : null;
    }
}
