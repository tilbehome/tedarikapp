# Görev 35 Teslim Raporu

## Sonuç

K105 için karar vermeyen mikro etkileşim envanteri oluşturuldu. Zorunlu 16 kategori korunmuş, kapsam boşluklarını kapatmak için 6 ek kategori eklenmiştir: işbirliği/eşzamanlılık, keşif/yardım, içe aktarma/veri bütünlüğü, oturum/yetki/güven, yerelleştirme/veri sunumu ve uyarlanabilir yüzey/giriş.

## Sayısal döküm

| Ölçü | Sonuç |
|---|---:|
| Kategori | 22 |
| Tür | 51 |
| Somut örüntü | 296 |
| B2B öncelik 1 | 235 |
| B2B öncelik 2 | 52 |
| B2B öncelik 3 | 9 |
| Resmi kaynak | 64 |
| JSON örüntü kaydı | 296 |

## Kategori kapsama dökümü

- KAT-01 — Kayıt ve satır eylemleri: 17 örüntü
- KAT-02 — Alan düzeyi: 23 örüntü
- KAT-03 — Tablo ve liste: 27 örüntü
- KAT-04 — Seçim ve toplu işlem: 11 örüntü
- KAT-05 — Gezinme ve komut: 14 örüntü
- KAT-06 — Geri bildirim ve durum: 23 örüntü
- KAT-07 — Yıkıcı eylem güvenliği: 11 örüntü
- KAT-08 — Arama: 12 örüntü
- KAT-09 — Görünüm modları: 10 örüntü
- KAT-10 — Sürükle-bırak: 13 örüntü
- KAT-11 — Çıkış ve paylaşım: 12 örüntü
- KAT-12 — Form, modal ve çekmece: 15 örüntü
- KAT-13 — Mikro animasyon ve hareket: 11 örüntü
- KAT-14 — Erişilebilirlik mikro davranışları: 15 örüntü
- KAT-15 — Bildirim ve arka plan işleri: 12 örüntü
- KAT-16 — Kişiselleştirme ve hafıza: 11 örüntü
- KAT-17 — İşbirliği ve eşzamanlılık: 11 örüntü
- KAT-18 — Keşif, yardım ve işe alıştırma: 8 örüntü
- KAT-19 — İçe aktarma, pano ve veri bütünlüğü: 11 örüntü
- KAT-20 — Oturum, yetki ve güven: 9 örüntü
- KAT-21 — Yerelleştirme ve veri sunumu: 10 örüntü
- KAT-22 — Uyarlanabilir yüzey ve giriş: 10 örüntü

## Kendi doğrulama çıktıları

| Kontrol | Sonuç |
|---|---|
| JSON ayrıştırma | BAŞARILI |
| ME-001..ME-296 kesintisiz | BAŞARILI |
| Yinelenen ME kimliği | 0 |
| Zorunlu yedi alanı boş örüntü | 0 |
| Öğe tipi boş örüntü | 0 |
| Emsal boş örüntü | 0 |
| Zorunlu 16 kategori | 16/16 |
| Ek kategori | 6 |
| Örüntü alt sınırı | 296/150 |
| Kaynak alt sınırı | 64/30 |
| Markdown kod çiti | 0 |

Doğrulama durumu: **BAŞARILI**.

## Dosya izlenebilirliği

| İstek | Dosya | Karşılık |
|---|---|---|
| A — hiyerarşik taksonomi ve yedi alan | `mikro-etkilesim-taksonomisi.md` | 22 kategori, 51 tür, 296 Katman-3 örüntüsü |
| B — makine okunur katalog | `mikro-etkilesim-katalogu.json` | Aynı tekil envanterden üretilen kesintisiz ME kimlikleri |
| C — sekiz emsal | `emsal-analizi.md` | Linear, Notion, Airtable, Stripe, Figma, Gmail, Shopify, monday.com/ClickUp |
| D — klavye standardı | `klavye-kisayol-standardi.md` | Yakınsamalar, kip kuralları, TR riskleri, öneri ailesi ve test kapıları |
| E — sayımlar, kaynaklar, belirsizlikler | `35-TESLIM-RAPORU.md` | Bu dosya |

## Kaynak listesi

Erişim tarihi: 2026-09-01. Ürün emsallerinde resmi yardım belgeleri; davranış ilkelerinde resmi tasarım sistemi ve standart kuruluşu belgeleri kullanılmıştır.

