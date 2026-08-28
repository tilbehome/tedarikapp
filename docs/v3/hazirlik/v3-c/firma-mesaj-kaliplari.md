# TedarikApp Görev #7A — Firma WhatsApp Mesaj Kalıpları

**Kullanım:** Çinli ithalatçı/tedarikçi firmalara liste ve teklif süreci iletişimi  
**Diller:** Türkçe (TR), English (EN), 中文 (ZH)  
**Kanal kuralı:** Paylaşım bağlantısı ile 6 haneli erişim anahtarı aynı mesajda gönderilmez.  
**Yer tutucu sözleşmesi:** `{liste_adi}`, `{link}`, `{anahtar}`, `{gun}`, `{firma_adi}`

> Her kalıpta kullanılan yer tutucu kümesi üç dilde birebir aynıdır. Köşeli açıklamalar gönderilmez; yalnız ilgili dildeki mesaj kopyalanır.

---

## 1. Liste iletimi — bağlantı mesajı

**Yer tutucular:** `{firma_adi}`, `{liste_adi}`, `{link}`  
**Güvenlik:** Bu mesaj **anahtar içermez**.

### TR

Merhaba {firma_adi}, incelemeniz için “{liste_adi}” ürün listesini paylaşıyoruz:
{link}

6 haneli erişim anahtarını ayrı bir mesajla gönderiyorum. İncelemeniz sonrasında fiyat ve termin bilgilerinizi rica ederiz. Teşekkürler.

### EN

Hello {firma_adi}, we are sharing the “{liste_adi}” product list for your review:
{link}

I will send the 6-digit access key in a separate message. Please provide your prices and lead times after reviewing the list. Thank you.

### ZH

尊敬的 {firma_adi}，您好。现将“{liste_adi}”产品清单发送给贵司审核：
{link}

6位访问验证码将另行发送。烦请审核后提供价格及交期，谢谢。

---

## 2. Anahtar mesajı — ayrı kanal/mesaj

**Yer tutucular:** `{anahtar}`  
**Güvenlik:** Bu mesaj bağlantı içermez.

### TR

Erişim anahtarı: {anahtar}

Daha önce paylaştığım bağlantıyı açın ve bu 6 haneli anahtarı girin.

### EN

Access key: {anahtar}

Open the link shared earlier and enter this 6-digit key.

### ZH

访问验证码：{anahtar}

请打开此前发送的链接，并输入以上6位验证码。

---

## 3. İlk kullanım mini kılavuzu

**Yer tutucular:** Yok

### TR

Kısa kullanım:

1. Paylaşılan liste bağlantısına tıklayın.
2. Ayrı mesajla iletilen 6 haneli anahtarı girin.
3. Ürünleri, varyantları ve talep edilen miktarları kontrol edin.
4. Türkiye KDV'si dâhil DDP birim fiyatı ve termin bilgisini girip yanıtınızı gönderin.

### EN

Quick guide:

1. Open the shared list link.
2. Enter the 6-digit key sent in a separate message.
3. Review the products, variants, and requested quantities.
4. Enter the DDP unit price including Turkish VAT and the lead time, then submit your response.

### ZH

操作说明：

1. 打开已发送的产品清单链接。
2. 输入另行发送的6位访问验证码。
3. 核对产品、规格及需求数量。
4. 填写含土耳其增值税的DDP单价及交期，然后提交回复。

---

## 4. Nazik hatırlatma

**Yer tutucular:** `{firma_adi}`, `{liste_adi}`, `{gun}`

### TR

Merhaba {firma_adi}, “{liste_adi}” listesi için {gun} gündür yanıtınızı bekliyoruz. Uygun olduğunuzda fiyat ve termin bilgilerini paylaşmanızı rica ederiz. Ek bilgiye ihtiyacınız varsa memnuniyetle yardımcı oluruz.

### EN

Hello {firma_adi}, we have been awaiting your response for the “{liste_adi}” list for {gun} days. Please share the pricing and lead-time information when convenient. We will be happy to assist if you need any additional details.

### ZH

尊敬的 {firma_adi}，您好。“{liste_adi}”清单已等待贵司回复 {gun} 天。烦请在方便时提供价格及交期信息；如需补充资料，请随时告知，谢谢。

---

## 5. Fiyat geçerliliği uyarısı

**Yer tutucular:** `{liste_adi}`, `{gun}`

### TR

Bilgilendirme: “{liste_adi}” listesindeki fiyatların geçerliliği {gun} gün içinde doluyor. Değerlendirme ve gerekiyorsa güncelleme için teyidinizi rica ederiz.

### EN

Notice: The prices in the “{liste_adi}” list will expire within {gun} days. Please confirm them for evaluation or provide updated prices if required.

