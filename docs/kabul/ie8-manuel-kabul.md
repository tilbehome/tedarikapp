# İE#8 Manuel Kabul Listesi — Ekran Görüntülü (K35)

> İE#9 kabul şartı: "İE#8'in eksik kabul kanıtı bu raporla birlikte gelir."
> Bu listeyi **Bünyamin** gerçek cihazlarda doldurur: her maddeye ✓/✗ + ekran görüntüsü
> (telefon + masaüstü). Görüntüler bu klasöre `ie8-ss-<no>-<aciklama>.png` adıyla konur
> veya PM sohbetine iletilir. Otomatik UI testi bilinçli olarak YOKTUR (K35).
>
> Ortam: üretim adayı sunucu (veya lokal kurulum) · panel `npm run build` çıktısıyla
> servis edilir · tarayıcı: telefonda gerçek cihaz (ya da 390px emülasyon) + masaüstü.

## A. Telefon akışı (gerçek cihaz veya 390px)

| # | Madde | ✓/✗ | Ekran görüntüsü |
|---|---|---|---|
| A1 | Giriş: şifre → TOTP ekranı → panele giriş | | |
| A2 | Yeni liste oluştur (ad + dönem) | | |
| A3 | 3 ürün ekle — en az biri görsel URL'li (MediaService'ten geçtiği görülür) | | |
| A4 | Ürün durumlarını tek dokunuş menüsüyle ilerlet (izinli geçişler menüde) | | |
| A5 | Liste toplamlarını gör (adet · ¥ · ₺) — backend TOPLAM satırı | | |
| A6 | Listeyi kopyala (kopya taslak + güncel kur) | | |
| A7 | Listeyi çöpe at → Çöp Kutusu'nda gör → geri al | | |
| A8 | Akışın tamamı takılmadan, yatay kaydırma olmadan AKICI | | |

## B. Masaüstü akışı

| # | Madde | ✓/✗ | Ekran görüntüsü |
|---|---|---|---|
| B1 | A2–A7 aynı akış TABLO görünümüyle | | |
| B2 | Yan menü, tablo sıralama, toplu durum işlemi çalışıyor | | |

## C. Davranış doğrulamaları

| # | Madde | ✓/✗ | Kanıt |
|---|---|---|---|
| C1 | Kur değişince YENİ liste yeni kuru kilitliyor; eski listenin TL'si OYNAMIYOR | | |
| C2 | Geçersiz durum geçişi UI'da hiç SUNULMUYOR (menü izinli listeden kuruluyor); zorlanırsa 422 Türkçe mesajla | | |
| C3 | Para her yerde string→görüntü; JS'te para aritmetiği yok (kod taraması: `frontend/src` içinde fiyat alanlarında `+ - * /` araması boş) | | |
| C4 | Oturum düşünce girişe dönüş; CSRF hatasında düzgün Türkçe mesaj | | |
| C5 | Export/Paylaşım butonları görünür ama "Faz 2" rozetiyle pasif; Gelen Kutusu "yakında" | | |
| C6 | Terminal (tamamlanmış/iptal) listede düzenleme kontrolleri kapalı; sunucu 422 `LIST_IMMUTABLE` döndürüyor (İE#9 eki) | | |

## D. CI / bağımlılık (rapor koşumundan işlenir)

| # | Madde | ✓/✗ |
|---|---|---|
| D1 | CI backend + panel + MySQL entegrasyon işleri yeşil | |
| D2 | `tsc --noEmit` 0 hata · ESLint 0 | |
| D3 | K19 dışı bağımlılık yok (`frontend/package.json` denetimi) | |

**Sonuç:** ☑ KABUL · ☐ ŞARTLI (eksikler listelenir) · ☐ RET
İmza/tarih: Bünyamin (Ürün Sahibi) · 19 Ağustos 2026

> **FAZ 1 KAPANIŞ KAYDI (İE#10 Blok 6):** Ürün Sahibi 19 Ağu 2026'da masaüstü +
> telefon kabul turunu canlı v0.9.5 üzerinde tamamladı, sorun bildirilmedi.
> (Ekran kanıtları PM sohbetine iletildi: Ana Ekran masaüstü + mobil, liste detayı,
> ürün formu — kur/K48 etiketi ve TOPLAM satırı doğrulanmış hâlde.) Tek tek satır
> işaretleri yerine bu toplu kayıt geçerlidir — Ürün Sahibi kararı.