1. **S01 — Linear — Select issues:** https://linear.app/docs/select-issues
2. **S02 — Linear — Search:** https://linear.app/docs/search
3. **S03 — Linear — Editor:** https://linear.app/docs/editor
4. **S04 — Linear — Board layout:** https://linear.app/docs/board-layout
5. **S05 — Linear — Create issues:** https://linear.app/docs/creating-issues
6. **S06 — Linear — Inbox:** https://linear.app/docs/inbox
7. **S07 — Linear — Favorites:** https://linear.app/docs/favorites
8. **S08 — Notion — Keyboard shortcuts:** https://www.notion.com/help/keyboard-shortcuts
9. **S09 — Notion — Navigate with the sidebar:** https://www.notion.com/help/navigate-with-the-sidebar
10. **S10 — Notion — Slash commands:** https://www.notion.com/help/guides/using-slash-commands
11. **S11 — Notion — Writing and editing basics:** https://www.notion.com/help/writing-and-editing-basics
12. **S12 — Airtable — Keyboard shortcuts:** https://support.airtable.com/articles/7980233311-airtable-keyboard-shortcuts
13. **S13 — Airtable — Miscellaneous extensions:** https://support.airtable.com/articles/1258690122-miscellaneous-airtable-extensions
14. **S14 — Stripe — Dashboard basics:** https://docs.stripe.com/dashboard/basics
15. **S15 — Figma — Cursor chat:** https://help.figma.com/hc/en-us/articles/4403130802199-Use-cursor-chat-in-Figma-Design
16. **S16 — Figma — Guide to FigJam:** https://help.figma.com/hc/en-us/articles/1500004362321-Guide-to-FigJam
17. **S17 — Figma — FigJam with a screen reader:** https://help.figma.com/hc/en-us/articles/14477051168791-Use-FigJam-with-a-screen-reader
18. **S18 — Gmail — Keyboard shortcuts:** https://support.google.com/mail/answer/6594?hl=en-GB
19. **S19 — Gmail — Toolbar actions:** https://support.google.com/mail/answer/2473038?hl=en-GB
20. **S20 — Gmail — Smart Compose:** https://support.google.com/mail/answer/9116836?hl=en-GB
21. **S21 — Shopify Admin — Bulk editing:** https://help.shopify.com/en/manual/shopify-admin/productivity-tools/bulk-editing
22. **S22 — Shopify Admin — Keyboard shortcuts:** https://help.shopify.com/en/manual/shopify-admin/productivity-tools/keyboard-shortcuts
23. **S23 — Shopify Polaris — Index table:** https://polaris.shopify.com/components/tables/index-table
24. **S24 — monday.com — Shortcuts:** https://support.monday.com/hc/en-us/articles/115005339905-monday-com-Shortcuts
25. **S25 — ClickUp — Keyboard shortcuts:** https://help.clickup.com/hc/en-us/articles/6309030550167-Use-keyboard-shortcuts
26. **S26 — ClickUp — Bulk Action Toolbar:** https://help.clickup.com/hc/en-us/articles/6309768265495-Manage-tasks-with-the-Bulk-Action-Toolbar
27. **S27 — Material Design 3 — Snackbar:** https://m3.material.io/components/snackbar
28. **S28 — Material Design 3 — Progress indicators:** https://m3.material.io/components/progress-indicators/overview
29. **S29 — Material Design 3 — Motion transitions:** https://m3.material.io/styles/motion/transitions
30. **S30 — Material Design 3 — Menus:** https://m3.material.io/components/menus/overview
31. **S31 — Apple HIG — Drag and drop:** https://developer.apple.com/design/human-interface-guidelines/drag-and-drop
32. **S32 — Apple HIG — Feedback:** https://developer.apple.com/design/human-interface-guidelines/feedback
33. **S33 — Apple HIG — Motion:** https://developer.apple.com/design/human-interface-guidelines/motion
34. **S34 — Apple HIG — Accessibility:** https://developer.apple.com/design/human-interface-guidelines/accessibility
35. **S35 — Atlassian Design System — Components:** https://atlassian.design/components
36. **S36 — Atlassian Design System — Inline edit:** https://atlassian.design/components/inline-edit
37. **S37 — Atlassian Design System — Dynamic table:** https://atlassian.design/components/dynamic-table
38. **S38 — Atlassian Design System — Flag:** https://atlassian.design/components/flag
39. **S39 — Atlassian Design System — Tooltip:** https://atlassian.design/components/tooltip
40. **S40 — Atlassian Design System — Motion:** https://atlassian.design/foundations/motion
41. **S41 — Atlassian Design System — Accessibility:** https://atlassian.design/foundations/accessibility
42. **S42 — Radix Primitives — Dialog:** https://www.radix-ui.com/primitives/docs/components/dialog
43. **S43 — Radix Primitives — Toast:** https://www.radix-ui.com/primitives/docs/components/toast
44. **S44 — Radix Primitives — Popover:** https://www.radix-ui.com/primitives/docs/components/popover
45. **S45 — Radix Primitives — Context Menu:** https://www.radix-ui.com/primitives/docs/components/context-menu
46. **S46 — WAI-ARIA APG — Patterns:** https://www.w3.org/WAI/ARIA/apg/patterns/
47. **S47 — W3C — Understanding WCAG 2.2:** https://www.w3.org/WAI/WCAG22/Understanding/
48. **S48 — W3C — Focus Appearance:** https://www.w3.org/WAI/WCAG22/Understanding/focus-appearance.html
49. **S49 — W3C — Content on Hover or Focus:** https://www.w3.org/WAI/WCAG22/Understanding/content-on-hover-or-focus.html
50. **S50 — Carbon — Data table:** https://carbondesignsystem.com/components/data-table/usage/
51. **S51 — Carbon — Pagination:** https://carbondesignsystem.com/components/pagination/usage/
52. **S52 — Carbon — Loading:** https://preview.carbondesignsystem.com/building-blocks/core/patterns/loading
53. **S53 — MUI X — Row selection:** https://mui.com/x/react-data-grid/row-selection/
54. **S54 — AG Grid — Clipboard:** https://www.ag-grid.com/javascript-data-grid/clipboard/
55. **S55 — AG Grid — Undo/redo edits:** https://www.ag-grid.com/javascript-data-grid/undo-redo-edits/
56. **S56 — AG Grid — Column pinning:** https://www.ag-grid.com/javascript-data-grid/column-pinning/
57. **S57 — GitHub Primer — DataTable:** https://primer.style/product/components/data-table/
58. **S58 — GitHub Primer — ActionMenu:** https://primer.style/product/components/action-menu/
59. **S59 — GOV.UK Design System — Error summary:** https://design-system.service.gov.uk/components/error-summary/
60. **S60 — GOV.UK Design System — Notification banner:** https://design-system.service.gov.uk/components/notification-banner/
61. **S61 — Nielsen Norman Group — Microinteractions in User Experience:** https://www.nngroup.com/articles/microinteractions/
62. **S62 — Nielsen Norman Group — 10 Usability Heuristics:** https://www.nngroup.com/articles/ten-usability-heuristics/
63. **S63 — Nielsen Norman Group — User Control and Freedom:** https://www.nngroup.com/articles/user-control-and-freedom/
64. **S64 — Nielsen Norman Group — Empty States in Complex Applications:** https://www.nngroup.com/articles/empty-state-interface-design/

