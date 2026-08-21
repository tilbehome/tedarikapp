/*
 * Paylaşım sayfası etkileşimi (İE#13 F4) — HARİCİ dosya: CSP `default-src 'self'`
 * satır içi script'e izin vermez (K45/K51). Şartname: paylasim-v4-premium.html.
 *
 * İşler: detay panelini aç/kapa · lightbox galeri (ok/kaydırma/ESC) · video modalı ·
 * yazdırma hatırlatması · paylaş menüsü (WhatsApp · WeChat/DingTalk QR · QQ · Telegram ·
 * e-posta · link) · indirme geri bildirimi.
 *
 * İE#15: Excel/PDF/CSV artık OTURUMSUZ çalışır — bağlantılar sunucuda imzalanmış
 * olarak sayfaya gömülür (A1), bu dosya yalnız "hazırlanıyor…" geri bildirimini verir.
 *
 * JavaScript OLMASA DA sayfa okunur: tablo, fiyatlar ve "Ürüne git" bağlantıları
 * saf HTML'dir. Bu dosya yalnız etkileşimi ekler.
 */
(function () {
  'use strict';

  var katman = document.getElementById('lbx');
  var gorsel = document.getElementById('lbi');
  var video = document.getElementById('lbv');
  var sayac = document.getElementById('lbs');
  var lbNot = document.getElementById('lbnot');
  var qrModal = document.getElementById('qrm');
  var qrGorsel = document.getElementById('qri');
  var qrBaslik = document.getElementById('qra');
  var qrNot = document.getElementById('qrn');
  var qrIndir = document.getElementById('qrp');
  var yazdirNotu = document.getElementById('ynot');
  var menu = document.querySelector('[data-paylas-menu]');

  // Paylaşım metinleri SUNUCUDAN gelir (İE#15 C2 — dil dosyası PHP tarafında);
  // burada yalnız kanala göre nereye gönderileceği bilinir.
  function paylasimVerisi() {
    if (menu === null) return { link: location.href, mesaj: location.href, konu: '' };
    return {
      link: menu.dataset.link || location.href,
      mesaj: menu.dataset.mesaj || location.href,
      konu: menu.dataset.konu || '',
      dil: menu.dataset.dil || 'tr',
    };
  }

  var galeriler = [];
  try {
    galeriler = JSON.parse(document.body.dataset.galeriler || '[]');
  } catch (hata) {
    galeriler = [];
  }

  var aktif = [];
  var sira = 0;

  function goster() {
    if (aktif.length === 0) return;
    if (sira < 0) sira = aktif.length - 1;
    if (sira >= aktif.length) sira = 0;
    gorsel.src = aktif[sira];
    sayac.textContent = aktif.length > 1 ? '‹ ' + (sira + 1) + ' / ' + aktif.length + ' ›' : '';
  }

  function ac(mod) {
    katman.classList.add('on');
    katman.dataset.mod = mod;
  }

  function kapat() {
    katman.classList.remove('on');
    video.pause();
    video.removeAttribute('src');
    video.load();
    video.hidden = true;
    gorsel.hidden = false;
    if (lbNot !== null) {
      lbNot.hidden = true;
      lbNot.textContent = '';
    }
  }

  function galeriAc(hedef) {
    var index = Number(hedef.dataset.galeri);
    aktif = galeriler[index] || [];
    sira = Number(hedef.dataset.sira || 0);
    video.hidden = true;
    gorsel.hidden = false;
    if (lbNot !== null) lbNot.hidden = true;
    goster();
    ac('gorsel');
  }

  /**
   * İE#15 E3/E4 — video modalı.
   *
   * Oynatılabilir adres varsa oynatılır. Yakalama "video var" dediği hâlde adres
   * alınamamışsa (1688 videoları imzalı MTOP isteği ister) modal BOŞ AÇILMAZ:
   * nazik bir açıklama ve varsa kaynak sayfa bağlantısı gösterilir.
   */
  function videoAc(hedef) {
    gorsel.hidden = true;
    sayac.textContent = '';

    var adres = hedef.dataset.video;
    if (!adres) {
      video.hidden = true;
      if (lbNot !== null) {
        lbNot.hidden = false;
        lbNot.textContent = 'Video şu an oynatılamıyor.';
        var kaynak = hedef.dataset.videoKaynak;
        if (kaynak) {
          var bag = document.createElement('a');
          bag.href = kaynak;
          bag.target = '_blank';
          bag.rel = 'noopener noreferrer nofollow';
          bag.textContent = 'Kaynak sayfada aç';
          lbNot.appendChild(document.createElement('br'));
          lbNot.appendChild(bag);
        }
      }
      ac('video');
      return;
    }

    if (lbNot !== null) lbNot.hidden = true;
    video.hidden = false;
    video.src = adres;
    ac('video');
    video.play().catch(function () {
      /* otomatik oynatma engellenebilir — kullanıcı kendi başlatır */
    });
    video.onerror = function () {
      video.hidden = true;
      if (lbNot !== null) {
        lbNot.hidden = false;
        lbNot.textContent = 'Video şu an oynatılamıyor.';
      }
    };
  }

  /** İE#15 C3 — QR modalı: kare sunucudan gelir, dış servis yok. */
  function qrAc(kanal, baslik) {
    if (qrModal === null) return;
    var veri = paylasimVerisi();
    var adres = location.pathname.replace(/\/$/, '') + '/qr.png'
      + (veri.dil && veri.dil !== 'tr' ? '?lang=' + encodeURIComponent(veri.dil) : '');
    qrGorsel.src = adres;
    qrIndir.href = adres;
    qrBaslik.textContent = baslik;
    qrNot.textContent = kanal === 'wechat'
      ? 'WeChat > + > Taramak (扫一扫) ile okutun. Özet metnini kopyalayıp sohbete yapıştırabilirsiniz.'
      : 'DingTalk uygulamasında tarayıcı ile okutun. Özet metnini kopyalayıp sohbete yapıştırabilirsiniz.';
    qrModal.hidden = false;
  }

  function qrKapat() {
    if (qrModal !== null) qrModal.hidden = true;
  }

  function panoyaYaz(metin, dugme, basariliEtiket) {
    var yaz = navigator.clipboard
      ? navigator.clipboard.writeText(metin)
      : Promise.reject(new Error('pano yok'));
    yaz.then(
      function () {
        if (dugme === null || dugme === undefined) return;
        var etiket = dugme.querySelector('span') || dugme;
        var eski = etiket.textContent;
        dugme.classList.add('kopyalandi');
        etiket.textContent = basariliEtiket || 'Kopyalandı';
        setTimeout(function () {
          etiket.textContent = eski;
          dugme.classList.remove('kopyalandi');
        }, 1800);
      },
      function () {
        window.prompt('Kopyalayın:', metin);
      },
    );
  }

  function menuKapat() {
    if (menu === null) return;
    menu.hidden = true;
    var dugme = document.querySelector('[data-paylas-ac]');
    if (dugme !== null) dugme.setAttribute('aria-expanded', 'false');
  }

  /** Kanal → hedef adres. WeChat ve DingTalk burada YOKTUR: onlar QR ile paylaşılır. */
  function kanalAdresi(kanal, veri) {
    var link = encodeURIComponent(veri.link);
    var mesaj = encodeURIComponent(veri.mesaj);
    switch (kanal) {
      case 'whatsapp':
        return 'https://wa.me/?text=' + mesaj;
      case 'telegram':
        return 'https://t.me/share/url?url=' + link + '&text=' + encodeURIComponent(veri.mesaj);
      case 'qq':
        return 'https://connect.qq.com/widget/shareqq/index.html?url=' + link
          + '&title=' + encodeURIComponent(veri.konu) + '&desc=' + mesaj;
      case 'eposta':
        return 'mailto:?subject=' + encodeURIComponent(veri.konu) + '&body=' + mesaj;
      default:
        return null;
    }
  }

  document.addEventListener('click', function (olay) {
    var videoDugmesi = olay.target.closest('[data-video], [data-video-yok]');
    if (videoDugmesi !== null) {
      olay.preventDefault();
      olay.stopPropagation();
      videoAc(videoDugmesi);
      return;
    }

    var galeriHedefi = olay.target.closest('[data-galeri]');
    if (galeriHedefi !== null) {
      olay.preventDefault();
      galeriAc(galeriHedefi);
      return;
    }

    if (katman.classList.contains('on') && olay.target.closest('#lbx') !== null) {
      kapat();
      return;
    }

    var detayDugmesi = olay.target.closest('[data-detay]');
    if (detayDugmesi !== null) {
      var satir = detayDugmesi.closest('tr').nextElementSibling;
      var acik = detayDugmesi.classList.toggle('on');
      if (satir !== null) satir.style.display = acik ? '' : 'none';
      return;
    }

    // ── İE#15 B2: yazdırma öncesi tek seferlik hatırlatma
    if (olay.target.closest('[data-yazdir]') !== null) {
      if (yazdirNotu !== null && localStorage.getItem('tdk-yazdir-notu') !== 'gizli') {
        yazdirNotu.hidden = false;
      } else {
        window.print();
      }
      return;
    }
    if (olay.target.closest('[data-yazdir-devam]') !== null) {
      var kutu = document.getElementById('ynh');
      if (kutu !== null && kutu.checked) localStorage.setItem('tdk-yazdir-notu', 'gizli');
      yazdirNotu.hidden = true;
      window.print();
      return;
    }
    if (olay.target.closest('[data-yazdir-iptal]') !== null) {
      yazdirNotu.hidden = true;
      return;
    }

    // ── İE#17 G5: İNDİRME — bağlantı TIKLAMA ANINDA tazelenir.
    //
    // Eski davranış: bağlantılar sayfa açılırken imzalanıyordu (15 dk ömür).
    // Sayfa daha uzun açık kalınca imza ölüyor, sunucu sabit 404 dönüyor ve
    // etiket "hazırlanıyor…"da kalıyordu. Artık önce /export-link çağrılır,
    // dönen TAZE adrese gidilir. JS yoksa HTML'deki imzalı href yine iş görür.
    var indirme = olay.target.closest('[data-indir]');
    if (indirme !== null) {
      olay.preventDefault();
      if (indirme.classList.contains('bekliyor')) return;

      var etiketAlani = indirme.querySelector('span');
      var eskiEtiket = etiketAlani === null ? '' : etiketAlani.textContent;
      var yaz = function (metin) {
        if (etiketAlani !== null) etiketAlani.textContent = metin;
      };
      var bitir = function (metin, sure) {
        yaz(metin);
        setTimeout(function () {
          yaz(eskiEtiket);
          indirme.classList.remove('bekliyor');
        }, sure);
      };

      indirme.classList.add('bekliyor');
      yaz('hazırlanıyor…');

      var bicim = indirme.dataset.format || '';
      var dil = indirme.dataset.lang || 'tr';
      var kok = location.pathname.replace(/\/$/, '');
      var adres = kok + '/export-link?format=' + encodeURIComponent(bicim) + '&lang=' + encodeURIComponent(dil);

      fetch(adres, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (yanit) {
          if (yanit.status === 429) {
            var bekle = parseInt(yanit.headers.get('Retry-After') || '', 10);
            var sure = isNaN(bekle) ? '' : ' (' + Math.max(1, Math.round(bekle / 60)) + ' dk)';
            bitir('indirme sınırına ulaşıldı — bir süre sonra deneyin' + sure, 6000);

            return null;
          }
          if (!yanit.ok) {
            bitir('indirilemedi — sayfayı yenileyip tekrar deneyin', 6000);

            return null;
          }

          return yanit.json();
        })
        .then(function (veri) {
          if (veri === null) return;
          var hedef = veri && veri.data ? veri.data.url : null;
          if (!hedef) {
            bitir('indirilemedi — sayfayı yenileyip tekrar deneyin', 6000);

            return;
          }
          // Yanıt "attachment" olduğu için sayfa DEĞİŞMEZ, indirme başlar.
          window.location.assign(hedef);
          bitir(eskiEtiket, 3000);
        })
        .catch(function () {
          // Konsola hata dökülmez, alert açılmaz — kullanıcıya tek satır yeter.
          bitir('indirilemedi — sayfayı yenileyip tekrar deneyin', 6000);
        });

      return;
    }

    // ── İE#15 C1: paylaş menüsü
    var paylasAc = olay.target.closest('[data-paylas-ac]');
    if (paylasAc !== null && menu !== null) {
      var veriler = paylasimVerisi();
      // Mobilde önce yerel paylaşım sayfası denenir (navigator.share).
      if (navigator.share && window.matchMedia('(max-width: 940px)').matches) {
        navigator.share({ title: veriler.konu, text: veriler.mesaj, url: veriler.link }).catch(function () {
          menu.hidden = false;
          paylasAc.setAttribute('aria-expanded', 'true');
        });
        return;
      }
      var acik = menu.hidden;
      menu.hidden = !acik;
      paylasAc.setAttribute('aria-expanded', acik ? 'true' : 'false');
      return;
    }

    var kopyala = olay.target.closest('[data-kopyala]');
    if (kopyala !== null) {
      panoyaYaz(paylasimVerisi().link, kopyala, 'Kopyalandı');
      menuKapat();
      return;
    }

    var kanalDugmesi = olay.target.closest('[data-kanal]');
    if (kanalDugmesi !== null) {
      var kanal = kanalDugmesi.dataset.kanal;
      var veri = paylasimVerisi();
      if (kanalDugmesi.dataset.qr === '1') {
        qrAc(kanal, kanalDugmesi.textContent.trim());
        menuKapat();
        return;
      }
      var hedef = kanalAdresi(kanal, veri);
      if (hedef !== null) {
        if (kanal === 'eposta') window.location.href = hedef;
        else window.open(hedef, '_blank', 'noopener,noreferrer');
      }
      menuKapat();
      return;
    }

    if (olay.target.closest('[data-qr-metin]') !== null) {
      panoyaYaz(paylasimVerisi().mesaj, olay.target.closest('[data-qr-metin]'), 'Metin kopyalandı');
      return;
    }
    if (olay.target.closest('[data-qr-kapat]') !== null
      || (qrModal !== null && !qrModal.hidden && olay.target === qrModal)) {
      qrKapat();
      return;
    }

    // Menü dışına tıklayınca kapanır.
    if (menu !== null && !menu.hidden && olay.target.closest('.pmenu') === null) {
      menuKapat();
    }
  });

  document.addEventListener('keydown', function (olay) {
    if (olay.key === 'Escape') {
      qrKapat();
      menuKapat();
      if (yazdirNotu !== null) yazdirNotu.hidden = true;
    }
    if (!katman.classList.contains('on')) return;
    if (olay.key === 'Escape') kapat();
    if (katman.dataset.mod !== 'gorsel') return;
    if (olay.key === 'ArrowRight') {
      sira += 1;
      goster();
    }
    if (olay.key === 'ArrowLeft') {
      sira -= 1;
      goster();
    }
  });

  // Klavye erişilebilirliği: görsel/video rozetleri Enter/Space ile de açılır.
  document.addEventListener('keydown', function (olay) {
    if (olay.key !== 'Enter' && olay.key !== ' ') return;
    var hedef = olay.target.closest('[data-galeri], [data-video], [data-video-yok]');
    if (hedef === null) return;
    olay.preventDefault();
    if (hedef.dataset.video || hedef.dataset.videoYok) videoAc(hedef);
    else galeriAc(hedef);
  });

  // Dokunmatik: yatay kaydırma ile önceki/sonraki görsel.
  var baslangic = null;
  katman.addEventListener('touchstart', function (olay) {
    baslangic = olay.changedTouches[0].clientX;
  }, { passive: true });
  katman.addEventListener('touchend', function (olay) {
    if (baslangic === null || katman.dataset.mod !== 'gorsel') return;
    var fark = olay.changedTouches[0].clientX - baslangic;
    if (Math.abs(fark) > 45) {
      sira += fark < 0 ? 1 : -1;
      goster();
    }
    baslangic = null;
  }, { passive: true });

  // Detay panelleri KAPALI başlar (şartnamede ilk satır açık gösterilir; canlıda
  // uzun listede tüm panelleri açık başlatmak sayfayı boğar).
  var paneller = document.querySelectorAll('tr.dt');
  for (var i = 0; i < paneller.length; i++) {
    paneller[i].style.display = 'none';
  }
})();
