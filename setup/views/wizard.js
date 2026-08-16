/* tedarikapp kurulum sihirbazı — bağımlılıksız, tek dosya.
 *
 * Güvenlik başlıkları satır içi script'e izin vermediği için bu dosya ayrı uçtan servis edilir.
 * Sunucudan gelen hiçbir metin innerHTML ile basılmaz (XSS) — textContent kullanılır.
 */
(function () {
  'use strict';

  var csrfToken = '';
  var STEPS = ['requirements', 'database', 'env', 'migrate', 'admin', 'recovery', 'done'];

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
    return fetch(path, options).then(function (response) {
      return response.json().catch(function () { return null; }).then(function (payload) {
        if (payload && payload.success) return payload.data;
        var error = new Error((payload && payload.error && payload.error.message) || 'Beklenmeyen bir hata oluştu.');
        error.fields = (payload && payload.error && payload.error.fields) || {};
        throw error;
      });
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

  function clearAlert() { $('alert').hidden = true; }

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
    return api('GET', '/api/setup/requirements').then(function (data) {
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
        items.push({
          state: directory.ok ? 'pass' : 'fail',
          title: directory.path + ' yazılabilir',
          hint: directory.hint
        });
      });

      replaceContent(body, checklist(items));
      $('req-next').disabled = !data.ok;

      if (!data.ok) alertBox('bad', 'Eksikler var. Yukarıdaki maddeleri giderip "Yeniden denetle" deyin.');
      else if (data.warnings.length) alertBox('warn', data.warnings.join(' '));
      else clearAlert();
    });
  }

  // ─────────── Bağlama ───────────

  function bind() {
    $('req-recheck').addEventListener('click', function () {
      clearAlert();
      loadRequirements().catch(function (error) { alertBox('bad', error.message); });
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
        alertBox('bad', error.message);
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
        replaceContent($('env-result'), definitions([
          ['Panel adresi', data.app_url],
          ['Güvenlik anahtarları', 'üretildi ve .env dosyasına yazıldı']
        ]));
        showStep('migrate');
      }).catch(function (error) {
        showFieldErrors(form, error.fields);
        alertBox('bad', error.message);
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
        alertBox('bad', error.message);
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
        alertBox('bad', error.message);
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
        alertBox('bad', error.message);
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
        replaceContent($('done-summary'), definitions(rows));
        showStep('done');
      }).catch(function (error) {
        alertBox('bad', error.message);
      }).finally(function () { busy(button, false); });
    });
  }

  // ─────────── Başlat ───────────

  api('GET', '/api/setup/state').then(function (data) {
    csrfToken = data.csrf_token;
    bind();
    showStep(data.step === 'done' ? 'done' : data.step);
    if (data.step === 'requirements') {
      return loadRequirements();
    }
    return null;
  }).catch(function (error) {
    alertBox('bad', error.message);
  });
})();
