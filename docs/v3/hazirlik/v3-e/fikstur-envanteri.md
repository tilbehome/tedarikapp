> İşlev: Sekiz platform adaptörü için hangi gerçek sayfa türlerinin toplanacağını ve neyi kanıtlayacağını sözleşmeye bağlar.  
> Faz: V3-E adaptör geliştirme, fikstür toplama ve platform mezuniyet kabulünde kullanılır.  
> Örnekleme: Her platform bağımsız olarak 30 gerçek sayfa, farklı kategori ve şablonla sınanır.  
> Ölçüm: Kritik alan ≥%95, yanlış fiyat <%1, yanlış varyant <%1 ve çift kayıt 0 birlikte sağlanmalıdır.  
> Kapsam dışı: Bu görev gerçek HTML/HAR toplamaz; ince ekran, otomatik tarama, GTİP, mevzuat ve vergi hesabı üretmez.

# Görev #17B — Fikstür Envanteri ve Mezuniyet Kapısı

## 1. Bağlayıcı ilkeler

- Fikstür yalnız kullanıcının açık yakalama eylemiyle elde edilen, kişisel veri ve oturum sırrı temizlenmiş HTML/JSON-LD/ağ yanıtı parçalarından oluşur.
- Platformun sağlamadığı alan negatif kontroldür: beklenen sonuç `null + capability=YOK` olur; `0`, boş dizi veya yapay metrik kabul edilmez.
- P3 AliExpress/Taobao yalnız referans, P4 Amazon/Temu yalnız hedef pazar kıyasıdır; bu sayfalar siparişe uygun tedarik kaynağına dönüşmez.
- Her fikstür `platform`, `adapter_version`, `captured_at`, `locale`, `currency_context`, `page_type`, `source_url_hash` ve elle doğrulanmış altın çıktıyı taşır.

## 2. Platform başına sayfa tipi envanteri