### ZH

温馨提示：“{liste_adi}”清单中的价格有效期将在 {gun} 天内届满。烦请确认现有报价；如需调整，请提供最新价格。

---

## 6. Teklif alındı — teşekkür

**Yer tutucular:** `{firma_adi}`, `{liste_adi}`

### TR

Merhaba {firma_adi}, “{liste_adi}” için teklifinizi aldık. Teşekkür ederiz. Fiyat, MOQ, termin ve koli bilgilerini değerlendirme sürecine aldık; sonuçlandığında size bilgi vereceğiz.

### EN

Hello {firma_adi}, we have received your quotation for “{liste_adi}.” Thank you. We are now evaluating the prices, MOQ, lead times, and carton information and will update you once the review is complete.

### ZH

尊敬的 {firma_adi}，您好。贵司关于“{liste_adi}”的报价已收悉，谢谢。我们已进入价格、起订量、交期及装箱信息的评估流程，审核完成后将及时反馈。

---

## 7. Onay / sipariş kararı bildirimi

**Yer tutucular:** `{firma_adi}`, `{liste_adi}`

### TR

Merhaba {firma_adi}, “{liste_adi}” için sipariş kararı onaylandı. Sonraki adım olarak, listedeki onaylı ürün, varyant, miktar ve fiyatlara göre sipariş teyit bilgilerini hazırlamanızı rica ederiz. Resmî sipariş detaylarını ayrıca ileteceğiz.

### EN

Hello {firma_adi}, the purchase decision for “{liste_adi}” has been approved. As the next step, please prepare the order-confirmation details based on the approved products, variants, quantities, and prices in the list. We will send the formal order details separately.

### ZH

尊敬的 {firma_adi}，您好。“{liste_adi}”的采购决定已获批准。下一步，请根据清单中已确认的产品、规格、数量和价格准备订单确认资料；正式采购订单信息将另行发送。

---

## 8. Revizyon ricası

**Yer tutucular:** `{liste_adi}`

### TR

“{liste_adi}” içinde işaretlediğimiz ürünler için güncel fiyat rica ediyoruz. Lütfen Türkiye KDV'si dâhil DDP birim fiyatı ile birlikte MOQ, termin ve varsa kademeli fiyatları da güncelleyiniz. Teşekkürler.

### EN

Please provide updated prices for the products marked in “{liste_adi}.” Kindly update the DDP unit price including Turkish VAT, together with the MOQ, lead time, and any tiered pricing. Thank you.

### ZH

烦请对“{liste_adi}”中已标注的产品提供最新报价，并同步更新含土耳其增值税的DDP单价、起订量、交期及阶梯价格（如有）。谢谢。

---

## 9. Anahtar yenilendi bildirimi

**Yer tutucular:** `{liste_adi}`, `{anahtar}`  
**Güvenlik:** Yenilenen anahtar yalnız bu ayrı anahtar mesajında yer alır; bağlantı tekrar eklenmez.

### TR

“{liste_adi}” için önceki erişim anahtarı artık geçersizdir.

Yeni 6 haneli anahtar: {anahtar}

Daha önce gönderilen bağlantıyı açıp yeni anahtarı giriniz.

### EN

The previous access key for “{liste_adi}” is no longer valid.

New 6-digit key: {anahtar}

Open the link sent earlier and enter the new key.

### ZH

“{liste_adi}”原访问验证码已失效。

新的6位验证码：{anahtar}

请打开此前发送的链接，并输入新验证码。

---

## 10. Kısa teşekkür / kapanış

**Yer tutucular:** `{firma_adi}`

### TR

Teşekkür ederiz {firma_adi}. İş birliğiniz ve hızlı desteğiniz için memnuniyet duyuyoruz. Yeni gelişmelerde tekrar iletişime geçeceğiz. İyi çalışmalar dileriz.

### EN

Thank you, {firma_adi}. We appreciate your cooperation and prompt support. We will contact you again when there is an update. Best regards.

### ZH

感谢 {firma_adi} 的配合与及时支持。如有最新进展，我们将再次与贵司联系。祝商祺！

---

## Gönderim öncesi hızlı kontrol

- Liste bağlantısı ve 6 haneli anahtar ayrı mesajlarda mı?
- Mesajda yalnız alıcı için gerekli dil mi bırakıldı?
- `{firma_adi}` ve `{liste_adi}` doğru mu?
- `{gun}` sayı olarak dolduruldu mu?
- `{anahtar}` tam 6 haneli mi?
- Mesajda şirket içi maliyet, hedef satış fiyatı, kâr, iç not veya erişim tokenı bulunmadığı kontrol edildi mi?
