<?php

declare(strict_types=1);

namespace App\Services\Kuyruk;

use DateTimeImmutable;

/**
 * İŞ BAĞLAMI — işleyicinin kirasını uzatmasını ve kaybı GÖRMESİNİ sağlar (A2).
 *
 * ÖNCESİ: `JobRunner::kalpAtisi()` vardı ama işleyiciye ne koşucu ne kira
 * token'ı geçiliyordu. Yani hiçbir işleyici onu ÇAĞIRAMIYORDU — ölü bir API.
 * Sonuç: 10 görselli bir ürünün medya işi (her görsel 25 sn zaman aşımı =
 * 250 sn) 300 saniyelik kirayı aşabiliyor, iş devralınıyor ve İKİ işleyici
 * aynı görselleri indirmeye başlıyordu.
 *
 * DAHA KÖTÜSÜ: kira kaybedildikten sonra işleyici çalışmaya DEVAM ediyordu.
 * Dosya indiriyor, diske yazıyor, dış servise vuruyordu — hepsi artık başka
 * bir işleyicinin sahiplendiği iş için. YAN ETKİ, SAHİPLİK KAYBINDAN SONRA
 * SÜRMEZ: `kontrolNoktasi()` kaybı görür görmez `KiraKaybedildi` atar ve
 * işleyicinin döngüsü orada durur.
 *
 * KULLANIM: her dış ağ ve dosya adımının ÖNCESİNDE ve SONRASINDA çağrılır.
 * Öncesinde çağrılır ki boşuna indirme yapılmasın; sonrasında çağrılır ki
 * uzun süren adımın ardından kira tazelensin.
 */
final class IsBaglami
{
    public function __construct(
        private readonly JobQueue $kuyruk,
        public readonly int $isId,
        private readonly string $token,
    ) {
    }

    /**
     * KONTROL NOKTASI: kirayı uzat, uzatılamıyorsa DUR.
     *
     * Dönüş değeri yok ve olmamalı: `bool` dönseydi çağıran onu kontrol etmeyi
     * unutabilirdi ve yan etki sessizce sürerdi. İstisna unutulamaz.
     *
     * @throws KiraKaybedildi kira artık bu işleyicinin değilse
     */
    public function kontrolNoktasi(DateTimeImmutable $now): void
    {
        if (!$this->kuyruk->kalpAtisi($this->isId, $this->token, $now)) {
            throw new KiraKaybedildi($this->isId, 'kalp_atisi');
        }
    }

    /**
     * Kira hâlâ bizde mi? — istisnasız sorgu.
     *
     * `kontrolNoktasi()` tercih edilir; bu yalnız akışını kendi yöneten
     * (örneğin kısmi sonucu kaydedip düzgün çıkmak isteyen) işleyiciler
     * içindir.
     */
    public function kiraBizdeMi(DateTimeImmutable $now): bool
    {
        return $this->kuyruk->kalpAtisi($this->isId, $this->token, $now);
    }
}