| Platform | Öncelik/rol | Sayfa tipi | Toplama beklentisi | Neyi kanıtlar |
|---|---|---|---|---|
| 1688 | P0 · tedarik_kaynagi | Standart ürün | En az 1 gerçek örnek | Temel kimlik, başlık, fiyat, satıcı, özellik ve medya çıkarımını kanıtlar. |
| 1688 | P0 · tedarik_kaynagi | Varyasyonlu ürün | En az 1 gerçek örnek | Varyant boyutları ile SKU-fiyat-stok bağının kartezyen olmayan gerçek matriste korunduğunu kanıtlar. |
| 1688 | P0 · tedarik_kaynagi | Kademeli fiyatlı | En az 1 gerçek örnek | Miktar eşiği, fiyat ve birim bağının kampanya fiyatına karıştırılmadığını kanıtlar. |
| 1688 | P0 · tedarik_kaynagi | Videolu ürün | En az 1 pozitif + 1 alansız örnek | Video ve poster kaynağının galeri görselinden ayrıldığını kanıtlar. |
| 1688 | P0 · tedarik_kaynagi | Özel üretim / 定制 | En az 1 gerçek örnek | Özelleştirme sinyalinin MOQ ve normal stoklu ürünle karıştırılmadığını kanıtlar. |
| 1688 | P0 · tedarik_kaynagi | Kapanmış ilan | En az 1 gerçek örnek | Sayfanın kapanmış/yayından kalkmış durumunun eski fiyat üretmeden sert engel oluşturduğunu kanıtlar. |
| 1688 | P0 · tedarik_kaynagi | Yönlendirmeli/kampanyalı | En az 1 gerçek örnek | Kanonik ürün kimliğinin kupon, reklam ve yönlendirme parametrelerinden ayrıldığını kanıtlar. |
| 1688 | P0 · tedarik_kaynagi | Seyrek/verisi eksik | En az 1 gerçek örnek | Eksik alanların sıfır veya uydurma değer yerine null + kanıt durumu verdiğini kanıtlar. |
| 1688 | P0 · tedarik_kaynagi | Bölge/oturum varyantı | En az 1 gerçek örnek | Para birimi, dil, fiyat ve stok bağlamının bölge/oturum snapshot’ıyla kaydedildiğini kanıtlar. |
| Alibaba.com | P1 · tedarik_kaynagi | Standart ürün | En az 1 gerçek örnek | Temel kimlik, başlık, fiyat, satıcı, özellik ve medya çıkarımını kanıtlar. |
| Alibaba.com | P1 · tedarik_kaynagi | Varyasyonlu ürün | En az 1 gerçek örnek | Varyant boyutları ile SKU-fiyat-stok bağının kartezyen olmayan gerçek matriste korunduğunu kanıtlar. |
| Alibaba.com | P1 · tedarik_kaynagi | Kademeli fiyatlı | En az 1 gerçek örnek | Miktar eşiği, fiyat ve birim bağının kampanya fiyatına karıştırılmadığını kanıtlar. |
| Alibaba.com | P1 · tedarik_kaynagi | Videolu ürün | En az 1 gerçek örnek | Video ve poster kaynağının galeri görselinden ayrıldığını kanıtlar. |
| Alibaba.com | P1 · tedarik_kaynagi | Özel üretim / 定制 | En az 1 gerçek örnek | Özelleştirme sinyalinin MOQ ve normal stoklu ürünle karıştırılmadığını kanıtlar. |
| Alibaba.com | P1 · tedarik_kaynagi | Kapanmış ilan | En az 1 gerçek örnek | Sayfanın kapanmış/yayından kalkmış durumunun eski fiyat üretmeden sert engel oluşturduğunu kanıtlar. |
| Alibaba.com | P1 · tedarik_kaynagi | Yönlendirmeli/kampanyalı | En az 1 gerçek örnek | Kanonik ürün kimliğinin kupon, reklam ve yönlendirme parametrelerinden ayrıldığını kanıtlar. |
| Alibaba.com | P1 · tedarik_kaynagi | Seyrek/verisi eksik | En az 1 gerçek örnek | Eksik alanların sıfır veya uydurma değer yerine null + kanıt durumu verdiğini kanıtlar. |
| Alibaba.com | P1 · tedarik_kaynagi | Bölge/oturum varyantı | En az 1 gerçek örnek | Para birimi, dil, fiyat ve stok bağlamının bölge/oturum snapshot’ıyla kaydedildiğini kanıtlar. |
| Made-in-China | P2 · tedarik_kaynagi | Standart ürün | En az 1 gerçek örnek | Temel kimlik, başlık, fiyat, satıcı, özellik ve medya çıkarımını kanıtlar. |
| Made-in-China | P2 · tedarik_kaynagi | Varyasyonlu ürün | En az 1 gerçek örnek | Varyant boyutları ile SKU-fiyat-stok bağının kartezyen olmayan gerçek matriste korunduğunu kanıtlar. |
| Made-in-China | P2 · tedarik_kaynagi | Kademeli fiyatlı | En az 1 gerçek örnek | Miktar eşiği, fiyat ve birim bağının kampanya fiyatına karıştırılmadığını kanıtlar. |
| Made-in-China | P2 · tedarik_kaynagi | Videolu ürün | En az 1 gerçek örnek | Video ve poster kaynağının galeri görselinden ayrıldığını kanıtlar. |
| Made-in-China | P2 · tedarik_kaynagi | Özel üretim / 定制 | En az 1 gerçek örnek | Özelleştirme sinyalinin MOQ ve normal stoklu ürünle karıştırılmadığını kanıtlar. |
| Made-in-China | P2 · tedarik_kaynagi | Kapanmış ilan | En az 1 gerçek örnek | Sayfanın kapanmış/yayından kalkmış durumunun eski fiyat üretmeden sert engel oluşturduğunu kanıtlar. |
| Made-in-China | P2 · tedarik_kaynagi | Yönlendirmeli/kampanyalı | En az 1 gerçek örnek | Kanonik ürün kimliğinin kupon, reklam ve yönlendirme parametrelerinden ayrıldığını kanıtlar. |
| Made-in-China | P2 · tedarik_kaynagi | Seyrek/verisi eksik | En az 1 gerçek örnek | Eksik alanların sıfır veya uydurma değer yerine null + kanıt durumu verdiğini kanıtlar. |
| Made-in-China | P2 · tedarik_kaynagi | Bölge/oturum varyantı | En az 1 gerçek örnek | Para birimi, dil, fiyat ve stok bağlamının bölge/oturum snapshot’ıyla kaydedildiğini kanıtlar. |
| Global Sources | P2 · tedarik_kaynagi | Standart ürün | En az 1 gerçek örnek | Temel kimlik, başlık, fiyat, satıcı, özellik ve medya çıkarımını kanıtlar. |
| Global Sources | P2 · tedarik_kaynagi | Varyasyonlu ürün | En az 1 gerçek örnek | Varyant boyutları ile SKU-fiyat-stok bağının kartezyen olmayan gerçek matriste korunduğunu kanıtlar. |
| Global Sources | P2 · tedarik_kaynagi | Kademeli fiyatlı | En az 1 gerçek örnek | Miktar eşiği, fiyat ve birim bağının kampanya fiyatına karıştırılmadığını kanıtlar. |
| Global Sources | P2 · tedarik_kaynagi | Videolu ürün | En az 1 gerçek örnek | Video ve poster kaynağının galeri görselinden ayrıldığını kanıtlar. |
| Global Sources | P2 · tedarik_kaynagi | Özel üretim / 定制 | En az 1 gerçek örnek | Özelleştirme sinyalinin MOQ ve normal stoklu ürünle karıştırılmadığını kanıtlar. |
| Global Sources | P2 · tedarik_kaynagi | Kapanmış ilan | En az 1 gerçek örnek | Sayfanın kapanmış/yayından kalkmış durumunun eski fiyat üretmeden sert engel oluşturduğunu kanıtlar. |
| Global Sources | P2 · tedarik_kaynagi | Yönlendirmeli/kampanyalı | En az 1 gerçek örnek | Kanonik ürün kimliğinin kupon, reklam ve yönlendirme parametrelerinden ayrıldığını kanıtlar. |
| Global Sources | P2 · tedarik_kaynagi | Seyrek/verisi eksik | En az 1 gerçek örnek | Eksik alanların sıfır veya uydurma değer yerine null + kanıt durumu verdiğini kanıtlar. |
| Global Sources | P2 · tedarik_kaynagi | Bölge/oturum varyantı | En az 1 gerçek örnek | Para birimi, dil, fiyat ve stok bağlamının bölge/oturum snapshot’ıyla kaydedildiğini kanıtlar. |
| AliExpress | P3 · referans | Standart ürün | En az 1 gerçek örnek | Temel kimlik, başlık, fiyat, satıcı, özellik ve medya çıkarımını kanıtlar. |
| AliExpress | P3 · referans | Varyasyonlu ürün | En az 1 gerçek örnek | Varyant boyutları ile SKU-fiyat-stok bağının kartezyen olmayan gerçek matriste korunduğunu kanıtlar. |
| AliExpress | P3 · referans | Kademeli fiyatlı | En az 1 gerçek örnek | Miktar eşiği, fiyat ve birim bağının kampanya fiyatına karıştırılmadığını kanıtlar. |
| AliExpress | P3 · referans | Videolu ürün | En az 1 gerçek örnek | Video ve poster kaynağının galeri görselinden ayrıldığını kanıtlar. |
| AliExpress | P3 · referans | Özel üretim / 定制 | Negatif kontrol: alan üretilmemeli | Özelleştirme sinyalinin MOQ ve normal stoklu ürünle karıştırılmadığını kanıtlar. |
| AliExpress | P3 · referans | Kapanmış ilan | En az 1 gerçek örnek | Sayfanın kapanmış/yayından kalkmış durumunun eski fiyat üretmeden sert engel oluşturduğunu kanıtlar. |
| AliExpress | P3 · referans | Yönlendirmeli/kampanyalı | En az 1 gerçek örnek | Kanonik ürün kimliğinin kupon, reklam ve yönlendirme parametrelerinden ayrıldığını kanıtlar. |
| AliExpress | P3 · referans | Seyrek/verisi eksik | En az 1 gerçek örnek | Eksik alanların sıfır veya uydurma değer yerine null + kanıt durumu verdiğini kanıtlar. |
| AliExpress | P3 · referans | Bölge/oturum varyantı | En az 1 gerçek örnek | Para birimi, dil, fiyat ve stok bağlamının bölge/oturum snapshot’ıyla kaydedildiğini kanıtlar. |
| Taobao | P3 · referans | Standart ürün | En az 1 gerçek örnek | Temel kimlik, başlık, fiyat, satıcı, özellik ve medya çıkarımını kanıtlar. |
| Taobao | P3 · referans | Varyasyonlu ürün | En az 1 gerçek örnek | Varyant boyutları ile SKU-fiyat-stok bağının kartezyen olmayan gerçek matriste korunduğunu kanıtlar. |
| Taobao | P3 · referans | Kademeli fiyatlı | Negatif kontrol: alan üretilmemeli | Miktar eşiği, fiyat ve birim bağının kampanya fiyatına karıştırılmadığını kanıtlar. |
| Taobao | P3 · referans | Videolu ürün | En az 1 gerçek örnek | Video ve poster kaynağının galeri görselinden ayrıldığını kanıtlar. |
| Taobao | P3 · referans | Özel üretim / 定制 | Negatif kontrol: alan üretilmemeli | Özelleştirme sinyalinin MOQ ve normal stoklu ürünle karıştırılmadığını kanıtlar. |
| Taobao | P3 · referans | Kapanmış ilan | En az 1 gerçek örnek | Sayfanın kapanmış/yayından kalkmış durumunun eski fiyat üretmeden sert engel oluşturduğunu kanıtlar. |
| Taobao | P3 · referans | Yönlendirmeli/kampanyalı | En az 1 gerçek örnek | Kanonik ürün kimliğinin kupon, reklam ve yönlendirme parametrelerinden ayrıldığını kanıtlar. |
| Taobao | P3 · referans | Seyrek/verisi eksik | En az 1 gerçek örnek | Eksik alanların sıfır veya uydurma değer yerine null + kanıt durumu verdiğini kanıtlar. |
| Taobao | P3 · referans | Bölge/oturum varyantı | En az 1 gerçek örnek | Para birimi, dil, fiyat ve stok bağlamının bölge/oturum snapshot’ıyla kaydedildiğini kanıtlar. |
| Amazon | P4 · hedef_pazar_kiyasi | Standart ürün | En az 1 gerçek örnek | Temel kimlik, başlık, fiyat, satıcı, özellik ve medya çıkarımını kanıtlar. |
| Amazon | P4 · hedef_pazar_kiyasi | Varyasyonlu ürün | En az 1 gerçek örnek | Varyant boyutları ile SKU-fiyat-stok bağının kartezyen olmayan gerçek matriste korunduğunu kanıtlar. |
| Amazon | P4 · hedef_pazar_kiyasi | Kademeli fiyatlı | Negatif kontrol: alan üretilmemeli | Miktar eşiği, fiyat ve birim bağının kampanya fiyatına karıştırılmadığını kanıtlar. |
| Amazon | P4 · hedef_pazar_kiyasi | Videolu ürün | En az 1 gerçek örnek | Video ve poster kaynağının galeri görselinden ayrıldığını kanıtlar. |
| Amazon | P4 · hedef_pazar_kiyasi | Özel üretim / 定制 | En az 1 gerçek örnek | Özelleştirme sinyalinin MOQ ve normal stoklu ürünle karıştırılmadığını kanıtlar. |
| Amazon | P4 · hedef_pazar_kiyasi | Kapanmış ilan | En az 1 gerçek örnek | Sayfanın kapanmış/yayından kalkmış durumunun eski fiyat üretmeden sert engel oluşturduğunu kanıtlar. |
| Amazon | P4 · hedef_pazar_kiyasi | Yönlendirmeli/kampanyalı | En az 1 gerçek örnek | Kanonik ürün kimliğinin kupon, reklam ve yönlendirme parametrelerinden ayrıldığını kanıtlar. |
| Amazon | P4 · hedef_pazar_kiyasi | Seyrek/verisi eksik | En az 1 gerçek örnek | Eksik alanların sıfır veya uydurma değer yerine null + kanıt durumu verdiğini kanıtlar. |
| Amazon | P4 · hedef_pazar_kiyasi | Bölge/oturum varyantı | En az 1 gerçek örnek | Para birimi, dil, fiyat ve stok bağlamının bölge/oturum snapshot’ıyla kaydedildiğini kanıtlar. |
| Temu | P4 · hedef_pazar_kiyasi | Standart ürün | En az 1 gerçek örnek | Temel kimlik, başlık, fiyat, satıcı, özellik ve medya çıkarımını kanıtlar. |
| Temu | P4 · hedef_pazar_kiyasi | Varyasyonlu ürün | En az 1 gerçek örnek | Varyant boyutları ile SKU-fiyat-stok bağının kartezyen olmayan gerçek matriste korunduğunu kanıtlar. |
| Temu | P4 · hedef_pazar_kiyasi | Kademeli fiyatlı | En az 1 pozitif + 1 alansız örnek | Miktar eşiği, fiyat ve birim bağının kampanya fiyatına karıştırılmadığını kanıtlar. |
| Temu | P4 · hedef_pazar_kiyasi | Videolu ürün | En az 1 gerçek örnek | Video ve poster kaynağının galeri görselinden ayrıldığını kanıtlar. |
| Temu | P4 · hedef_pazar_kiyasi | Özel üretim / 定制 | En az 1 gerçek örnek | Özelleştirme sinyalinin MOQ ve normal stoklu ürünle karıştırılmadığını kanıtlar. |
| Temu | P4 · hedef_pazar_kiyasi | Kapanmış ilan | En az 1 gerçek örnek | Sayfanın kapanmış/yayından kalkmış durumunun eski fiyat üretmeden sert engel oluşturduğunu kanıtlar. |
| Temu | P4 · hedef_pazar_kiyasi | Yönlendirmeli/kampanyalı | En az 1 gerçek örnek | Kanonik ürün kimliğinin kupon, reklam ve yönlendirme parametrelerinden ayrıldığını kanıtlar. |
| Temu | P4 · hedef_pazar_kiyasi | Seyrek/verisi eksik | En az 1 gerçek örnek | Eksik alanların sıfır veya uydurma değer yerine null + kanıt durumu verdiğini kanıtlar. |
| Temu | P4 · hedef_pazar_kiyasi | Bölge/oturum varyantı | En az 1 gerçek örnek | Para birimi, dil, fiyat ve stok bağlamının bölge/oturum snapshot’ıyla kaydedildiğini kanıtlar. |

