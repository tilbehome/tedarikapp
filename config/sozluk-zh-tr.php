<?php

declare(strict_types=1);

/**
 * ZH→TR YEREL SÖZLÜK (İE#14 A2 · KARAR K56 Katman 1).
 *
 * NEDEN DOSYA: kapalı kümeler (malzeme, renk, menşe, birim, ambalaj, sertifika)
 * belirlenimci çevrilmelidir — aynı terim her belgede AYNI karşılığı almalı.
 * Şema açmaya değmez; dosya sürüm kontrolündedir, değişimi commit'te görünür.
 *
 * NASIL BÜYÜR: `php bin/sozluk-tohum.php` canlı `raw_attributes` DEĞERLERİNDEN
 * frekans çıkarır ve aday listesi üretir; insan gözden geçirip buraya ekler.
 * Panelden de düzenlenebilir (Ayarlar > Terminoloji) — yazma bu dosyaya olur.
 *
 * KURAL: burada YALNIZCA kapalı küme terimleri bulunur. Marka, model kodu,
 * ölçü/sayı/birim ve ilan numarası ASLA çevrilmez (K56 ortak kuralı).
 *
 * Anahtarlar Çince terim, değerler Türkçe karşılıktır. Eşleşme TAM METİN
 * üzerindendir; kısmi/eşzamanlı değiştirme yapılmaz (yanlış birleşimleri önler).
 */
return [
    // ── Malzeme ──
    '不锈钢' => 'Paslanmaz çelik',
    '304不锈钢' => '304 paslanmaz çelik',
    '316不锈钢' => '316 paslanmaz çelik',
    '合金' => 'Alaşım',
    '铝合金' => 'Alüminyum alaşım',
    '锌合金' => 'Çinko alaşım',
    '塑料' => 'Plastik',
    'ABS' => 'ABS plastik',
    'PVC' => 'PVC',
    'PP' => 'PP (polipropilen)',
    'PE' => 'PE (polietilen)',
    '硅胶' => 'Silikon',
    '橡胶' => 'Kauçuk',
    '玻璃' => 'Cam',
    '陶瓷' => 'Seramik',
    '木质' => 'Ahşap',
    '竹' => 'Bambu',
    '皮革' => 'Deri',
    'PU皮' => 'PU deri',
    '棉' => 'Pamuk',
    '涤纶' => 'Polyester',
    '尼龙' => 'Naylon',
    '碳钢' => 'Karbon çelik',
    '铁' => 'Demir',
    '铜' => 'Bakır',
    '纸' => 'Kâğıt',

    // ── Renk ──
    '白色' => 'Beyaz',
    '黑色' => 'Siyah',
    '灰色' => 'Gri',
    '银色' => 'Gümüş',
    '金色' => 'Altın',
    '红色' => 'Kırmızı',
    '蓝色' => 'Mavi',
    '深蓝' => 'Lacivert',
    '绿色' => 'Yeşil',
    '黄色' => 'Sarı',
    '粉色' => 'Pembe',
    '紫色' => 'Mor',
    '橙色' => 'Turuncu',
    '棕色' => 'Kahverengi',
    '米色' => 'Bej',
    '透明' => 'Şeffaf',
    '彩色' => 'Renkli',
    '混色' => 'Karışık renk',

    // ── Menşe / bölge ──
    '中国' => 'Çin',
    '中国大陆' => 'Çin',
    '浙江' => 'Zhejiang',
    '广东' => 'Guangdong',
    '江苏' => 'Jiangsu',
    '福建' => 'Fujian',
    '山东' => 'Shandong',
    '义乌' => 'Yiwu',
    '深圳' => 'Shenzhen',
    '广州' => 'Guangzhou',
    '宁波' => 'Ningbo',

    // ── Birim ──
    '个' => 'adet',
    '件' => 'adet',
    '只' => 'adet',
    '套' => 'takım',
    '对' => 'çift',
    '双' => 'çift',
    '包' => 'paket',
    '箱' => 'koli',
    '盒' => 'kutu',
    '袋' => 'poşet',
    '米' => 'metre',
    '公斤' => 'kg',
    '克' => 'gram',
    '升' => 'litre',
    '毫升' => 'ml',

    // ── Ambalaj ──
    '彩盒' => 'Renkli kutu',
    '白盒' => 'Beyaz kutu',
    '中性包装' => 'Nötr ambalaj',
    '塑料袋' => 'Plastik poşet',
    '纸箱' => 'Karton koli',
    '气泡袋' => 'Baloncuklu poşet',
    '吸塑' => 'Blister',
    '独立包装' => 'Tekli ambalaj',
    '简装' => 'Sade ambalaj',
    '礼盒装' => 'Hediye kutusu',

    // ── Sertifika / uyumluluk ──
    'CE认证' => 'CE sertifikalı',
    'CE' => 'CE',
    'ROHS' => 'RoHS',
    'FCC' => 'FCC',
    '3C认证' => '3C sertifikalı',
    'FDA' => 'FDA',
    '食品级' => 'Gıdaya uygun',
    '医用级' => 'Tıbbi sınıf',
    '防水' => 'Su geçirmez',
    '防尘' => 'Toz geçirmez',
    '可充电' => 'Şarj edilebilir',
    '无线' => 'Kablosuz',
    '有线' => 'Kablolu',

    // ── Sık geçen öznitelik ADLARI (değer değil, etiket) ──
    '品牌' => 'Marka',
    '型号' => 'Model',
    '材质' => 'Malzeme',
    '颜色' => 'Renk',
    '尺寸' => 'Ölçü',
    '重量' => 'Ağırlık',
    '净重' => 'Net ağırlık',
    '产地' => 'Menşe',
    '容量' => 'Kapasite',
    '功率' => 'Güç',
    '电压' => 'Voltaj',
    '保修' => 'Garanti',
    '认证' => 'Sertifika',
    '包装' => 'Ambalaj',
    '适用人群' => 'Kullanım alanı',
    '适用场景' => 'Kullanım yeri',
    '货号' => 'Stok kodu',
    '规格' => 'Varyasyon',
    '起订量' => 'Asgari sipariş',
    '装箱数' => 'Koli içi adet',
];
