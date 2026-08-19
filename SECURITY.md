# Güvenlik Politikası

## Desteklenen sürümler

Yalnız `main` dalındaki son release desteklenir (tek kurulum, tek işletmeci — docs/08 K9).

## Güvenlik açığı bildirimi

Bu depo tek işletmecili özel bir üründür. Olası bir güvenlik açığını **GitHub Security
Advisories** üzerinden (Security > Report a vulnerability) veya depo sahibine özel
kanaldan bildirin — herkese açık issue AÇMAYIN.

Bildirimde: etkilenen uç/bileşen, yeniden üretme adımları ve etki değerlendirmesi.
Hedef ilk yanıt süresi: 72 saat.

## Güvenlik modeli (özet — ayrıntı docs/08 karar kayıtlarında)

- Kimlik: Argon2id + opsiyonel TOTP 2FA (K45); oturum DB'de (K44); artan bekleme + IP kilidi
- Dışa açık tek yüzey paylaşım sayfasıdır: 256-bit token, DB'de SHA-256, sabit 404,
  hız sınırı, tam escape (K51)
- SQL yalnız prepared statements; sırlar yalnız dosya yapılandırmasında (config.php/.env);
  APP_KEY ve türevleri asla loglanmaz
- Yedekler AES-256-GCM şifreli ve web'den erişilemez (İE#10.5)
- CI: gitleaks sır taraması + composer/npm audit her PR'da