## 3. Otuz sayfalık örnekleme planı

Aşağıdaki 30 yuva **her platform için ayrı** doldurulur. Bir URL iki yuvayı karşılayamaz; aynı satıcının sayfaları toplam örneğin %20’sini aşamaz.

### Kategori dağılımı

| Kategori | Sayfa |
|---|---:|
| Ev & Mutfak | 7 |
| Banyo & Düzenleme | 5 |
| Tüketici Elektroniği | 6 |
| Küçük Ev Aleti | 4 |
| Ev Tekstili | 4 |
| Hırdavat/Diğer | 4 |

### Tip dağılımı

| Tip | Sayfa |
|---|---:|
| Standart ürün | 6 |
| Varyasyonlu ürün | 6 |
| Kademeli fiyatlı | 4 |
| Videolu ürün | 3 |
| Özel üretim / 定制 | 3 |
| Kapanmış ilan | 2 |
| Yönlendirmeli/kampanyalı | 2 |
| Seyrek/verisi eksik | 2 |
| Bölge/oturum varyantı | 2 |

### Kesin yuva listesi

| ID | Kategori | Sayfa tipi | Gerçek URL / fikstür kimliği | Altın doğrulayan |
|---|---|---|---|---|
| FP-01 | Ev & Mutfak | Standart ürün | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-02 | Banyo & Düzenleme | Standart ürün | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-03 | Tüketici Elektroniği | Standart ürün | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-04 | Küçük Ev Aleti | Standart ürün | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-05 | Ev Tekstili | Standart ürün | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-06 | Hırdavat/Diğer | Standart ürün | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-07 | Ev & Mutfak | Varyasyonlu ürün | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-08 | Banyo & Düzenleme | Varyasyonlu ürün | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-09 | Tüketici Elektroniği | Varyasyonlu ürün | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-10 | Küçük Ev Aleti | Varyasyonlu ürün | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-11 | Ev Tekstili | Varyasyonlu ürün | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-12 | Hırdavat/Diğer | Varyasyonlu ürün | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-13 | Ev & Mutfak | Kademeli fiyatlı | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-14 | Banyo & Düzenleme | Kademeli fiyatlı | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-15 | Tüketici Elektroniği | Kademeli fiyatlı | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-16 | Küçük Ev Aleti | Kademeli fiyatlı | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-17 | Ev Tekstili | Videolu ürün | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-18 | Hırdavat/Diğer | Videolu ürün | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-19 | Ev & Mutfak | Videolu ürün | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-20 | Banyo & Düzenleme | Özel üretim / 定制 | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-21 | Tüketici Elektroniği | Özel üretim / 定制 | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-22 | Küçük Ev Aleti | Özel üretim / 定制 | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-23 | Ev Tekstili | Kapanmış ilan | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-24 | Hırdavat/Diğer | Kapanmış ilan | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-25 | Ev & Mutfak | Yönlendirmeli/kampanyalı | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-26 | Banyo & Düzenleme | Yönlendirmeli/kampanyalı | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-27 | Tüketici Elektroniği | Seyrek/verisi eksik | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-28 | Ev & Mutfak | Seyrek/verisi eksik | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-29 | Ev & Mutfak | Bölge/oturum varyantı | _toplama işinde doldurulur_ | _iki göz kontrolü_ |
| FP-30 | Tüketici Elektroniği | Bölge/oturum varyantı | _toplama işinde doldurulur_ | _iki göz kontrolü_ |