## Kararsız kalınan noktalar

1. **“Tüm” envanterin sınırı:** Mikro etkileşim literatüründe evrensel, kapalı bir üst küme bulunmadığından “tamlık” 2026-09-01 itibarıyla yoğun web iş uygulamaları, tasarım sistemleri ve erişilebilirlik standartlarında görülen davranış ailelerinin kapsanması olarak yorumlandı. Yeni bir ürün örneği, yeni uygulama kararı doğurmadan mevcut türlerden birine eklenebilir.
2. **J/K ve X seçimi:** Güçlü sektör emsali vardır; ancak tek harflerin yalnız görüntüleme kipinde çalışması ve ekran okuyucu/metin girişiyle çakışmaması gerekir. Nihai etkinleştirme PM kararıdır.
3. **Enter gönderimi:** Tek satırlı güvenli form, hücre düzenleme ve modal bağlamlarında anlamı farklıdır. Bu yüzden katalog tek bir küresel Enter davranışı önermemiş, kip sözleşmesini zorunlu tutmuştur.
4. **Cmd/Ctrl+S:** Otomatik kaydetme ile açık kaydetme beklentisi ürünlere göre ayrışır. Standarda “niyet alınır ve görünür sonuç verilir” ilkesi yazıldı; ekran bazlı davranış PM’e bırakıldı.
5. **Sonsuz kaydırma ve sayfalama:** İkisi de envanterde tutuldu; veri büyüklüğü, erişilebilirlik ve geri dönüş bağlamına göre seçim uygulama kararıdır.
6. **Hareket süreleri:** Ham süre veya easing değeri icat edilmedi. Örüntüler, seçilecek tasarım sisteminin kaynaklı anlamsal hareket tokenına bağlanır. Atlassian ve Material belgeleri kaynak olarak gösterildi; K105’in hangi token setini kullanacağı PM kararıdır.
7. **monday.com / ClickUp:** Görev metnindeki sekizinci emsal ifadesi ortak bir başlık olarak ele alındı; iki ürünün ayırt edici davranışları aynı bölümde ayrı ayrı belirtildi.

## Bilinçli kapsam kararları

- Uygulamaya özgü ekran, alan, kayıt türü veya özellik tasarlanmadı.
- Kod, şema, migration ya da uygulama mimarisi önerilmedi.
- Katalogda üçüncü taraf ürün özellikleri doğrudan kapsam önerisi olarak değil, davranış emsali olarak tutuldu.
- Sayısal animasyon süresi önerilmedi; kaynaklı token kullanma kuralı yazıldı.
