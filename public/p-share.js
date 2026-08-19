/*
 * Paylaşım sayfası etkileşimi (İE#13 F4) — HARİCİ dosya: CSP `default-src 'self'`
 * satır içi script'e izin vermez (K45/K51). Şartname: paylasim-v4-premium.html.
 *
 * İşler: detay panelini aç/kapa · lightbox galeri (ok/kaydırma/ESC) · video modalı ·
 * yazdır · WhatsApp · linki kopyala · Excel/PDF (yalnız panel oturumu olan görüntüleyende
 * basılan düğmeler; uçlar CSRF ister).
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
  }

  function galeriAc(hedef) {
    var index = Number(hedef.dataset.galeri);
    aktif = galeriler[index] || [];
    sira = Number(hedef.dataset.sira || 0);
    video.hidden = true;
    gorsel.hidden = false;
    goster();
    ac('gorsel');
  }

  function videoAc(hedef) {
    gorsel.hidden = true;
    video.hidden = false;
    video.src = hedef.dataset.video;
    sayac.textContent = '';
    ac('video');
    video.play().catch(function () {
      /* otomatik oynatma engellenebilir — kullanıcı kendi başlatır */
    });
  }

  document.addEventListener('click', function (olay) {
    var videoDugmesi = olay.target.closest('[data-video]');
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

    if (olay.target.closest('[data-yazdir]') !== null) {
      window.print();
      return;
    }

    var whatsapp = olay.target.closest('[data-whatsapp]');
    if (whatsapp !== null) {
      var metin = whatsapp.dataset.whatsapp + ' — ' + location.href;
      window.open('https://wa.me/?text=' + encodeURIComponent(metin), '_blank', 'noopener');
      return;
    }

    var kopyala = olay.target.closest('[data-kopyala]');
    if (kopyala !== null) {
      var yaz = navigator.clipboard
        ? navigator.clipboard.writeText(location.href)
        : Promise.reject(new Error('pano yok'));
      yaz.then(
        function () {
          kopyala.classList.add('kopyalandi');
          var etiket = kopyala.querySelector('span');
          if (etiket !== null) {
            var eski = etiket.textContent;
            etiket.textContent = 'Kopyalandı';
            setTimeout(function () {
              etiket.textContent = eski;
              kopyala.classList.remove('kopyalandi');
            }, 1800);
          }
        },
        function () {
          window.prompt('Bağlantıyı kopyalayın:', location.href);
        },
      );
      return;
    }

    var disaAktar = olay.target.closest('[data-export]');
    if (disaAktar !== null) {
      // Uç oturum + CSRF ister; düğme zaten yalnız panel oturumu olan görüntüleyende basılır.
      var liste = document.body.dataset.liste;
      window.open('/panel/listeler/' + liste + '?cikti=' + disaAktar.dataset.export, '_blank', 'noopener');
    }
  });

  document.addEventListener('keydown', function (olay) {
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
    var hedef = olay.target.closest('[data-galeri], [data-video]');
    if (hedef === null) return;
    olay.preventDefault();
    if (hedef.dataset.video) videoAc(hedef);
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
