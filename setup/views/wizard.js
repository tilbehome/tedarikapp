/* tedarikapp kurulum sihirbazı — bağımlılıksız, tek dosya.
 *
 * Güvenlik başlıkları satır içi script'e izin vermediği için bu dosya ayrı uçtan servis edilir.
 * Sunucudan gelen hiçbir metin innerHTML ile basılmaz (XSS) — textContent kullanılır.
 */
(function () {
  'use strict';

  var csrfToken = '';
  var STEPS = ['requirements', 'database', 'env', 'migrate', 'admin', 'recovery', 'done'];

  // K42 tanılama durumu: adım günlüğü + son hata + son Request-ID.
  var progressLog = [];
  var lastFailure = null;
  var lastRequestId = '';

  var $ = function (id) { return document.getElementById(id); };

  // ─────────── HTTP ───────────

  function api(method, path, body) {
    var options = {
      method: method,
      headers: { 'X-Setup-Token': csrfToken },
      credentials: 'same-origin'
    };
    if (body !== undefined) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(body);
    }
    var startedAt = Date.now();
    return fetch(path, options).then(function (response) {
      lastRequestId = response.headers.get('X-Request-Id') || lastRequestId;
      return response.json().catch(function () { return null; }).then(function (payload) {
        var ok = !!(payload && payload.success);
        appendLog({ step: method + ' ' + path, ok: ok, ms: Date.now() - startedAt });
        if (ok) return payload.data;
        var error = new Error((payload && payload.error && payload.error.message) || 'Beklenmeyen bir hata oluştu.');
        error.code = (payload && payload.error && payload.error.code) || '';
        error.fields = (payload && payload.error && payload.error.fields) || {};
        error.diagnostics = (payload && payload.meta && payload.meta.diagnostics) || null;
        throw error;
      });
    }, function (networkError) {
      appendLog({ step: method + ' ' + path, ok: false, ms: Date.now() - startedAt });
      throw networkError;
    });
  }

  // ─────────── K42: işlem günlüğü + tanılama ───────────

  function appendLog(entry) {
    progressLog.push(entry);
    var panel = $('islem-paneli');
    var list = $('islem-gunlugu');
    if (!panel || !list) return;
    panel.hidden = false;
    var row = document.createElement('li');
    row.className = entry.ok ? 'pass' : 'fail';
    row.textContent = (entry.ok ? '✓ ' : '✕ ') + entry.step + ' — ' + entry.ms + ' ms';
    list.appendChild(row);
  }

  function progressSummary() {
    var total = 0;
    var failed = 0;
    progressLog.forEach(function (entry) { total += entry.ms; if (!entry.ok) failed += 1; });
    return progressLog.length + ' adım · ' + failed + ' hata · toplam ' + total + ' ms';
  }

  /** Dostane mesaj alert'te; teknik detay + kopyalanabilir rapor tanılama kutusunda. */
  function failBox(error) {
    alertBox('bad', error.message);
    lastFailure = error.diagnostics || null;
    var box = $('diag-box');
    if (!box) return;
    if (!error.diagnostics) { box.hidden = true; return; }
    $('diag-detail').textContent = diagnosticsText(error.diagnostics, false);
    box.hidden = false;
    box.scrollIntoView({ block: 'nearest' });
  }

  function clearFailure() { lastFailure = null; var box = $('diag-box'); if (box) box.hidden = true; }

  /** Teşhis nesnesini düz metne çevirir (SIR İÇERMEZ — sunucu tarafı redaksiyonlu). */
  function diagnosticsText(diag, fullReport) {
    var lines = [];
    var env = diag.environment || {};
    var failure = diag.failure || {};
    if (fullReport) {
      lines.push('=== tedarikapp KURULUM TANILAMA RAPORU ===');
      lines.push('Zaman: ' + (env.timestamp || new Date().toISOString()));
      lines.push('Request-ID: ' + (lastRequestId || 'yok'));
      lines.push('');
    }
    lines.push('Uygulama: ' + (env.app_version || '?') + ' · PHP ' + (env.php_version || '?') + ' (' + (env.sapi || '?') + ')');
    lines.push('Sunucu: ' + (env.os || '?') + ' · MySQL: ' + (env.mysql_version || 'alınamadı'));
    var extensions = env.extensions || {};
    lines.push('Eklentiler: ' + Object.keys(extensions).map(function (name) {
      return name + '=' + extensions[name];
    }).join(' '));
    if (failure.step) {
      lines.push('');
      lines.push('Başarısız adım: ' + failure.step);
      lines.push('Hata: ' + (failure.exception || '?') + ' — ' + (failure.message || ''));
      lines.push('Konum: ' + (failure.location || '?'));
      (failure.trace || []).forEach(function (frame) { lines.push('  ' + frame); });
    }
    if (fullReport) {
      lines.push('');
      lines.push('İşlem günlüğü (' + progressSummary() + '):');
      progressLog.forEach(function (entry) {
        lines.push('  ' + (entry.ok ? 'OK  ' : 'HATA') + ' ' + entry.step + ' — ' + entry.ms + ' ms');
      });
    }
    return lines.join('\n');
  }

  function copyDiagnosticReport() {
    var build = function (diag) {
      var text = diagnosticsText(diag, true);
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function () {
          alertBox('ok', 'Tanılama raporu panoya kopyalandı; destek talebinize yapıştırabilirsiniz.');
        }, function () {
          alertBox('warn', 'Tarayıcı kopyalamaya izin vermedi; teknik detay bölümünü elle seçip kopyalayın.');
        });
      } else {
        alertBox('warn', 'Tarayıcı kopyalamaya izin vermedi; teknik detay bölümünü elle seçip kopyalayın.');
      }
    };
    if (lastFailure) return build(lastFailure);
    // Hata yanıtında teşhis yoksa (örn. ağ hatası) ortam özeti sunucudan çekilir.
    api('GET', '/api/setup/diagnostics').then(function (env) {
      build({ environment: env, failure: null });
    }).catch(function () {
      build({ environment: {}, failure: null });
    });
  }

  // ─────────── Arayüz yardımcıları ───────────

  function alertBox(kind, message) {
    var box = $('alert');
    box.className = 'alert ' + kind;
    box.textContent = message;
    box.hidden = false;
    box.scrollIntoView({ block: 'nearest' });
  }

  function clearAlert() { $('alert').hidden = true; clearFailure(); }

  // D2-REV: teşhis/onarım ekranları normal adım dizisinin PARÇASI DEĞİLDİR —
  // adım şeridinde yerleri yoktur, ama bir adıma geçildiğinde kapanmaları gerekir.
  var ONARIM_PANELLERI = ['teshis', 'sahiplik', 'yikici', 'config-onar', 'guncelle'];

  function onarimPaneliniGoster(ad) {
    STEPS.forEach(function (name) {
      var panel = $('panel-' + name);
      if (panel) panel.hidden = true;
    });
    ONARIM_PANELLERI.forEach(function (name) {
      var panel = $('panel-' + name);
      if (panel) panel.hidden = name !== ad;
    });
    Array.prototype.forEach.call($('steps').children, function (item) { item.className = ''; });
    $('steps').hidden = true;
  }

  function showStep(step) {
    ONARIM_PANELLERI.forEach(function (name) {
      var panel = $('panel-' + name);
      if (panel) panel.hidden = true;
    });
    $('steps').hidden = false;
    STEPS.forEach(function (name) {
      var panel = $('panel-' + name);
      if (panel) panel.hidden = name !== step;
    });
    var index = STEPS.indexOf(step);
    Array.prototype.forEach.call($('steps').children, function (item, position) {
      item.className = position === index ? 'active' : (position < index ? 'done' : '');
    });
  }

  function showFieldErrors(form, fields) {
    Array.prototype.forEach.call(form.querySelectorAll('.field-error'), function (node) { node.remove(); });
    Object.keys(fields || {}).forEach(function (name) {
      var input = form.querySelector('[name="' + name + '"]');
      var note = document.createElement('span');
      note.className = 'field-error';
      note.textContent = fields[name];
      if (input && input.parentNode) input.parentNode.appendChild(note);
      else form.appendChild(note);
    });
  }

  function busy(button, on, label) {
    if (!button) return;
    button.disabled = on;
    if (on) {
      button.dataset.label = button.textContent;
      button.textContent = label || 'Çalışıyor…';
    } else if (button.dataset.label) {
      button.textContent = button.dataset.label;
    }
  }

  function checklist(items) {
    var list = document.createElement('ul');
    items.forEach(function (item) {
      var row = document.createElement('li');
      row.className = item.state;
      var mark = document.createElement('span');
      mark.className = 'mark';
      mark.textContent = item.state === 'pass' ? '✓' : (item.state === 'fail' ? '✕' : '!');
      var text = document.createElement('span');
      var title = document.createElement('strong');
      title.textContent = item.title;
      text.appendChild(title);
      if (item.hint) {
        var hint = document.createElement('span');
        hint.className = 'hint';
        hint.textContent = item.hint;
        text.appendChild(hint);
      }
      row.appendChild(mark);
      row.appendChild(text);
      list.appendChild(row);
    });
    return list;
  }

  function definitions(pairs) {
    var list = document.createElement('dl');
    pairs.forEach(function (pair) {
      var term = document.createElement('dt');
      term.textContent = pair[0];
      var value = document.createElement('dd');
      value.textContent = pair[1];
      list.appendChild(term);
      list.appendChild(value);
    });
    return list;
  }

  function replaceContent(node, child) {
    node.textContent = '';
    node.appendChild(child);
    node.hidden = false;
  }

  // ─────────── 1) Gereksinimler ───────────

  function loadRequirements() {
    var body = $('req-body');
    body.textContent = 'Denetleniyor…';
    return Promise.all([
      api('GET', '/api/setup/requirements'),
      // K43: MANIFEST'e göre eksik/bozuk dosya denetimi — ulaşılamazsa adımı düşürmez.
      api('GET', '/api/system/integrity').catch(function () { return null; })
    ]).then(function (results) {
      var data = results[0];
      var integrity = results[1];
      return renderRequirements(data, integrity);
    });
  }

  function renderRequirements(data, integrity) {
    var body = $('req-body');
    return (function () {
      var items = [{
        state: data.php.ok ? 'pass' : 'fail',
        title: 'PHP ' + data.php.current,
        hint: data.php.ok ? 'En az ' + data.php.required + ' gerekiyor.' :
          'En az ' + data.php.required + ' gerekiyor. cPanel > MultiPHP Manager\'dan sürümü yükseltin.'
      }];
      data.extensions.forEach(function (extension) {
        items.push({
          state: extension.ok ? 'pass' : (extension.required ? 'fail' : 'skip'),
          title: extension.name + (extension.required ? '' : ' (opsiyonel)'),
          hint: extension.ok ? '' : extension.reason
        });
      });
      data.writable.forEach(function (directory) {
        // K37 §D10: yazılamayan klasör kurulumu BLOKLAMAZ — hotlink/DB moduyla
        // devam edilir; madde uyarı olarak işaretlenir.
        items.push({
          state: directory.ok ? 'pass' : 'skip',
          title: directory.path + ' yazılabilir' + (directory.ok ? '' : ' değil (hotlink modu)'),
          hint: directory.hint
        });
      });
      if (data.https) {
        items.push({
          state: data.https.ok ? 'pass' : (data.https.required ? 'fail' : 'skip'),
          title: 'HTTPS bağlantısı' + (data.https.required ? '' : ' (geliştirme: opsiyonel)'),
          hint: data.https.hint
        });
      }

      // K43: dosya bütünlüğü — eksik açılmış zip artık SESSİZ kalmaz, isim isim söylenir.
      var integrityOk = true;
      if (integrity && integrity.manifest_exists) {
        if (integrity.ok) {
          items.push({ state: 'pass', title: 'Dosya bütünlüğü', hint: integrity.message });
        } else {
          integrityOk = false;
          var eksikler = (integrity.missing || []).slice(0, 10);
          var degisenler = (integrity.modified || []).slice(0, 10);
          var detay = [];
          if (eksikler.length) detay.push('EKSİK: ' + eksikler.join(', '));
          if (degisenler.length) detay.push('DEĞİŞMİŞ: ' + degisenler.join(', '));
          // Yalnız .htaccess değişmişse bu cPanel'in PHP sürüm seçiminden gelir — normaldir.
          var sadeceHtaccess = !eksikler.length && degisenler.length === 1 && degisenler[0] === '.htaccess';
          items.push({
            state: sadeceHtaccess ? 'skip' : 'fail',
            title: sadeceHtaccess
              ? 'Dosya bütünlüğü — yalnız .htaccess değişmiş (normal)'
              : 'Dosya bütünlüğü — zip eksik/bozuk açılmış',
            hint: (sadeceHtaccess
              ? 'cPanel, PHP sürümü seçildiğinde .htaccess dosyasına kendi satırlarını ekler; bu beklenen bir durumdur ve sorun değildir.'
              : integrity.message) + (detay.length ? ' — ' + detay.join(' · ') : '')
          });
          if (sadeceHtaccess) integrityOk = true;
        }
      } else if (integrity) {
        items.push({ state: 'skip', title: 'Dosya bütünlüğü', hint: integrity.message });
      }

      replaceContent(body, checklist(items));
      // K45: hiçbir denetim İLERLEMEYİ KAPATMAZ — eksikler yalnız uyarı olarak gösterilir.
      $('req-next').disabled = false;

      if (!integrityOk) alertBox('warn', 'Bazı dosyalar eksik/bozuk görünüyor (yukarıda listelendi) — yine de devam edebilirsiniz.');
      else if (!data.ok) alertBox('warn', 'Bazı gereksinimler eksik görünüyor — yine de devam edebilirsiniz.');
      else if (data.warnings.length) alertBox('warn', data.warnings.join(' '));
      else clearAlert();
    })();
  }


  // ═════════════════ D2-REV: TEŞHİS + ONARIM MERKEZİ ═════════════════
  //
  // Sihirbaz artık "kilit var mı?" ile başlamaz; sunucuya "ne buldun?" diye sorar
  // ve gelen duruma göre TEK doğru yolu sunar. Buradaki her seçenek sihirbaz
  // İÇİNDE tamamlanır — kullanıcı hiçbir hâlde komut satırına ya da phpMyAdmin'e
  // gönderilmez (tek istisna dosya yüklemedir; onun da tarifi ekrandadır).

  var teshis = null;

  var ROZET_SINIFI = { 'iyi': 'iyi', 'uyarı': 'uyari', 'kötü': 'kotu', 'nötr': '' };

  function teshisYukle() {
    return api('GET', '/api/setup/situation').then(function (data) {
      teshis = data;
      csrfToken = data.csrf_token || csrfToken;
      teshisCiz(data);
      return data;
    });
  }

  function teshisCiz(data) {
    onarimPaneliniGoster('teshis');

    var rozet = $('teshis-rozet');
    rozet.className = 'rozet ' + (ROZET_SINIFI[data.rozet] || '');
    rozet.textContent = 'Teşhis: ' + data.durum.replace(/_/g, ' ').toLowerCase();
    $('teshis-baslik').textContent = data.baslik;
    $('teshis-aciklama').textContent = data.aciklama;

    // Özet tablo — hangi ölçüm ne dedi.
    var satirlar = [
      ['Ayar dosyası', data.config.var ? (data.config.saglam ? 'var, sağlam' : 'var ama eksik: ' + data.config.eksik_alanlar.join(', ')) : 'yok'],
      ['Veritabanı', data.veritabani.denendi ? (data.veritabani.erisim ? 'bağlanıldı' + (data.veritabani.surum ? ' (' + data.veritabani.surum + ')' : '') : 'HATA — ' + data.veritabani.hata) : 'denenmedi (ayar dosyası okunamadı)'],
      ['Tablolar', data.sema.okundu ? String(data.sema.tablo_sayisi) : '—'],
      ['Migration', data.sema.okundu ? (data.sema.uygulanan_sayisi + ' uygulandı · ' + data.sema.bekleyen_sayisi + ' bekliyor') : '—'],
      ['Kurulum kilidi', data.kilit],
      ['Sürüm', 'dosyalar ' + data.surum.dosya + ' · veritabanı ' + (data.surum.kurulu || 'kayıtsız')],
      ['Paket dosyaları', data.dosyalar.manifest_var ? (data.dosyalar.tamam ? 'tam (' + data.dosyalar.toplam + ' dosya)' : data.dosyalar.eksik_sayisi + ' eksik · ' + data.dosyalar.bozuk_sayisi + ' bozuk') : 'MANIFEST yok (geliştirme kurulumu)']
    ];
    replaceContent($('teshis-detay'), definitions(satirlar));

    // Migration tablosu — hangisi koşmuş, hangisi bekliyor.
    var migrationKutu = $('teshis-migration');
    if (data.sema.okundu && (data.sema.uygulanan.length || data.sema.bekleyen.length)) {
      var liste = document.createElement('div');
      data.sema.uygulanan.forEach(function (ad) { liste.appendChild(migrationSatiri(ad, true)); });
      data.sema.bekleyen.forEach(function (ad) { liste.appendChild(migrationSatiri(ad, false)); });
      replaceContent($('teshis-migration-tablo'), liste);
      migrationKutu.hidden = false;
    } else {
      migrationKutu.hidden = true;
    }

    // Eksik/bozuk dosyalar.
    var dosyaKutu = $('teshis-dosyalar');
    if (data.dosyalar.manifest_var && !data.dosyalar.tamam) {
      var kutu = document.createElement('div');
      kutu.className = 'dosya-liste';
      data.dosyalar.eksik.forEach(function (yol) { kutu.appendChild(dosyaSatiri('EKSİK', yol)); });
      data.dosyalar.bozuk.forEach(function (yol) { kutu.appendChild(dosyaSatiri('BOZUK', yol)); });
      replaceContent($('teshis-dosya-liste'), kutu);
      dosyaKutu.hidden = false;
    } else {
      dosyaKutu.hidden = true;
    }

    seceneklerCiz(data.secenekler);
  }

  function migrationSatiri(ad, uygulandi) {
    var satir = document.createElement('div');
    satir.className = 'migration-satir ' + (uygulandi ? 'uygulandi' : 'bekliyor');
    var durum = document.createElement('span');
    durum.className = 'durum';
    durum.textContent = uygulandi ? '✓ koştu' : '• bekliyor';
    var isim = document.createElement('span');
    isim.textContent = ad;
    satir.appendChild(durum);
    satir.appendChild(isim);
    return satir;
  }

  function dosyaSatiri(etiket, yol) {
    var satir = document.createElement('div');
    satir.textContent = etiket + '  ' + yol;
    return satir;
  }

  function seceneklerCiz(secenekler) {
    var kutu = $('teshis-secenekler');
    while (kutu.firstChild) kutu.removeChild(kutu.firstChild);

    (secenekler || []).forEach(function (secenek, sira) {
      var sarmal = document.createElement('div');
      var dugme = document.createElement('button');
      dugme.type = 'button';
      dugme.textContent = secenek.etiket;
      if (secenek.yikici || sira > 0) dugme.className = 'ghost';
      dugme.addEventListener('click', function () { secenekCalistir(secenek.kod); });
      var not = document.createElement('span');
      not.className = 'secenek-aciklama';
      not.textContent = secenek.aciklama;
      sarmal.appendChild(dugme);
      sarmal.appendChild(not);
      kutu.appendChild(sarmal);
    });
  }

  /** Seçilen yolu başlatır. Sahiplik gerektiren yollar önce doğrulamaya uğrar. */
  function secenekCalistir(kod) {
    clearAlert();

    if (kod === 'panele_git') { location.href = '/panel'; return; }
    if (kod === 'yeniden_tara') { teshisYukle().catch(failBox); return; }
    if (kod === 'normal_kurulum') { showStep('requirements'); loadRequirements().catch(failBox); return; }

    if (kod === 'db_bilgilerini_duzelt' || kod === 'config_onar') {
      configOnarAc(kod === 'db_bilgilerini_duzelt');
      return;
    }

    // Kalan yollar KURULU bir sisteme dokunur: önce sahiplik, sonra iş.
    // Bilet zaten varsa (15 dakika içinde doğrulanmış) tekrar sorulmaz.
    var devam = function () {
      if (kod === 'temiz_kurulum') { onarimPaneliniGoster('yikici'); return; }
      if (kod === 'guncelle' || kod === 'bekleyenleri_tamamla') { guncelleAc(); return; }
      if (kod === 'devam_et') { showStep(teshis && teshis.oturum_adimi ? teshis.oturum_adimi : 'migrate'); return; }
    };

    var sahiplikGerekli = teshis && teshis.kilit === 'locked' && !teshis.bilet_var;
    if (sahiplikGerekli) { sahiplikAc(devam); return; }
    devam();
  }

  // ─────────── Sahiplik doğrulama ───────────

  var sahiplikSonrasi = null;

  function sahiplikAc(sonra) {
    sahiplikSonrasi = sonra;
    onarimPaneliniGoster('sahiplik');
  }

  function sahiplikGonder(govde, dugme) {
    busy(dugme, true, 'Doğrulanıyor…');
    return api('POST', '/api/setup/verify-owner', govde).then(function (data) {
      alertBox('ok', data.mesaj);
      return teshisYukle().then(function () {
        var sonra = sahiplikSonrasi;
        sahiplikSonrasi = null;
        if (sonra) sonra();
      });
    }).catch(failBox).then(function () { busy(dugme, false); });
  }

  // ─────────── Ayar dosyası onarımı ───────────

  function configOnarAc(appKeyKaniti) {
    onarimPaneliniGoster('config-onar');
    $('config-onar-kanit').hidden = !appKeyKaniti;
    $('config-onar-manuel').hidden = true;
    $('config-onar-not').textContent = appKeyKaniti
      ? 'Mevcut ayar dosyasındaki APP_KEY KORUNUR — şifreli verileriniz (2FA, API anahtarları) '
        + 'açılmaya devam eder. Yalnız bağlantı bilgileri değişir.'
      : 'Ayar dosyasında APP_KEY kalmadıysa YENİ anahtar üretilir; eski anahtarla şifrelenmiş '
        + 'veriler (2FA gizli anahtarı, API anahtarları) çözülemez ve yeniden girilmeleri gerekir. '
        + 'Veritabanında zaten bir kurulum varsa yönetici e-postası ve şifresi sorulur.';
  }

  // ─────────── Güncelleme ───────────

  function guncelleAc() {
    onarimPaneliniGoster('guncelle');
    $('guncelle-sonuc').hidden = true;
    var bekleyen = teshis ? teshis.sema.bekleyen_sayisi : 0;
    $('guncelle-ozet').textContent = teshis
      ? ('Dosyalar ' + teshis.surum.dosya + ' sürümünde, veritabanı '
         + (teshis.surum.kurulu || 'kayıtsız') + '. ' + bekleyen
         + ' migration koşacak. Veriye dokunulmaz.')
      : '';
  }

  function onarimBagla() {
    $('teshis-yenile').addEventListener('click', function () {
      clearAlert();
      teshisYukle().catch(failBox);
    });

    $('sahiplik-vazgec').addEventListener('click', function () { teshisCiz(teshis); });

    // B14: e-posta yazılınca hesapta 2FA olup olmadığı sorulur ve kod alanı
    // yalnız gerekiyorsa açılır. Sorgu SIR SIZDIRMAZ: yalnız "kod gerekir mi"
    // bilgisini döner, hesabın var olup olmadığını değil (yoksa da false döner).
    $('sahiplik-form').email.addEventListener('blur', function (event) {
      var eposta = event.target.value.trim();
      if (!eposta) { $('sahiplik-kod-alani').hidden = true; return; }
      api('POST', '/api/setup/owner-check', { email: eposta }).then(function (data) {
        $('sahiplik-kod-alani').hidden = !data.iki_adimli;
      }).catch(function () { /* sorgu başarısızsa alan gizli kalır; kod yine gönderilebilir */ });
    });

    $('sahiplik-form').addEventListener('submit', function (event) {
      event.preventDefault();
      clearAlert();
      var form = event.target;
      sahiplikGonder({
        yontem: 'admin',
        email: form.email.value.trim(),
        sifre: form.sifre.value,
        kod: form.kod ? form.kod.value.trim() : ''
      }, form.querySelector('button[type="submit"]'));
    });

    $('sahiplik-appkey-gonder').addEventListener('click', function (event) {
      clearAlert();
      var anahtar = $('sahiplik-app-key').value.trim();
      if (!anahtar) { alertBox('bad', 'APP_KEY boş olamaz.'); return; }
      sahiplikGonder({ yontem: 'app_key', app_key: anahtar }, event.target);
    });

    // Yıkıcı yol: yazarak onay olmadan düğme açılmaz.
    $('yikici-onay').addEventListener('input', function (event) {
      $('yikici-calistir').disabled = event.target.value !== 'SIFIRLA';
    });
    $('yikici-vazgec').addEventListener('click', function () { teshisCiz(teshis); });
    $('yikici-calistir').addEventListener('click', function (event) {
      clearAlert();
      if (!window.confirm('Veritabanındaki TÜM tablolar silinecek. Bu işlem geri alınamaz. Devam?')) return;
      var dugme = event.target;
      busy(dugme, true, 'Sıfırlanıyor…');
      api('POST', '/api/setup/migrate', { fresh: true, confirm: 'SIFIRLA' }).then(function () {
        alertBox('ok', 'Tablolar sıfırlandı ve yeniden kuruldu. Yönetici hesabı adımına geçiliyor.');
        showStep('admin');
      }).catch(function (error) {
        // APP_KEY kanıtı isteniyorsa sahiplik ekranına yönlendir — çıkmaz sokak yok.
        if (error.code === 'FORBIDDEN') { sahiplikAc(function () { onarimPaneliniGoster('yikici'); }); }
        failBox(error);
      }).then(function () { busy(dugme, false); });
    });

    $('config-onar-vazgec').addEventListener('click', function () { teshisCiz(teshis); });

    $('config-onar-form').addEventListener('submit', function (event) {
      event.preventDefault();
      clearAlert();
      var form = event.target;
      var dugme = form.querySelector('button[type="submit"]');
      busy(dugme, true, 'Test ediliyor…');
      api('POST', '/api/setup/config-repair', {
        host: form.host.value.trim(),
        port: form.port.value.trim(),
        name: form.name.value.trim(),
        user: form.user.value.trim(),
        pass: form.pass.value,
        app_key: form.app_key ? form.app_key.value.trim() : '',
        email: '',
        sifre: ''
      }).then(function (data) {
        alertBox(data.yeni_app_key ? 'warn' : 'ok', data.uyari);
        if (data.manual) {
          $('config-onar-yonerge').textContent = data.instructions;
          $('config-onar-icerik').textContent = data.content;
          $('config-onar-manuel').hidden = false;
        } else {
          teshisYukle().catch(failBox);
        }
      }).catch(function (error) {
        showFieldErrors(form, error.fields);
        failBox(error);
      }).then(function () { busy(dugme, false); });
    });

    $('config-onar-kopyala').addEventListener('click', function () {
      var icerik = $('config-onar-icerik').textContent;
      if (navigator.clipboard) {
        navigator.clipboard.writeText(icerik).then(function () { alertBox('ok', 'İçerik kopyalandı.'); });
      }
    });

    $('config-onar-dogrula').addEventListener('click', function (event) {
      clearAlert();
      var dugme = event.target;
      busy(dugme, true, 'Doğrulanıyor…');
      api('POST', '/api/setup/config-repair/verify', {}).then(function () {
        alertBox('ok', 'Ayar dosyası doğrulandı.');
        return teshisYukle();
      }).catch(failBox).then(function () { busy(dugme, false); });
    });

    $('guncelle-vazgec').addEventListener('click', function () { teshisCiz(teshis); });

    $('guncelle-calistir').addEventListener('click', function (event) {
      clearAlert();
      var dugme = event.target;
      busy(dugme, true, 'Güncelleniyor…');
      api('POST', '/api/setup/update', {}).then(function (data) {
        var satirlar = [
          ['Önceki sürüm', data.onceki_surum || 'kayıtsız'],
          ['Yeni sürüm', data.yeni_surum],
          ['Koşan migration', String(data.uygulanan.length)],
          ['Kalan', String(data.kalan.length)]
        ];
        replaceContent($('guncelle-sonuc'), definitions(satirlar));
        $('guncelle-sonuc').hidden = false;
        alertBox('ok', 'Güncelleme tamamlandı.');
      }).catch(failBox).then(function () { busy(dugme, false); });
    });
  }

  // ─────────── Bağlama ───────────

  function bind() {
    $('req-recheck').addEventListener('click', function () {
      clearAlert();
      loadRequirements().catch(function (error) { failBox(error); });
    });

    $('req-next').addEventListener('click', function () {
      clearAlert();
      showStep('database');
    });

    $('db-form').addEventListener('submit', function (event) {
      event.preventDefault();
      clearAlert();
      var form = event.target;
      var button = form.querySelector('button');
      var payload = {
        host: form.host.value.trim(),
        port: form.port.value.trim(),
        name: form.name.value.trim(),
        user: form.user.value.trim(),
        pass: form.pass.value
      };
      busy(button, true, 'Bağlanılıyor…');
      api('POST', '/api/setup/database', payload).then(function (data) {
        showFieldErrors(form, {});
        replaceContent($('db-result'), definitions([
          ['Sunucu sürümü', data.version],
          ['Karakter seti', data.charset]
        ]));
        alertBox('ok', 'Bağlantı başarılı: ' + data.version);
        showStep('env');
      }).catch(function (error) {
        showFieldErrors(form, error.fields);
        failBox(error);
      }).finally(function () { busy(button, false); });
    });

    $('env-form').addEventListener('submit', function (event) {
      event.preventDefault();
      clearAlert();
      var form = event.target;
      var button = form.querySelector('button');
      busy(button, true, 'Yazılıyor…');
      api('POST', '/api/setup/env', { app_url: form.app_url.value.trim() }).then(function (data) {
        showFieldErrors(form, {});
        if (data.existing) {
          // K45: sunucuda config.php/.env zaten var — aynen kullanılır, adım geçilir.
          alertBox('ok', 'Mevcut yapılandırma dosyası bulundu ve kullanılacak.');
          showStep('migrate');
          return;
        }
        if (data.manual) {
          // K44: kök yazılamıyor — WordPress wp-config.php modeli: içerik ekranda,
          // kullanıcı File Manager ile config.php olarak kaydeder, sonra doğrular.
          $('manual-instructions').textContent = data.instructions;
          $('config-content').textContent = data.content;
          $('manual-box').hidden = false;
          alertBox('warn', 'Klasör yazılabilir değil: içeriği kopyalayıp "' + data.filename + '" olarak kaydedin, sonra doğrulayın.');
          return;
        }
        replaceContent($('env-result'), definitions([
          ['Panel adresi', data.app_url],
          ['Güvenlik anahtarı', 'üretildi ve config.php dosyasına yazıldı']
        ]));
        showStep('migrate');
      }).catch(function (error) {
        showFieldErrors(form, error.fields);
        failBox(error);
      }).finally(function () { busy(button, false); });
    });

    $('config-copy').addEventListener('click', function () {
      var content = $('config-content').textContent;
      if (navigator.clipboard) {
        navigator.clipboard.writeText(content).then(function () {
          alertBox('ok', 'config.php içeriği panoya kopyalandı. File Manager ile uygulama köküne kaydedin.');
        });
      } else {
        alertBox('warn', 'Tarayıcı kopyalamaya izin vermedi; içeriği elle seçip kopyalayın.');
      }
    });

    $('config-verify').addEventListener('click', function (event) {
      clearAlert();
      var button = event.target;
      busy(button, true, 'Doğrulanıyor…');
      api('POST', '/api/setup/env/verify', {}).then(function () {
        alertBox('ok', 'config.php doğrulandı.');
        $('manual-box').hidden = true;
        showStep('migrate');
      }).catch(function (error) {
        failBox(error);
      }).finally(function () { busy(button, false); });
    });

    // K46: buton, kutuya BİREBİR "SIFIRLA" yazılmadan pasiftir.
    $('fresh-confirm').addEventListener('input', function (event) {
      $('migrate-fresh').disabled = event.target.value.trim() !== 'SIFIRLA';
    });

    // K45 temiz kurulum + K46 kapısı: yazılı onay ("SIFIRLA") + sahiplik kanıtı.
    $('migrate-fresh').addEventListener('click', function (event) {
      clearAlert();
      var button = event.target;
      busy(button, true, 'Sıfırlanıyor…');
      var payload = { fresh: true, confirm: $('fresh-confirm').value.trim() };
      var appKey = $('fresh-app-key').value.trim();
      if (appKey) payload.app_key = appKey;
      api('POST', '/api/setup/migrate', payload).then(function (data) {
        alertBox('ok', 'Temiz kurulum tamam: ' + data.applied.length + ' migration uygulandı.');
        showStep('admin');
      }).catch(function (error) {
        showFieldErrors($('panel-migrate'), error.fields);
        failBox(error);
      }).finally(function () { busy(button, false); });
    });

    $('migrate-run').addEventListener('click', function (event) {
      clearAlert();
      var button = event.target;
      busy(button, true, 'Tablolar oluşturuluyor…');
      api('POST', '/api/setup/migrate', {}).then(function (data) {
        var rows = data.migrations.map(function (migration) {
          return [migration.name, migration.execution_ms + ' ms'];
        });
        replaceContent($('migrate-result'), definitions(rows.length ? rows : [['Durum', 'Uygulanacak migration yoktu']]));
        alertBox('ok', data.applied.length
          ? data.applied.length + ' migration uygulandı.'
          : 'Tablolar zaten güncel.');
        showStep('admin');
      }).catch(function (error) {
        failBox(error);
      }).finally(function () { busy(button, false); });
    });

    $('admin-form').addEventListener('submit', function (event) {
      event.preventDefault();
      clearAlert();
      var form = event.target;
      var button = form.querySelector('button');
      busy(button, true);
      api('POST', '/api/setup/admin', {
        email: form.email.value.trim(),
        password: form.password.value
      }).then(function (data) {
        showFieldErrors(form, {});
        $('qr').src = data.qr_svg;
        $('manual-key').textContent = data.manual_key;
        $('totp-box').hidden = false;
        form.querySelector('button').disabled = true;
        $('totp-form').code.focus();
      }).catch(function (error) {
        showFieldErrors(form, error.fields);
        failBox(error);
      }).finally(function () { busy(button, false); });
    });

    // K45: 2FA opsiyonel — atla: kullanıcı TOTP'siz oluşturulur, kurulum hemen kilitlenir.
    $('admin-skip-totp').addEventListener('click', function (event) {
      clearAlert();
      var form = $('admin-form');
      var button = event.target;
      busy(button, true, 'Kuruluyor…');
      api('POST', '/api/setup/admin', {
        email: form.email.value.trim(),
        password: form.password.value,
        skip_totp: true
      }).then(function () {
        return api('POST', '/api/setup/finish', {});
      }).then(function (data) {
        var rows = [['PHP', data.php_version]];
        if (data.db_version) rows.push(['Veritabanı', data.db_version]);
        rows.push(['Tablolar', data.migrations.length + ' migration uygulandı']);
        rows.push(['Giriş', 'şifre ile (2FA atlandı — panelden sonra eklenebilir)']);
        rows.push(['Sihirbaz', 'kalıcı olarak kilitlendi']);
        rows.push(['İşlem günlüğü', progressSummary()]);
        replaceContent($('done-summary'), definitions(rows));
        showStep('done');
      }).catch(function (error) {
        showFieldErrors(form, error.fields);
        failBox(error);
      }).finally(function () { busy(button, false); });
    });

    $('totp-form').addEventListener('submit', function (event) {
      event.preventDefault();
      clearAlert();
      var form = event.target;
      var button = form.querySelector('button');
      busy(button, true, 'Doğrulanıyor…');
      api('POST', '/api/setup/admin/verify', { code: form.code.value.trim() }).then(function (data) {
        showFieldErrors(form, {});
        var list = $('codes');
        list.textContent = '';
        data.recovery_codes.forEach(function (code) {
          var item = document.createElement('li');
          item.textContent = code;
          list.appendChild(item);
        });
        showStep('recovery');
      }).catch(function (error) {
        showFieldErrors(form, error.fields);
        failBox(error);
      }).finally(function () { busy(button, false); });
    });

    $('copy-codes').addEventListener('click', function () {
      var codes = Array.prototype.map.call($('codes').children, function (item) { return item.textContent; }).join('\n');
      if (navigator.clipboard) {
        navigator.clipboard.writeText(codes).then(function () {
          alertBox('ok', 'Kodlar panoya kopyalandı. Kalıcı bir yere yapıştırmayı unutmayın.');
        });
      } else {
        alertBox('warn', 'Tarayıcı kopyalamaya izin vermedi; kodları elle not alın.');
      }
    });

    $('codes-saved').addEventListener('change', function (event) {
      $('finish').disabled = !event.target.checked;
    });

    $('finish').addEventListener('click', function (event) {
      clearAlert();
      var button = event.target;
      busy(button, true, 'Kilitleniyor…');
      api('POST', '/api/setup/finish', { codes_saved: true }).then(function (data) {
        var rows = [['PHP', data.php_version]];
        if (data.db_version) rows.push(['Veritabanı', data.db_version]);
        rows.push(['Tablolar', data.migrations.length + ' migration uygulandı']);
        rows.push(['Sihirbaz', 'kalıcı olarak kilitlendi']);
        rows.push(['İşlem günlüğü', progressSummary()]);
        replaceContent($('done-summary'), definitions(rows));
        showStep('done');
      }).catch(function (error) {
        failBox(error);
      }).finally(function () { busy(button, false); });
    });
  }

  // ─────────── Başlat ───────────

  // Kopyalama düğmesi bind()'den bağımsız: açılış bile başarısız olsa rapor alınabilir (K42).
  $('diag-copy').addEventListener('click', copyDiagnosticReport);

  // K45+K46: kilit kaldırma — çıkmaz sokak yok AMA sahiplik kanıtı (APP_KEY) şart.
  $('unlock-run').addEventListener('click', function (event) {
    var appKey = $('unlock-app-key').value.trim();
    if (!appKey) { alertBox('bad', 'Sahiplik kanıtı gerekli: config.php içindeki APP_KEY değerini girin.'); return; }
    if (!window.confirm('Bu tarayıcı için 15 dakikalık bir yeniden kurulum bileti alınacak ve sihirbaz '
      + 'yeniden çalışacak. Kurulum kilidi SİLİNMEZ. Devam edilsin mi?')) return;
    var button = event.target;
    busy(button, true, 'Doğrulanıyor…');
    api('POST', '/api/setup/unlock', { app_key: appKey }).then(function () {
      location.reload();
    }).catch(function (error) {
      failBox(error);
    }).finally(function () { busy(button, false); });
  });

  // AÇILIŞ: her şeyden önce TEŞHİS. Eski açılış doğrudan `/api/setup/state`
  // çağırıyordu; o uç yalnız "hangi adımdayız" der ve bozuk bir sistemde
  // yanıltıcıdır (adım "requirements" görünür, oysa veritabanı düşmüştür).
  teshisYukle().then(function (data) {
    bind();
    onarimBagla();

    // Temiz bir sistemde teşhis ekranında oyalanmayız: doğrudan kuruluma gireriz.
    if (data.durum === 'KURULUM_YOK' && !data.config.var) {
      showStep('requirements');
      return loadRequirements();
    }
    if (data.durum === 'YARIM' && data.bilet_var !== false && data.kilit !== 'locked') {
      // Yarım kurulumda da teşhis ekranı gösterilir (seçenek: devam / baştan),
      // ama adım bilgisi hazır dursun.
      return null;
    }
    return null;
  }).catch(function (error) {
    // K45: "kurulum tamamlanmış" 403'ü çıkmaz sokak değil — seçenek sun.
    // İE#19 G1: kilit okunamıyorsa (503) sihirbaz hiç açılmaz — bu bir arıza
    // bildirimidir, "yeniden kur" seçeneği DEĞİL: kanıt olmadan kapı açılmaz.
    if (error && error.code === 'SETUP_STATE_UNKNOWN') {
      alertBox('bad', error.message);
      return;
    }
    // K45: "kurulum tamamlanmış" 403'ü çıkmaz sokak değil — seçenek sun.
    if (error && (error.code === 'FORBIDDEN')
        && typeof error.message === 'string' && error.message.indexOf('Kurulum zaten tamamlanmış') !== -1) {
      $('locked-box').hidden = false;
      alertBox('warn', 'Kurulum tamamlanmış görünüyor. Panele gidebilir veya APP_KEY ile 15 dakikalık '
        + 'yeniden kurulum bileti alıp sihirbazı yeniden çalıştırabilirsiniz.');
      return;
    }
    failBox(error);
  });
})();
