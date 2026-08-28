from pathlib import Path
from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.colors import HexColor, Color, white
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.lib.utils import ImageReader


ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT.parent / "output" / "pdf" / "Tedarik-App-Marka-Kimligi-Rehberi.pdf"
PAGE = landscape(A4)
W, H = PAGE

INK = HexColor("#1F2530")
ORANGE = HexColor("#FF6B00")
ORANGE_DARK = HexColor("#E85F00")
AMBER = HexColor("#FFB000")
CORAL = HexColor("#FF4D3D")
WARM = HexColor("#FFFDF8")
CANVAS = HexColor("#FFF8F1")
MUTED = HexColor("#6B7280")
BORDER = HexColor("#E7D9CA")
SUCCESS = HexColor("#0E9F6E")
INFO = HexColor("#1479C9")
WARNING = HexColor("#D97706")
DANGER = HexColor("#D92D20")
PURPLE = HexColor("#7C3AED")

pdfmetrics.registerFont(TTFont("DV", "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"))
pdfmetrics.registerFont(TTFont("DVB", "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"))
pdfmetrics.registerFont(TTFont("DVM", "/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf"))

MARK = ImageReader(str(ROOT / "logo" / "tedarik-app-mark.png"))
LOGO = ImageReader(str(ROOT / "logo" / "tedarik-app-logo-horizontal.png"))
LOGO_DARK = ImageReader(str(ROOT / "logo" / "tedarik-app-logo-horizontal-dark.png"))
POPUP = ImageReader(str(ROOT / "ui-assets" / "extension-popup-header.png"))
DOC_HEADER = ImageReader(str(ROOT / "documents" / "tedarik-listesi-header.png"))
PROMO = ImageReader(str(ROOT / "chrome-web-store" / "small-promo-440x280.png"))


def round_rect(c, x, y, w, h, r=12, fill=WARM, stroke=None, sw=1):
    c.setLineWidth(sw)
    c.setFillColor(fill)
    if stroke:
        c.setStrokeColor(stroke)
        c.roundRect(x, y, w, h, r, fill=1, stroke=1)
    else:
        c.roundRect(x, y, w, h, r, fill=1, stroke=0)


def wrapped_lines(text, font, size, width):
    lines = []
    for paragraph in text.split("\n"):
        if not paragraph:
            lines.append("")
            continue
        words = paragraph.split()
        line = ""
        for word in words:
            test = word if not line else line + " " + word
            if pdfmetrics.stringWidth(test, font, size) <= width:
                line = test
            else:
                if line:
                    lines.append(line)
                line = word
        if line:
            lines.append(line)
    return lines


def text_box(c, x, y_top, width, text, size=12, leading=None, color=INK, font="DV", max_lines=None):
    leading = leading or size * 1.42
    lines = wrapped_lines(text, font, size, width)
    if max_lines:
        lines = lines[:max_lines]
    c.setFillColor(color)
    c.setFont(font, size)
    y = y_top
    for line in lines:
        c.drawString(x, y, line)
        y -= leading
    return y


def label(c, x, y, text, color=ORANGE, bg=HexColor("#FFE4CF")):
    width = pdfmetrics.stringWidth(text, "DVB", 8) + 18
    round_rect(c, x, y - 5, width, 20, 10, bg)
    c.setFillColor(color)
    c.setFont("DVB", 8)
    c.drawString(x + 9, y + 1, text)
    return width


def page_base(c, title, page_no, section):
    c.setFillColor(CANVAS)
    c.rect(0, 0, W, H, fill=1, stroke=0)
    c.drawImage(MARK, 34, H - 58, 34, 34, mask="auto")
    c.setFillColor(INK)
    c.setFont("DVB", 10)
    c.drawString(78, H - 42, "TEDARİK APP")
    c.setFillColor(MUTED)
    c.setFont("DV", 8)
    c.drawString(78, H - 54, section.upper())
    c.setFillColor(INK)
    c.setFont("DVB", 24)
    c.drawString(34, H - 98, title)
    c.setStrokeColor(BORDER)
    c.setLineWidth(1)
    c.line(34, 30, W - 34, 30)
    c.setFillColor(MUTED)
    c.setFont("DV", 8)
    c.drawString(34, 17, "Tedarik App Marka Kimliği - v1.0 - 2026-08-23")
    c.setFont("DVM", 8)
    c.drawRightString(W - 34, 17, f"{page_no:02d}")