## 4. Mezuniyet kapısı ölçüm şablonu

| Sayfa ID | Platform | Tip | Kritik beklenen | Kritik doğru | Kritik başarı % | Fiyat denemesi | Yanlış fiyat | Varyant denemesi | Yanlış varyant | Çift kayıt | Sonuç/not |
|---|---|---|---|---:|---:|---:|---:|---:|---:|---:|---|
| FP-… | … | … | … | … | … | … | … | … | … | … | … |

### Hesap ve karar kuralı

1. `kritik_alan_basarisi = toplam_dogru_kritik_hucre / toplam_uygulanabilir_kritik_hucre * 100`. Matriste `YOK` veya sayfa tipinde uygulanamaz alan paydaya girmez.
2. `yanlis_fiyat_orani = yanlis_fiyat / fiyat_denemesi * 100` ve `yanlis_varyant_orani = yanlis_varyant / varyant_denemesi * 100`; her ikisi de ayrı ayrı **< %1** olmalıdır.
3. Fiyat doğruluğu, para birimi + miktar eşiği + birim + seçili SKU bağının birlikte doğru olmasını ister; yalnız rakam eşleşmesi yetmez.
4. Varyant doğruluğu, boyut/değer/SKU/fiyat/stok ilişkisinin altın çıktıyla birebir eşleşmesidir; kartezyen ürün uydurmak yanlış sayılır.
5. `GEÇTİ = sayfa_sayisi=30 AND kategori_kotasi_tam AND tip_kotasi_tam AND kritik>=95 AND fiyat_hata<1 AND varyant_hata<1 AND cift_kayit=0`. Koşullardan biri yanlışsa `KALDI`.
6. `GİZLİ` gereken ürünün puanlanması veya P3/P4 sayfasının tedarik kaynağına çevrilmesi kritik hatadır ve oranlardan bağımsız `KALDI` üretir.

