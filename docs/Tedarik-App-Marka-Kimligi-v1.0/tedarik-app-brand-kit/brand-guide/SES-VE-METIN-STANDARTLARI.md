# Ses ve Metin Standartları

## Marka sesi

Tedarik App; hızlı ama aceleci olmayan, kurumsal ama soğuk olmayan, teknik ama anlaşılır bir dille konuşur.

## Arayüz metni kalıpları

| Amaç | Önerilen metin |
| --- | --- |
| Ana aktarım | Ürünü Tedarik App'e Aktar |
| Yeniden deneme | Tekrar Dene |
| Panel bağlantısı | Panelde Aç |
| Taslak kayıt | Taslak Olarak Kaydet |
| Kesin işlem | Siparişi Onayla |
| Tehlikeli işlem | Listeyi Kalıcı Olarak Sil |
| Boş gelen kutusu | Henüz aktarılmış ürün yok |
| Başarı | Ürün panele aktarıldı |
| Kısmi başarı | Ürün aktarıldı; bazı alanlar doğrulanmalı |
| Bağlantı hatası | Panele ulaşılamadı. Bağlantınızı kontrol edip tekrar deneyin. |

## Veri güven etiketleri

- **Kaynak verisi:** Sayfadan aynen alınmıştır.
- **Dönüştürüldü:** Birim, para veya dil dönüşümü uygulanmıştır.
- **Tahmini:** Model ya da kural tabanlı tahmindir.
- **Doğrulandı:** Kullanıcı veya yetkili tarafından kontrol edilmiştir.
- **Eksik:** Kaynaktan alınamamıştır.

## Tarih ve sayı biçimi

- Dahili TR: `23.08.2026`
- Uluslararası paylaşım: `2026-08-23`
- Para: `¥ 12.50`, `$ 2.40`, `₺ 145,90`
- Miktar: sayı ve birim ayrılır: `240 adet`, `12 koli`
- Aralık: `100-250 adet`
- Bilinmeyen değer boş bırakılmaz; `Belirtilmemiş` yazılır.

## Kaçınılacak dil

- “En iyi”, “kusursuz”, “sıfır risk” gibi doğrulanamaz iddialar
- Kullanıcının suçlandığı ifadeler
- Yalnız teknik hata kodundan oluşan mesajlar
- Birden fazla fiil içeren belirsiz buton metinleri