def title_card(c, x, y, w, h, title, body, accent=ORANGE):
    round_rect(c, x, y, w, h, 14, WARM, BORDER)
    c.setFillColor(accent)
    c.roundRect(x, y, 7, h, 3.5, fill=1, stroke=0)
    c.setFillColor(INK)
    c.setFont("DVB", 13)
    c.drawString(x + 22, y + h - 30, title)
    text_box(c, x + 22, y + h - 52, w - 40, body, 9.5, 14, MUTED)


def page_cover(c):
    c.setFillColor(INK)
    c.rect(0, 0, W, H, fill=1, stroke=0)
    c.setFillColor(ORANGE)
    c.circle(25, -30, 190, fill=1, stroke=0)
    c.setFillColor(CORAL)
    c.circle(W - 20, H - 30, 180, fill=1, stroke=0)
    c.setFillColor(Color(1, 1, 1, alpha=.08))
    c.saveState()
    c.translate(120, H - 60)
    c.rotate(-22)
    c.rect(0, 0, 520, 54, fill=1, stroke=0)
    c.restoreState()
    c.setFillAlpha(1)
    c.drawImage(MARK, 70, 172, 240, 240, mask="auto")
    c.setFillColor(white)
    c.setFont("DVB", 36)
    c.drawString(360, 346, "Tedarik App")
    c.setFillColor(AMBER)
    c.setFont("DVB", 18)
    c.drawString(362, 306, "MARKA KİMLİĞİ VE TASARIM SİSTEMİ")
    c.setFillColor(HexColor("#F4D7C1"))
    text_box(c, 362, 260, 390, "Logo, renk, tipografi, panel, Chrome eklentisi, liste ve çok dilli çıktı standartları.", 13, 20, HexColor("#F4D7C1"))
    label(c, 362, 188, "V1.0", white, ORANGE)
    c.setFillColor(white)
    c.setFont("DVM", 9)
    c.drawString(362, 151, "23.08.2026")


def page_brand(c):
    page_base(c, "Marka özü", 2, "Strateji")
    title_card(c, 34, 316, 370, 150, "Marka vaadi", "Çin menşeli ürün araştırmasını dağınık sekmelerden çıkarıp doğrulanabilir, paylaşılabilir ve siparişe dönüşebilir tek bir akışa taşımak.", ORANGE)
    title_card(c, 426, 316, 382, 150, "Konumlandırma", "Ürün verisi toplayan Chrome eklentisi ile karar, liste ve sipariş yönetimi panelini birleştiren profesyonel tedarik çalışma alanı.", CORAL)
    attributes = [("GÜVENİLİR", ORANGE), ("SİSTEMLİ", INK), ("HIZLI", AMBER), ("ULUSLARARASI", CORAL), ("SADE", MUTED)]
    x = 34
    for name, color in attributes:
        w = label(c, x, 270, name, white, color)
        x += w + 10
    c.setFillColor(INK)
    c.setFont("DVB", 24)
    c.drawString(34, 205, "Akıllı Tedarik Yönetimi")
    c.setFillColor(MUTED)
    c.setFont("DV", 13)
    c.drawString(34, 174, "Ürünü keşfet, veriyi düzenle, listeyi paylaş, siparişi yönet.")
    round_rect(c, 520, 100, 288, 150, 16, HexColor("#FFE4CF"))
    c.setFillColor(ORANGE_DARK)
    c.setFont("DVB", 12)
    c.drawString(545, 214, "RESMÎ YAZIM")
    c.setFillColor(INK)
    c.setFont("DVB", 30)
    c.drawString(545, 164, "Tedarik App")
    c.setFillColor(MUTED)
    c.setFont("DVM", 10)
    c.drawString(545, 126, "teknik ad: tedarik-app")