## 5. Kritik alan kümeleri

| Rol | Kritik alanlar |
|---|---|
| P0–P2 tedarik kaynağı | platform_product_id, canonical_url, title, currency, base_price, unit, seller_id, seller_name; sayfada varsa moq, price_tiers, variant_dimensions, sku_matrix |
| P3 referans | platform_product_id, canonical_url, title, currency, base_price, variant_dimensions, sku_matrix, rating/review_count; `supply_eligible=false` |
| P4 hedef pazar kıyası | platform_product_id, canonical_url, title, currency, base_price, pack/unit, variant_dimensions, rating/review_count; `supply_eligible=false` |

## 6. Alan eşleme sözlüğü

| Ortak alan | 1688 | Alibaba.com | Made-in-China | Global Sources | AliExpress | Taobao | Amazon | Temu |
|---|---|---|---|---|---|---|---|---|
| `platform_product_id` | offerId / 商品ID | product_id | Product page ID / model no. | Product ID / URL suffix | product_id | num_iid / item_id | ASIN | goods ID / URL g-...  |
| `canonical_url` | 商品链接 | Product URL | Product URL | Product URL | Item URL | item_url | Detail page URL | Product URL |
| `title` | 商品标题 | Product name / subject | Product Name | Product title | Product title | 宝贝标题 / title | item_name / Product title | Product title |
| `category_path` | 类目 / 面包屑 | Category | Category breadcrumb | Category breadcrumb (kısmi) | category_id / breadcrumb | cid / category_chain | browseClassifications | kanıtlanmadı |
| `currency` | 人民币 / CNY | fob_currency | FOB currency | US$ / currency | currency_code | CNY | offer.price.currency | localized currency |
| `base_price` | 价格 | FOB price / price range | FOB Price | Price | product_price / offer_sale_price | 价格 / promotion_price | offer.price.amount / Buy Box | localized price |
| `price_tiers` | 阶梯价 | bulk_discount_prices | Bulk-buy price (değişken) | Quantity price tiers | bulk_order + bulk_sale_price (opsiyonel) | YOK | YOK | kanıtlanmadı |
| `moq` | 起批量 | Min. order / min_order_quantity | Min. Order / MOQ | Minimum order / MOQ | YOK (perakende) | YOK | YOK | kanıtlanmadı |
| `unit` | 计量单位 | Unit | Piece / Set / ... | Pieces / Units / ... | product_unit | 件 / item unit (kısmi) | pack/item quantity (kısmi) | pack count (kanıtlanmadı) |
| `variant_dimensions` | 规格 / 颜色分类 | SKU attributes | Product options (kısmi) | Product attributes (kısmi) | SKU properties | 销售属性 / sku_props | variations / variation theme | options (kanıtlanmadı) |
| `sku_matrix` | SKU / 规格报价 | SKU + inventory + bulk price | kanıtlanmadı | YOK | SKU price + stock + image | SKU price + quantity | ASIN relationship + offers (kısmi) | kanıtlanmadı |
| `stock` | 库存 / 可售数量 | inventory | YOK | YOK | available_stock | item_quantity / sku quantity | fulfillmentAvailability / availability (kısmi) | Low stock / availability (kısmi) |
| `attributes` | 产品属性 | Product attributes | Product Attributes | Product Attribute / Specifications | Product properties / detail | 商品属性 / props | Catalog attributes | Product details (kanıtlanmadı) |
| `gallery_images` | 商品图片 | Product images | Product Images | Product images | Product images | item_imgs / small_images | images / image locators | Product images |
| `video` | 商品视频 | Product video | Watch Video | videoControl (değişken) | multimedia.videos | videos | VIDEOS (değişken) | kanıtlanmadı |
| `seller_id` | 会员ID / 公司ID | Supplier/company ID | Supplier profile ID | Supplier profile ID | store_id | seller_id | seller ID (offer bağlamı) | store ID (kanıtlanmadı) |
| `seller_name` | 公司名称 | Company name | Supplier name | Supplier name | store_name | shop_name / seller_nick | Sold by (değişken) | store name (kısmi) |
| `seller_location` | 所在地区 | Company/factory location | Province, China | Country / province | kanıtlanmadı | provcity | YOK | kanıtlanmadı |
| `seller_badges` | 实力商家 / 验厂标识 | Verified Supplier / specialty tags | Diamond Member / Audited Supplier | Verified Supplier / Premier Supplier | Store badges (değişken) | 店铺标识 (kanıtlanmadı) | Amazon badges (değişken) | kanıtlanmadı |
| `seller_scorecard` | 回头率 / 发货 / 响应 | rating / response / on-time / reorder | Rating / response time | Years / response time / company profile | store_info ratings | 店铺评分 (kanıtlanmadı) | seller feedback sayfası (kısmi) | kanıtlanmadı |
| `sales_30d` | 近30天成交 | YOK | YOK | YOK | YOK | 月销 (kanıtlanmadı) | YOK | kanıtlanmadı |
| `sales_total` | 成交件数 | Orders / transactions (değişken) | YOK | YOK | order_count | volume (kısmi) | YOK | sold count (kanıtlanmadı) |
| `rating` | 货描相符 / 商品评分 | Buyer rating | Rating (değişken) | YOK | avg_evaluation_rating | rate_info (kısmi) | star rating | rating (kanıtlanmadı) |
| `review_count` | 评价数 | Reviews | Reviews (değişken) | YOK | evaluation_count | 评价数 (kanıtlanmadı) | ratings count | reviews (kanıtlanmadı) |
| `listed_at` | 上架时间 | publish_time (kısmi) | Page year / kanıtlanmadı | YOK | YOK | list_time | Date First Available (değişken) | kanıtlanmadı |
| `packaging_text` | 包装信息 | packaging_desc | Transport Package | Packaging / shipping info (kısmi) | package fields (kısmi) | YOK | package quantity/dimensions (kısmi) | kanıtlanmadı |
| `units_per_carton` | 装箱数 | packaging_desc içinden | kanıtlanmadı | kanıtlanmadı | YOK | YOK | YOK | kanıtlanmadı |
| `gross_weight` | 毛重 | Package weight (değişken) | Gross Weight (değişken) | Weight (değişken) | gross_weight | 重量属性 (kanıtlanmadı) | item/package weight (ürün tipine bağlı) | product details (kanıtlanmadı) |
| `carton_dimensions` | 包装尺寸 | Package dimensions (değişken) | Package Size (değişken) | Dimensions per unit (kısmi) | package_length/width/height | YOK | package dimensions (ürün tipine bağlı) | kanıtlanmadı |
| `carton_cbm` | 由包装尺寸计算 | YOK | YOK | YOK | YOK (koli değil) | YOK | YOK (tedarik kolisi değil) | kanıtlanmadı |
| `custom_order` | 来样定制 / 加工定制 | Customization / OEM / ODM | Customization / OEM / ODM | OEM / ODM / customization (kısmi) | YOK | YOK | Personalize (değişken) | personalized item (kanıtlanmadı) |
| `lead_time` | 交期 / 发货时间 | delivery_time / lead time | Average Lead Time | Lead Time | delivery_time | 发货时间 (kısmi) | delivery estimate (değişken) | delivery estimate (kanıtlanmadı) |

## 7. Fikstür dosya sözleşmesi

```json
{
  "fixture_id": "FX-PLATFORM-001",
  "platform": "1688",
  "adapter_version": "semver",
  "captured_at": "ISO-8601",
  "locale": "zh-CN",
  "currency_context": "CNY",
  "page_type": "variant_product",
  "source_url_hash": "sha256",
  "sanitized_html_file": "relative/path.html",
  "sanitized_network_files": [],
  "expected_output_file": "relative/golden.json",
  "consent": "user_initiated_capture",
  "secrets_removed": true
}
```

Gerçek HTML/HAR dosyaları V3-E eklenti işinde toplanır; bu belge yalnız seçim, temizleme, altın çıktı ve kabul sözleşmesidir.
