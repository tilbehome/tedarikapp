<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * V3-C BLOK A1 — FİRMA, TUR VE RFQ OMURGASI (yalnız DDL).
 *
 * MODELİN BİRİMİ `liste_id + firma_id + tur_no` ÜÇLÜSÜDÜR (#15 §1). Bugün
 * paylaşım listeye bağlı tek bir kolonlar kümesiydi; bir listenin iki firmaya
 * ayrı ayrı sorulması mümkün değildi. Tur kavramı bunu açar: aynı liste üç
 * firmaya gidebilir, her biri kendi turunu yürütür ve turlar birbirini EZMEZ.
 *
 * FİRMA HESABI YOKTUR (K15/K62): dış aktör link + 6 haneli anahtarla kısa
 * ömürlü bir oturum alır ve yalnız kendi `supplier_round_id`si üzerinde yazar.
 * `suppliers` bir KAYITTIR, bir hesap değil.
 *
 * K23: bu dosya YALNIZ DDL yapar. Paylaşım göçü ve doldurma AYRI
 * migration'dadır (0037) — MySQL'de DDL örtük commit yapar; tek dosyada hem
 * tablo açıp hem veri taşımak, yarıda kalırsa geri alınamayan bir hâl bırakır.
 *
 * K50 SINIRI: bu tabloların HİÇBİRİ belge üretimine bağlanmaz. Çıktının kuru
 * her zaman `lists.yuan_rate` kopyasından okunur; tur snapshot'ı sonradan
 * değişse bile geçmiş belge aynen yeniden üretilir.
 */
return new class () implements Migration {
    public function up(PDO $pdo): void
    {
        $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        $this->suppliers($pdo, $sqlite);
        $this->supplierContacts($pdo, $sqlite);
        $this->rfqSnapshots($pdo, $sqlite);
        $this->supplierRounds($pdo, $sqlite);
        $this->quoteResponses($pdo, $sqlite);
    }

    private function suppliers(PDO $pdo, bool $sqlite): void
    {
        if ($this->tabloVar($pdo, 'suppliers')) {
            return;
        }

        // `varsayilan_*` alanları tur açarken forma ÖN DOLDURULUR: aynı firmaya
        // her seferinde aynı geçerlilik süresini elle yazmak, unutulduğunda
        // sessizce yanlış bir süre demekti.
        $pdo->exec($sqlite
            ? 'CREATE TABLE suppliers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ad TEXT NOT NULL,
                tip TEXT NOT NULL DEFAULT "uretici",
                ulke TEXT NULL,
                platform TEXT NULL,
                varsayilan_dil TEXT NOT NULL DEFAULT "zh",
                varsayilan_gecerlilik_gun INTEGER NULL,
                whatsapp TEXT NULL,
                eposta TEXT NULL,
                notlar TEXT NULL,
                arsivlendi_at TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
            : 'CREATE TABLE suppliers (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                ad VARCHAR(190) NOT NULL,
                tip VARCHAR(16) NOT NULL DEFAULT "uretici",
                ulke VARCHAR(64) NULL,
                platform VARCHAR(30) NULL,
                varsayilan_dil VARCHAR(8) NOT NULL DEFAULT "zh",
                varsayilan_gecerlilik_gun INT UNSIGNED NULL,
                whatsapp VARCHAR(32) NULL,
                eposta VARCHAR(190) NULL,
                notlar TEXT NULL,
                arsivlendi_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY ix_firma_ad (ad),
                KEY ix_firma_arsiv (arsivlendi_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', );
    }

    private function supplierContacts(PDO $pdo, bool $sqlite): void
    {
        if ($this->tabloVar($pdo, 'supplier_contacts')) {
            return;
        }

        $pdo->exec($sqlite
            ? 'CREATE TABLE supplier_contacts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                supplier_id INTEGER NOT NULL,
                ad TEXT NOT NULL,
                gorev TEXT NULL,
                whatsapp TEXT NULL,
                eposta TEXT NULL,
                birincil INTEGER NOT NULL DEFAULT 0,
                notlar TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
            : 'CREATE TABLE supplier_contacts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                supplier_id BIGINT UNSIGNED NOT NULL,
                ad VARCHAR(190) NOT NULL,
                gorev VARCHAR(120) NULL,
                whatsapp VARCHAR(32) NULL,
                eposta VARCHAR(190) NULL,
                birincil TINYINT(1) NOT NULL DEFAULT 0,
                notlar TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY ix_kisi_firma (supplier_id, birincil)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', );
    }

    /**
     * RFQ SNAPSHOT — TURA KİLİTLENEN DEĞİŞMEZ GÖRÜNTÜ.
     *
     * Firmaya "şu ürünleri fiyatla" dedikten sonra listede bir ürünün adını
     * değiştirirsek, firma başka bir şeyi fiyatlamış olur ve kimse farkı
     * göremez. Snapshot bunu imkânsız kılar: tur açılırken listenin o anki
     * görüntüsü DONAR ve firma daima onu görür.
     *
     * Üç dilli alanlar JSON olarak saklanır (tr/en/zh/source): portal sayfanın
     * TAMAMINI tek dile çevirir (K81 istisnası, sıfır karışık dil) ve çeviri
     * o an üretilmez — snapshot anında donmuş olanı gösterir.
     */
    private function rfqSnapshots(PDO $pdo, bool $sqlite): void
    {
        if (!$this->tabloVar($pdo, 'rfq_snapshots')) {
            $pdo->exec($sqlite
                ? 'CREATE TABLE rfq_snapshots (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    list_id INTEGER NOT NULL,
                    list_revision INTEGER NOT NULL DEFAULT 0,
                    satir_sayisi INTEGER NOT NULL DEFAULT 0,
                    olusturan_id INTEGER NULL,
                    created_at TEXT NOT NULL
                )'
                : 'CREATE TABLE rfq_snapshots (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    list_id BIGINT UNSIGNED NOT NULL,
                    list_revision INT UNSIGNED NOT NULL DEFAULT 0,
                    satir_sayisi INT UNSIGNED NOT NULL DEFAULT 0,
                    olusturan_id BIGINT UNSIGNED NULL,
                    created_at DATETIME NOT NULL,
                    KEY ix_rfq_liste (list_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', );
        }

        if ($this->tabloVar($pdo, 'rfq_lines')) {
            return;
        }

        // `rfq_satir_id` UUID'dir ve DIŞ DÜNYAYA açılan kimliktir: Excel
        // şablonundaki gizli `satir_imzasi` ve yapıştır-ayrıştır eşleştirmesi
        // buna dayanır. Otomatik artan id kullanılsaydı, firma dosyasındaki
        // bir sayı başka bir kurulumun satırına denk gelebilirdi.
        $pdo->exec($sqlite
            ? 'CREATE TABLE rfq_lines (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                rfq_snapshot_id INTEGER NOT NULL,
                rfq_satir_id TEXT NOT NULL,
                product_id INTEGER NULL,
                sira INTEGER NOT NULL DEFAULT 0,
                urun_kodu TEXT NULL,
                urun_adi_json TEXT NOT NULL,
                kaynak_urun_json TEXT NULL,
                talep_varyant_json TEXT NULL,
                talep_miktar TEXT NOT NULL DEFAULT "0",
                talep_birim TEXT NOT NULL DEFAULT "adet",
                alici_notu_json TEXT NULL,
                gorsel_url TEXT NULL,
                created_at TEXT NOT NULL,
                UNIQUE (rfq_snapshot_id, rfq_satir_id)
            )'
            : 'CREATE TABLE rfq_lines (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                rfq_snapshot_id BIGINT UNSIGNED NOT NULL,
                rfq_satir_id CHAR(36) NOT NULL,
                product_id BIGINT UNSIGNED NULL,
                sira INT UNSIGNED NOT NULL DEFAULT 0,
                urun_kodu VARCHAR(64) NULL,
                urun_adi_json JSON NOT NULL,
                kaynak_urun_json JSON NULL,
                talep_varyant_json JSON NULL,
                talep_miktar DECIMAL(12,3) NOT NULL DEFAULT 0,
                talep_birim VARCHAR(16) NOT NULL DEFAULT "adet",
                alici_notu_json JSON NULL,
                gorsel_url VARCHAR(1000) NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_rfq_satir (rfq_snapshot_id, rfq_satir_id),
                KEY ix_rfq_satir_urun (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', );
    }

    /**
     * TURUN KENDİSİ (#15 §2 — 10 durum).
     *
     * `state` enum DEĞİL VARCHAR: durum kümesi V3-N/V3-D ile büyüyecek ve
     * MySQL'de enum genişletmek tabloyu yeniden yazar. Geçerli değerler
     * uygulama katmanında (`TurDurumMakinesi`) zorlanır — liste/ürün durum
     * makinesiyle aynı desen (K37).
     *
     * KUR DÖRTLÜSÜ KOPYADIR, REFERANS DEĞİL (#28 kanıt seti). `rate_snapshot_id`
     * yalnız PROVENANCE'tır — "hangi snapshot'tan geldi" sorusunu cevaplar.
     * Turun hesapta kullandığı kur açılış anında `kur_para_birimi`,
     * `kur_degeri`, `kur_kaynagi`, `kur_kilit_at` kolonlarına KOPYALANIR;
     * snapshot satırı sonradan değişse ya da silinse turun hangi kurla
     * konuşulduğu DEĞİŞMEZ. Yalnız referans tutulsaydı bu bilgi kaybolurdu.
     *
     * K50 SINIRI BURADA DA GEÇERLİ: belge kuru bu kolonlardan DEĞİL,
     * `lists.yuan_rate` kopyasından okunur. Bunlar turun iç kıyası içindir.
     *
     * `tur_no` durum adına GÖMÜLMEZ (#15 §2): "R2 gönderildi" arayüz metnidir,
     * `tur_no=2, state=SENT` veridir. Böylece R3, R4 için yeni durum gerekmez.
     */
    private function supplierRounds(PDO $pdo, bool $sqlite): void
    {
        if ($this->tabloVar($pdo, 'supplier_rounds')) {
            return;
        }

        $pdo->exec($sqlite
            ? 'CREATE TABLE supplier_rounds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                list_id INTEGER NOT NULL,
                supplier_id INTEGER NOT NULL,
                tur_no INTEGER NOT NULL DEFAULT 1,
                parent_round_id INTEGER NULL,
                state TEXT NOT NULL DEFAULT "DRAFT",
                state_changed_at TEXT NULL,
                state_changed_by_type TEXT NULL,
                state_reason TEXT NULL,
                rfq_snapshot_id INTEGER NULL,
                rate_snapshot_id INTEGER NULL,
                kur_para_birimi TEXT NULL,
                kur_degeri TEXT NULL,
                kur_kaynagi TEXT NULL,
                kur_kilit_at TEXT NULL,
                rate_policy TEXT NOT NULL DEFAULT "inherit",
                share_id INTEGER NULL,
                kapsam_satir_json TEXT NULL,
                gecerlilik_gun INTEGER NULL,
                valid_until TEXT NULL,
                portal_dili TEXT NOT NULL DEFAULT "zh",
                firma_yazan_ad TEXT NULL,
                drafted_at TEXT NULL,
                sent_at TEXT NULL,
                first_viewed_at TEXT NULL,
                last_viewed_at TEXT NULL,
                pricing_started_at TEXT NULL,
                last_partial_submitted_at TEXT NULL,
                partial_submission_count INTEGER NOT NULL DEFAULT 0,
                responded_at TEXT NULL,
                approved_at TEXT NULL,
                revision_requested_at TEXT NULL,
                expired_at TEXT NULL,
                revoked_at TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (list_id, supplier_id, tur_no)
            )'
            : 'CREATE TABLE supplier_rounds (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                list_id BIGINT UNSIGNED NOT NULL,
                supplier_id BIGINT UNSIGNED NOT NULL,
                tur_no INT UNSIGNED NOT NULL DEFAULT 1,
                parent_round_id BIGINT UNSIGNED NULL,
                state VARCHAR(24) NOT NULL DEFAULT "DRAFT",
                state_changed_at DATETIME NULL,
                state_changed_by_type VARCHAR(16) NULL,
                state_reason VARCHAR(500) NULL,
                rfq_snapshot_id BIGINT UNSIGNED NULL,
                rate_snapshot_id BIGINT UNSIGNED NULL,
                kur_para_birimi VARCHAR(4) NULL,
                kur_degeri DECIMAL(12,4) NULL,
                kur_kaynagi VARCHAR(8) NULL,
                kur_kilit_at DATETIME NULL,
                rate_policy VARCHAR(8) NOT NULL DEFAULT "inherit",
                share_id BIGINT UNSIGNED NULL,
                kapsam_satir_json JSON NULL,
                gecerlilik_gun INT UNSIGNED NULL,
                valid_until DATETIME NULL,
                portal_dili VARCHAR(8) NOT NULL DEFAULT "zh",
                firma_yazan_ad VARCHAR(190) NULL,
                drafted_at DATETIME NULL,
                sent_at DATETIME NULL,
                first_viewed_at DATETIME NULL,
                last_viewed_at DATETIME NULL,
                pricing_started_at DATETIME NULL,
                last_partial_submitted_at DATETIME NULL,
                partial_submission_count INT UNSIGNED NOT NULL DEFAULT 0,
                responded_at DATETIME NULL,
                approved_at DATETIME NULL,
                revision_requested_at DATETIME NULL,
                expired_at DATETIME NULL,
                revoked_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_tur (list_id, supplier_id, tur_no),
                KEY ix_tur_durum (state, valid_until),
                KEY ix_tur_liste (list_id, state),
                KEY ix_tur_paylasim (share_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', );
    }

    /**
     * FİRMA YANITI — 19 ALAN (rfq-alan-sozlesmesi.json).
     *
     * PARA `DECIMAL(12,2)`, KUR `DECIMAL(12,4)` (K14/K24): float ile para
     * tutulmaz. JSON'da string taşınır.
     *
     * `yanit_durumu` DÖRT DEĞER alır ve `unanswered` İLE `not_found` AYRI
     * ŞEYLERDİR (#28 yasak varsayım 2): boş satır "Bulunamadı" demek değildir,
     * "henüz cevaplanmadı" demektir. Bu ayrım kaybolursa kısmi tur, tamamlanmış
     * gibi görünür.
     */
    private function quoteResponses(PDO $pdo, bool $sqlite): void
    {
        if (!$this->tabloVar($pdo, 'quote_responses')) {
            $pdo->exec($sqlite
                ? 'CREATE TABLE quote_responses (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    supplier_round_id INTEGER NOT NULL,
                    surum INTEGER NOT NULL DEFAULT 1,
                    kanal TEXT NOT NULL DEFAULT "portal",
                    ham_kaynak TEXT NULL,
                    yazan_ad TEXT NULL,
                    gecerlilik_onayi INTEGER NOT NULL DEFAULT 0,
                    ddp_kdv_onayi INTEGER NOT NULL DEFAULT 0,
                    kismi INTEGER NOT NULL DEFAULT 1,
                    gonderildi_at TEXT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )'
                : 'CREATE TABLE quote_responses (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    supplier_round_id BIGINT UNSIGNED NOT NULL,
                    surum INT UNSIGNED NOT NULL DEFAULT 1,
                    kanal VARCHAR(16) NOT NULL DEFAULT "portal",
                    ham_kaynak MEDIUMTEXT NULL,
                    yazan_ad VARCHAR(190) NULL,
                    gecerlilik_onayi TINYINT(1) NOT NULL DEFAULT 0,
                    ddp_kdv_onayi TINYINT(1) NOT NULL DEFAULT 0,
                    kismi TINYINT(1) NOT NULL DEFAULT 1,
                    gonderildi_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    KEY ix_yanit_tur (supplier_round_id, surum)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', );
        }

        if (!$this->tabloVar($pdo, 'quote_lines')) {
            $pdo->exec($sqlite
                ? 'CREATE TABLE quote_lines (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    quote_response_id INTEGER NOT NULL,
                    rfq_satir_id TEXT NOT NULL,
                    yanit_durumu TEXT NOT NULL DEFAULT "unanswered",
                    ddp_birim_fiyat TEXT NULL,
                    para_birimi TEXT NULL,
                    ddp_kdv_dahil_onayi INTEGER NULL,
                    moq_deger TEXT NULL,
                    moq_birim TEXT NULL,
                    termin_baslangici TEXT NULL,
                    termin_baslangici_aciklamasi TEXT NULL,
                    termin_suresi INTEGER NULL,
                    termin_birimi TEXT NULL,
                    koli_ici_adet INTEGER NULL,
                    koli_uzunluk_cm TEXT NULL,
                    koli_genislik_cm TEXT NULL,
                    koli_yukseklik_cm TEXT NULL,
                    koli_cbm TEXT NULL,
                    koli_brut_kg TEXT NULL,
                    koli_net_kg TEXT NULL,
                    ambalaj TEXT NULL,
                    firma_notu TEXT NULL,
                    gonderildi_at TEXT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    UNIQUE (quote_response_id, rfq_satir_id)
                )'
                : 'CREATE TABLE quote_lines (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    quote_response_id BIGINT UNSIGNED NOT NULL,
                    rfq_satir_id CHAR(36) NOT NULL,
                    yanit_durumu VARCHAR(24) NOT NULL DEFAULT "unanswered",
                    ddp_birim_fiyat DECIMAL(12,2) NULL,
                    para_birimi VARCHAR(4) NULL,
                    ddp_kdv_dahil_onayi TINYINT(1) NULL,
                    moq_deger DECIMAL(12,3) NULL,
                    moq_birim VARCHAR(16) NULL,
                    termin_baslangici VARCHAR(32) NULL,
                    termin_baslangici_aciklamasi VARCHAR(300) NULL,
                    termin_suresi INT UNSIGNED NULL,
                    termin_birimi VARCHAR(16) NULL,
                    koli_ici_adet INT UNSIGNED NULL,
                    koli_uzunluk_cm DECIMAL(10,2) NULL,
                    koli_genislik_cm DECIMAL(10,2) NULL,
                    koli_yukseklik_cm DECIMAL(10,2) NULL,
                    koli_cbm DECIMAL(12,4) NULL,
                    koli_brut_kg DECIMAL(10,3) NULL,
                    koli_net_kg DECIMAL(10,3) NULL,
                    ambalaj VARCHAR(300) NULL,
                    firma_notu TEXT NULL,
                    gonderildi_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE KEY uq_yanit_satir (quote_response_id, rfq_satir_id),
                    KEY ix_yanit_satir_durum (yanit_durumu)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', );
        }

        if (!$this->tabloVar($pdo, 'quote_price_tiers')) {
            // KADEMELER ARASI DOĞRUSAL İNTERPOLASYON YASAK (#28 yasak varsayım 4):
            // 500 ve 1000 kademesi arasında 700 için fiyat HESAPLANMAZ. Bu yüzden
            // `min_adet`/`max_adet` ve "eşik mi aralık mı" AÇIKÇA saklanır.
            $pdo->exec($sqlite
                ? 'CREATE TABLE quote_price_tiers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    quote_line_id INTEGER NOT NULL,
                    sira INTEGER NOT NULL DEFAULT 0,
                    min_adet TEXT NOT NULL,
                    max_adet TEXT NULL,
                    birim_fiyat TEXT NOT NULL,
                    para_birimi TEXT NULL,
                    kademe_tipi TEXT NOT NULL DEFAULT "esik",
                    created_at TEXT NOT NULL
                )'
                : 'CREATE TABLE quote_price_tiers (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    quote_line_id BIGINT UNSIGNED NOT NULL,
                    sira INT UNSIGNED NOT NULL DEFAULT 0,
                    min_adet DECIMAL(12,3) NOT NULL,
                    max_adet DECIMAL(12,3) NULL,
                    birim_fiyat DECIMAL(12,2) NOT NULL,
                    para_birimi VARCHAR(4) NULL,
                    kademe_tipi VARCHAR(8) NOT NULL DEFAULT "esik",
                    created_at DATETIME NOT NULL,
                    KEY ix_kademe_satir (quote_line_id, sira)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', );
        }

        if ($this->tabloVar($pdo, 'quote_alternatives')) {
            return;
        }

        // ALTERNATİF AYRI BİR CEVAP NESNESİDİR (#28): asıl satır "Bulunamadı"
        // kalır, alternatif ona BAĞLI ama AYRI satır olarak gelir. Aynı satıra
        // yazsaydık, "istediğim ürün bulundu" ile "başka bir şey öneriyorum"
        // ayırt edilemezdi.
        $pdo->exec($sqlite
            ? 'CREATE TABLE quote_alternatives (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                quote_line_id INTEGER NOT NULL,
                baglanti TEXT NULL,
                gorsel_url TEXT NULL,
                aciklama TEXT NULL,
                ddp_birim_fiyat TEXT NULL,
                para_birimi TEXT NULL,
                kabul_edildi_at TEXT NULL,
                olusan_product_id INTEGER NULL,
                created_at TEXT NOT NULL
            )'
            : 'CREATE TABLE quote_alternatives (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                quote_line_id BIGINT UNSIGNED NOT NULL,
                baglanti VARCHAR(1000) NULL,
                gorsel_url VARCHAR(1000) NULL,
                aciklama TEXT NULL,
                ddp_birim_fiyat DECIMAL(12,2) NULL,
                para_birimi VARCHAR(4) NULL,
                kabul_edildi_at DATETIME NULL,
                olusan_product_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                KEY ix_alternatif_satir (quote_line_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', );
    }

    private function tabloVar(PDO $pdo, string $tablo): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :ad");
            $statement->execute(['ad' => $tablo]);

            return (int) $statement->fetchColumn() > 0;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :ad',
        );
        $statement->execute(['ad' => $tablo]);

        return (int) $statement->fetchColumn() > 0;
    }
};