def page_logo(c):
    page_base(c, "Logo sistemi", 3, "Görsel kimlik")
    round_rect(c, 34, 282, 774, 190, 16, WARM, BORDER)
    c.drawImage(LOGO, 58, 310, 690, 172.5, mask="auto", preserveAspectRatio=True, anchor="c")
    round_rect(c, 34, 70, 236, 180, 16, WARM, BORDER)
    c.drawImage(MARK, 74, 90, 156, 156, mask="auto")
    title_card(c, 292, 70, 248, 180, "İşaret", "T biçimli ok verinin aktarımını, açık kutu fiziksel ürünü, amber ve mercan kapaklar tedarik akışını anlatır.", ORANGE)
    title_card(c, 562, 70, 246, 180, "Kullanım sırası", "1. Yatay ana logo\n2. Koyu zemin logosu\n3. Dar alanda yalnız amblem\n4. Tek renk baskıda mono sürüm", CORAL)


def page_usage(c):
    page_base(c, "Logo kullanım kuralları", 4, "Görsel kimlik")
    items = [
        ("Güvenli alan", "Amblem genişliğinin en az yüzde 12'si kadar boşluk bırakın."),
        ("Minimum ölçü", "Dijitalde 24 px, basılı işlerde 8 mm altına inmeyin."),
        ("Zemin", "Açık zeminde ana; koyu zeminde ters logoyu kullanın."),
        ("Oran", "Logo en-boy oranını koruyun; esnetmeyin veya sıkıştırmayın."),
        ("Efekt", "Gölge, kontur, bevel, parlama veya farklı renk eklemeyin."),
        ("Küçük alan", "240 px'den dar alanda yazılı logo yerine amblemi kullanın."),
    ]
    for i, (head, body) in enumerate(items):
        col = i % 3
        row = i // 3
        title_card(c, 34 + col * 258, 298 - row * 190, 238, 166, head, body, [ORANGE, AMBER, CORAL][col])


def page_color(c):
    page_base(c, "Canlı turuncu renk sistemi", 5, "Görsel kimlik")
    swatches = [
        ("Sinyal Turuncusu", "#FF6B00", ORANGE, white),
        ("Amber", "#FFB000", AMBER, INK),
        ("Canlı Mercan", "#FF4D3D", CORAL, white),
        ("Kömür", "#1F2530", INK, white),
        ("Sıcak Beyaz", "#FFFDF8", WARM, INK),
    ]
    for i, (name, code, color, text) in enumerate(swatches):
        x = 34 + i * 154
        c.setFillColor(color)
        c.roundRect(x, 290, 138, 174, 14, fill=1, stroke=0)
        if color == WARM:
            c.setStrokeColor(BORDER)
            c.roundRect(x, 290, 138, 174, 14, fill=0, stroke=1)
        c.setFillColor(text)
        c.setFont("DVB", 9)
        c.drawString(x + 14, 332, name)
        c.setFont("DVM", 10)
        c.drawString(x + 14, 310, code)
    c.setFillColor(INK)
    c.setFont("DVB", 14)
    c.drawString(34, 242, "Kullanım oranı")
    c.setFillColor(WARM); c.roundRect(34, 188, 405, 34, 10, fill=1, stroke=0)
    c.setFillColor(INK); c.roundRect(439, 188, 170, 34, 0, fill=1, stroke=0)
    c.setFillColor(ORANGE); c.roundRect(609, 188, 105, 34, 0, fill=1, stroke=0)
    c.setFillColor(AMBER); c.roundRect(714, 188, 52, 34, 0, fill=1, stroke=0)
    c.setFillColor(CORAL); c.roundRect(766, 188, 42, 34, 10, fill=1, stroke=0)
    c.setFillColor(MUTED)
    c.setFont("DV", 9)
    c.drawString(34, 164, "Sıcak beyaz %52  |  Kömür %22  |  Turuncu %14  |  Amber %7  |  Mercan %5")
    text_box(c, 34, 126, 770, "Mavi ve yeşil marka rengi değildir. Yalnız bilgi ve başarı gibi evrensel sistem durumlarında sınırlı biçimde kullanılır.", 10, 15, MUTED)


