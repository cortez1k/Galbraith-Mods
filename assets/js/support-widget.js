(function () {
  var root = document.getElementById('supportWidget');
  if (!root) return;
  var panel = document.getElementById('swPanel'), body = document.getElementById('swBody');
  var launcher = document.getElementById('swLauncher'), closeBtn = document.getElementById('swClose');
  var form = document.getElementById('swForm'), input = document.getElementById('swInput'), dot = document.getElementById('swDot');
  var isHome = root.dataset.home === '1', logged = root.dataset.logged === '1';
  var timer = null, lastCount = null, lastHtml = '';
  var minimized = false;
  try { minimized = sessionStorage.getItem('sw_min') === '1'; } catch (e) {}

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function isOpen() { return root.classList.contains('open'); }

  function render(msgs) {
    var hello = '<div class="sw-hello">👋 Привет! Опиши проблему или вопрос — ответим прямо здесь.</div>';
    var html = hello + msgs.map(function (m) {
      return '<div class="sw-msg ' + (m.is_agent ? 'agent' : 'mine') + '">' +
        (m.message ? '<div>' + esc(m.message) + '</div>' : '') +
        (m.attachment_url ? '<a href="' + esc(m.attachment_url) + '" target="_blank">📎 Изображение</a>' : '') +
        '<small>' + (m.is_agent ? 'Поддержка · ' : '') + esc(m.time) + '</small></div>';
    }).join('');
    if (html !== lastHtml) {
      var near = body.scrollHeight - body.scrollTop - body.clientHeight < 80;
      body.innerHTML = html; lastHtml = html;
      if (near || lastCount === null) body.scrollTop = body.scrollHeight;
    }
    var agentCount = msgs.filter(function (m) { return m.is_agent; }).length;
    if (lastCount !== null && agentCount > lastCount && !isOpen()) dot.hidden = false;
    lastCount = agentCount;
  }

  function poll() {
    if (!logged) return;
    fetch('/api/support-widget', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (j.success) render(j.messages || []); })
      .catch(function () {});
  }

  function open() {
    root.classList.add('open'); dot.hidden = true;
    poll(); clearInterval(timer); timer = setInterval(poll, 4000);
    setTimeout(function () { if (input) input.focus({ preventScroll: true }); }, 250);
  }
  function close(manual) {
    root.classList.remove('open'); clearInterval(timer); timer = setInterval(poll, 15000);
    if (manual && isHome) { try { sessionStorage.setItem('sw_min', '1'); } catch (e) {} }
  }

  launcher.addEventListener('click', function () { try { sessionStorage.removeItem('sw_min'); } catch (e) {} open(); });
  closeBtn.addEventListener('click', function () { close(true); });

  if (form) form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = input.value.trim(); if (!text) return;
    var fd = new FormData(); fd.append('message', text);
    input.value = ''; input.disabled = true;
    fetch('/api/support-widget/send', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (!j.success) { input.value = text; alert(j.error || 'Не удалось отправить'); } else { lastCount = lastCount; poll(); setTimeout(function () { body.scrollTop = body.scrollHeight; }, 300); } })
      .catch(function () { input.value = text; })
      .finally(function () { input.disabled = false; input.focus(); });
  });

  // Главная: изначально открыт (если пользователь не сворачивал в этой сессии). Остальные страницы: свёрнут.
  if (isHome && !minimized) setTimeout(open, 600); else { close(false); poll(); }
})();
