# tedarikapp — Çalışma Protokolü

> Durum: v1.0 — bu belge projenin anayasasıdır, tüm taraflar buna uyar.

## 1. Roller

| Taraf | Rol | Sorumluluk |
|---|---|---|
| **Claude (bu sohbet)** | Proje Yöneticisi (PM) | Planlama, iş emirlerini yazma, Claude Code çıktılarını ve GitHub'daki kodu inceleme, kalite kontrol, belge güncellemelerini yönetme, sonraki adımı belirleme |
| **Bünyamin** | Ürün Sahibi + Koordinatör | Nihai karar ve onay makamı; iş emirlerini Claude Code'a iletir, çıktı raporlarını PM'e getirir, gerçek kullanım testlerini yapar (sunucu, 1688, firma tarafı) |
| **Claude Code** | Uygulayıcı Geliştirici | İş emrini birebir uygular, iş emri kapsamı DIŞINA ÇIKMAZ, çıktı raporu üretir, GitHub'a push eder |

## 2. Çalışma Döngüsü

```
PM iş emrini yazar (İŞ EMRİ #N)
        │
Bünyamin → Claude Code'a iletir
        │
Claude Code uygular → GitHub'a push → ÇIKTI RAPORU üretir
        │
Bünyamin raporu PM'e getirir
        │
PM inceler: GitHub'dan kodu kontrol eder + raporu değerlendirir
        │
   ┌────┴────┐
 KABUL     REVİZYON → düzeltme iş emri → döngü başa
   │
PM sonraki İŞ EMRİ #N+1'i yazar
```

Kurallar:
- Aynı anda **tek aktif iş emri** olur. Bitmeden yenisi açılmaz.
- Kapsam değişikliği istenirse iş emri ortasında değil, sonraki iş emrinde yapılır.
- Her iş emri en fazla bir oturumda bitecek büyüklükte tutulur (büyük işler bölünür).
- Karar (K#) numaralarını yalnızca PM atar. Ürün Sahibi'nin PM döngüsü dışındaki doğrudan talimatları geçerlidir; ancak yapılan iş PM'e raporlanır ve karar kaydına PM'in verdiği numarayla işlenir (çakışma önleme).

## 3. İş Emri Şablonu (PM → Claude Code)

```markdown
# İŞ EMRİ #N — <kısa başlık>
Faz: <Faz X> · Modül: <M?> · Dal: is-emri-N-<slug>

## Hedef
<Tek cümlede bu iş emri bitince ne çalışıyor olacak>

## Ön Koşul
<Okunacak belgeler (docs/...), bağımlı iş emirleri>

## Yapılacaklar
1. ...
2. ...

## Kapsam DIŞI
<Bu emirde kesinlikle dokunulmayacaklar>

## Kabul Kriterleri
- [ ] ...

## Test
<Çalıştırılacak testler / elle doğrulama adımları>

## Teslim
Commit + push (dal: is-emri-N-...), PR aç, ÇIKTI RAPORU üret.
```

## 4. Çıktı Raporu Şablonu (Claude Code → PM)

```markdown
# ÇIKTI RAPORU — İŞ EMRİ #N
Durum: TAMAMLANDI / KISMEN / ENGELLENDİ

## Yapılanlar
## Oluşan/Değişen Dosyalar
## Test Sonuçları
## Kabul Kriterleri Durumu (madde madde ✓/✗)
## Karşılaşılan Sorunlar ve Alınan Kararlar
## PR / Commit
```

## 5. GitHub Süreci

- Repo: `tilbehome/tedarikapp` (private).
- **Dallar:** `main` her zaman çalışır durumda tutulur. Her iş emri kendi dalında yapılır (`is-emri-N-slug`), PR açılır.
- **İnceleme:** PM, PR'ı GitHub üzerinden inceler (Claude'un GitHub erişimi vardır). Onay bu sohbette verilir; merge'ü Claude Code yapar.
- **Commit standardı:** `tip(kapsam): açıklama` — tipler: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`. Örn: `feat(export): xlsx görsel gömme`.
- **Sürümleme:** SemVer. Her faz sonu tag: Faz 1 → `v0.1.0`, Faz 2 → `v0.2.0`, Faz 3 → `v0.3.0`, Faz 4 → `v1.0.0` (üretim).
- **Belgeler kodla yaşar:** Bir iş emri bir kararı değiştiriyorsa ilgili `docs/*.md` güncellemesi aynı PR'a dahildir.

## 6. Kalite Çizgisi

- Kod standardı: PSR-12 (PHP), ESLint (React). Türkçe kullanıcı arayüzü, İngilizce kod/değişken adları.
- Hiçbir sır (DB şifresi, API token) repoya girmez — `.env` + `.env.example` düzeni.
- Her PR'da: kabul kriterleri karşılanmış, test adımları raporlanmış olmalı; aksi halde PM reddeder.