def page_type(c):
    page_base(c, "Tipografi ve sayısal veri", 6, "Görsel kimlik")
    round_rect(c, 34, 260, 500, 205, 16, WARM, BORDER)
    c.setFillColor(INK); c.setFont("DVB", 34); c.drawString(58, 406, "Inter / Noto Sans")
    c.setFillColor(ORANGE); c.setFont("DVB", 20); c.drawString(58, 360, "Başlık 700 - Eylem odaklı")
    c.setFillColor(INK); c.setFont("DV", 14); c.drawString(58, 324, "Gövde 400-500 - Açık, kısa ve okunabilir")
    c.setFillColor(MUTED); c.setFont("DV", 11); c.drawString(58, 288, "Çince alanlar için Noto Sans SC kullanılmalıdır.")
    round_rect(c, 558, 260, 250, 205, 16, INK)
    c.setFillColor(AMBER); c.setFont("DVB", 10); c.drawString(582, 421, "SAYISAL VERİ")
    c.setFillColor(white); c.setFont("DVM", 25); c.drawRightString(782, 374, "¥ 18.60")
    c.setFont("DVM", 18); c.drawRightString(782, 337, "240 adet")
    c.drawRightString(782, 300, "2026-08-23")
    title_card(c, 34, 70, 238, 160, "Ölçek", "Başlık 30 px\nSayfa başlığı 24 px\nGövde 14-16 px\nYardımcı metin 12 px", ORANGE)
    title_card(c, 292, 70, 238, 160, "Hizalama", "Metin sola; sayılar, fiyatlar, miktarlar ve yüzdeler sağa hizalanır.", AMBER)
    title_card(c, 550, 70, 258, 160, "Monospace", "SKU, sipariş numarası, liste kimliği, EAN ve teknik hata kodlarında kullanılır.", CORAL)


def page_ui(c):
    page_base(c, "Panel temelleri", 7, "Ürün arayüzü")
    metrics = [("64 px", "Üst bar"), ("264 px", "Sol menü"), ("40 px", "Kontrol"), ("52 px", "Tablo satırı"), ("14 px", "Kart yarıçapı")]
    for i, (value, caption) in enumerate(metrics):
        x = 34 + i * 154
        round_rect(c, x, 365, 138, 100, 14, WARM, BORDER)
        c.setFillColor(ORANGE if i in (0, 2) else INK); c.setFont("DVB", 20); c.drawCentredString(x + 69, 418, value)
        c.setFillColor(MUTED); c.setFont("DV", 9); c.drawCentredString(x + 69, 391, caption)
    title_card(c, 34, 160, 238, 170, "4 px ritim", "Tüm boşluk ve ölçüler 4 px tabanlı sistemden türetilir. En sık kullanılan aralıklar 8, 12, 16, 24 ve 32 px'dir.", ORANGE)
    title_card(c, 292, 160, 238, 170, "Tek ana eylem", "Her ekranın bir birincil turuncu eylemi vardır. İkincil işlemler beyaz veya metin düğmesidir.", AMBER)
    title_card(c, 550, 160, 258, 170, "Erişilebilir odak", "Klavye odağı görünürdür. Dokunma hedefi minimum 44 x 44 px, normal metin kontrastı en az 4.5:1'dir.", CORAL)


def page_components(c):
    page_base(c, "Bileşen ve durum standartları", 8, "Ürün arayüzü")
    c.setFont("DVB", 9)
    round_rect(c, 34, 408, 150, 44, 10, ORANGE); c.setFillColor(white); c.drawCentredString(109, 424, "Ürün Ekle")
    round_rect(c, 196, 408, 150, 44, 10, WARM, BORDER); c.setFillColor(INK); c.drawCentredString(271, 424, "Dışa Aktar")
    round_rect(c, 358, 408, 150, 44, 10, HexColor("#FFF0EF"), DANGER); c.setFillColor(DANGER); c.drawCentredString(433, 424, "Kalıcı Sil")
    c.setFillColor(MUTED); c.setFont("DV", 8); c.drawString(34, 386, "Birincil"); c.drawString(196, 386, "İkincil"); c.drawString(358, 386, "Tehlikeli")
    statuses = [
        ("Gelen", INFO, HexColor("#EAF5FD")), ("İnceleniyor", PURPLE, HexColor("#F3EFFF")),
        ("Onaylandı", SUCCESS, HexColor("#E8F8F2")), ("Teklif Alındı", WARNING, HexColor("#FFF4DF")),
        ("Sipariş Verildi", INK, HexColor("#E8EEF7")), ("Üretimde", HexColor("#8B5A00"), HexColor("#FFF5CC")),
        ("Yolda", HexColor("#087D96"), HexColor("#E6F7FA")), ("Gümrükte", HexColor("#C2410C"), HexColor("#FFF0E8")),
        ("Depoda", HexColor("#047A68"), HexColor("#E5F8F4")), ("Tamamlandı", HexColor("#067647"), HexColor("#E9F7EF")),
    ]
    for i, (name, fg, bg) in enumerate(statuses):
        col = i % 5; row = i // 5
        x = 34 + col * 154; y = 296 - row * 60
        round_rect(c, x, y, 138, 36, 18, bg)
        c.setFillColor(fg); c.circle(x + 18, y + 18, 3.5, fill=1, stroke=0)
        c.setFont("DVB", 8); c.drawString(x + 30, y + 14, name)
    title_card(c, 34, 74, 774, 110, "Durum ilkesi", "Durum yalnız renkle anlatılmaz. Nokta veya ikon, metin etiketi ve gerektiğinde zaman bilgisi birlikte kullanılır. Reddedildi veya hata durumu, normal iş akışı durumundan ayrı ele alınır.", CORAL)


