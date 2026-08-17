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

  function showStep(step) {
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

    // K45 temiz kurulum: önceki yarım denemeden kalan tablolar "already exists"
    // veriyorsa tek tıkla sıfırla + kur (açık onay istenir — TÜM tablolar silinir).
    $('migrate-fresh').addEventListener('click', function (event) {
      if (!window.confirm('Veritabanındaki TÜM tablolar SİLİNECEK ve sıfırdan kurulacak. Emin misiniz?')) return;
      clearAlert();
      var button = event.target;
      busy(button, true, 'Sıfırlanıyor…');
      api('POST', '/api/setup/migrate', { fresh: true }).then(function (data) {
        alertBox('ok', 'Temiz kurulum tamam: ' + data.applied.length + ' migration uygulandı.');
        showStep('admin');
      }).catch(function (error) {
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

  // K45: kilit kaldırma — kurulum tamamlanmışken bile yeniden kurulabilme.
  $('unlock-run').addEventListener('click', function (event) {
    if (!window.confirm('Kurulum kilidi kaldırılacak ve sihirbaz yeniden çalışacak. Emin misiniz?')) return;
    var button = event.target;
    busy(button, true, 'Kaldırılıyor…');
    api('POST', '/api/setup/unlock', {}).then(function () {
      location.reload();
    }).catch(function (error) {
      failBox(error);
    }).finally(function () { busy(button, false); });
  });

  api('GET', '/api/setup/state').then(function (data) {
    csrfToken = data.csrf_token;
    bind();
    showStep(data.step === 'done' ? 'done' : data.step);
    if (data.step === 'requirements') {
      return loadRequirements();
    }
    return null;
  }).catch(function (error) {
    // K45: "kurulum tamamlanmış" 403'ü çıkmaz sokak değil — seçenek sun.
    if (error && typeof error.message === 'string' && error.message.indexOf('kalıcı olarak kapalı') !== -1) {
      $('locked-box').hidden = false;
      alertBox('warn', 'Kurulum tamamlanmış görünüyor. Panele gidebilir veya kilidi kaldırıp yeniden kurabilirsiniz.');
      return;
    }
    failBox(error);
  });
})();