def page_table(c):
    page_base(c, "Liste ve tablo sistemi", 9, "Ürün arayüzü")
    x, y, tw = 34, 168, 774
    round_rect(c, x, y, tw, 296, 14, WARM, BORDER)
    c.setFillColor(INK); c.roundRect(x, y + 248, tw, 48, 14, fill=1, stroke=0); c.rect(x, y + 248, tw, 24, fill=1, stroke=0)
    headers = [("Ürün", 26), ("Kaynak", 360), ("Fiyat", 500), ("MOQ", 610), ("Durum", 690)]
    c.setFont("DVB", 8); c.setFillColor(white)
    for text, xx in headers: c.drawString(x + xx, y + 266, text)
    rows = [
        ("Cam yağlık seti", "1688", "¥ 18.60", "120", "İnceleniyor"),
        ("Silikon mutfak seti", "Alibaba", "$ 4.80", "240", "Onaylandı"),
        ("Vakumlu saklama kabı", "1688", "¥ 11.20", "300", "Teklif Alındı"),
    ]
    for i, row in enumerate(rows):
        ry = y + 188 - i * 62
        if i % 2: c.setFillColor(CANVAS); c.rect(x + 1, ry, tw - 2, 62, fill=1, stroke=0)
        c.setStrokeColor(BORDER); c.line(x, ry, x + tw, ry)
        c.setFillColor(HexColor("#FFE4CF")); c.roundRect(x + 20, ry + 12, 38, 38, 8, fill=1, stroke=0)
        c.setFillColor(INK); c.setFont("DVB", 9); c.drawString(x + 70, ry + 34, row[0])
        c.setFillColor(MUTED); c.setFont("DV", 8); c.drawString(x + 70, ry + 18, "Varyant bilgisi alt satırda")
        c.setFillColor(INK); c.setFont("DV", 9); c.drawString(x + 360, ry + 26, row[1])
        c.setFont("DVM", 9); c.drawRightString(x + 575, ry + 26, row[2]); c.drawRightString(x + 660, ry + 26, row[3])
        fg, bg = (PURPLE, HexColor("#F3EFFF")) if i == 0 else ((SUCCESS, HexColor("#E8F8F2")) if i == 1 else (WARNING, HexColor("#FFF4DF")))
        round_rect(c, x + 680, ry + 17, 82, 28, 14, bg); c.setFillColor(fg); c.setFont("DVB", 6.8); c.drawCentredString(x + 721, ry + 27, row[4])
    text_box(c, 34, 135, 774, "Ürün, kaynak, fiyat, MOQ, durum ve son güncelleme ilk bakışta görünür. İnce sütun çizgileri korunur; sayısal alanlar sağa hizalanır.", 10, 15, MUTED)


def page_extension(c):
    page_base(c, "Chrome eklentisi ve mağaza", 10, "Dağıtım")
    round_rect(c, 34, 250, 400, 220, 16, WARM, BORDER)
    c.drawImage(POPUP, 34, 382, 400, 96, mask="auto")
    c.setFillColor(ORANGE); c.roundRect(58, 286, 352, 48, 12, fill=1, stroke=0)
    c.setFillColor(white); c.setFont("DVB", 10); c.drawCentredString(234, 304, "Ürünü Tedarik App'e Aktar")
    c.setFillColor(MUTED); c.setFont("DV", 8); c.drawString(58, 356, "Aktif site: 1688.com  |  Veri algılandı")
    round_rect(c, 460, 250, 348, 220, 16, INK)
    c.drawImage(PROMO, 484, 294, 300, 191, mask="auto", preserveAspectRatio=True)
    c.setFillColor(white); c.setFont("DVB", 10); c.drawString(484, 272, "Small promo: 440 x 280")
    title_card(c, 34, 72, 238, 150, "Store ikonu", "128 x 128 PNG. Kare amblem yaklaşık 96 x 96; her yönde yaklaşık 16 px şeffaf boşluk.", ORANGE)
    title_card(c, 292, 72, 238, 150, "Ekran görüntüsü", "En az 1, en fazla 5 gerçek ekran. 1280 x 800 tercih edilir; tam taşma ve kare köşe.", AMBER)
    title_card(c, 550, 72, 258, 150, "İşlem akışı", "Algılanıyor > okunuyor > görseller hazırlanıyor > panele aktarılıyor > tamamlandı.", CORAL)


def page_documents(c):
    page_base(c, "Liste, Excel ve PDF çıktıları", 11, "Kurumsal çıktı")
    round_rect(c, 34, 292, 774, 172, 16, WARM, BORDER)
    c.drawImage(DOC_HEADER, 54, 319, 734, 110, mask="auto", preserveAspectRatio=True)
    title_card(c, 34, 82, 238, 170, "Başlık", "Logo, liste adı, liste numarası, ISO tarih, para birimi ve dil birlikte görünür.", ORANGE)
    title_card(c, 292, 82, 238, 170, "Gizlilik", "Dahili maliyet, kâr marjı ve özel notlar tedarikçi sürümünde varsayılan olarak gizlidir.", AMBER)
    title_card(c, 550, 82, 258, 170, "Çok dillilik", "Türkçe > English > ZH-CN sırası. Çinli firmalara giden metin kısa, açık ve deyimsizdir.", CORAL)


def page_voice(c):
    page_base(c, "Marka sesi, erişilebilirlik ve teslim", 12, "Yönetişim")
    title_card(c, 34, 300, 238, 164, "Marka sesi", "Hızlı ama aceleci değil. Kurumsal ama soğuk değil. Teknik ama anlaşılır. Kesin olmayan veri açıkça etiketlenir.", ORANGE)
    title_card(c, 292, 300, 238, 164, "Hata metni", "Ne başarısız oldu, kullanıcı ne yapmalı ve teknik ayrıntı nasıl kopyalanır: üçü birlikte sunulur.", CORAL)
    title_card(c, 550, 300, 258, 164, "Erişilebilirlik", "4.5:1 kontrast, görünür odak, minimum 44 x 44 px hedef, renk yanında ikon ve etiket, azaltılmış hareket.", AMBER)
    c.setFillColor(INK); c.setFont("DVB", 14); c.drawString(34, 250, "Geliştiriciye teslim edilen kaynaklar")
    files = [
        "SVG + PNG logo ailesi ve mono sürümler", "Chrome ikonları, favicon.ico ve PWA ikonları",
        "CSS / JSON tasarım tokenları", "Tailwind uyumlu tema ön ayarı",
        "10 durumlu TR / EN / ZH eşleme dosyası", "Liste başlık, kapak, footer ve filigran şablonları",
        "Chrome Web Store promo varlıkları", "Panel bileşen demo HTML'i ve uygulama kontrol listesi",
    ]
    for i, item in enumerate(files):
        col = i % 2; row = i // 2
        x = 34 + col * 390; y = 215 - row * 38
        c.setFillColor(ORANGE); c.circle(x + 5, y + 3, 4, fill=1, stroke=0)
        c.setFillColor(INK); c.setFont("DV", 9); c.drawString(x + 18, y, item)


def build():
    OUT.parent.mkdir(parents=True, exist_ok=True)
    c = canvas.Canvas(str(OUT), pagesize=PAGE, pageCompression=1)
    c.setTitle("Tedarik App Marka Kimliği ve Tasarım Sistemi")
    c.setAuthor("Tedarik App")
    pages = [page_cover, page_brand, page_logo, page_usage, page_color, page_type, page_ui, page_components, page_table, page_extension, page_documents, page_voice]
    for fn in pages:
        fn(c)
        c.showPage()
    c.save()
    print(OUT)


if __name__ == "__main__":
    build()
