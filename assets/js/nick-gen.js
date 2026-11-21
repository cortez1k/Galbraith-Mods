/* Генератор ников Galbraith. Всегда формат Имя_Фамилия (латиница), не более 24 символов. */
(function () {
  'use strict';
  var CFG = window.NICK_CFG || {};
  var MAX = CFG.maxLen || 24;
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function rnd(n) { return Math.floor(Math.random() * n); }
  function pick(a) { return a[rnd(a.length)]; }
  function cap(w) { return w ? w.charAt(0).toUpperCase() + w.slice(1).toLowerCase() : ''; }
  function toast(m) { if (typeof window.showToast === 'function') window.showToast(m); else alert(m); }
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var ORIGINS = { ru: 'Русские', en: 'Американские', it: 'Итальянские', es: 'Латино', kav: 'Кавказские', ir: 'Ирландские', de: 'Немецкие', jp: 'Японские' };
  var GROUPS = {
    any: { label: 'Любой', o: { ru: 18, en: 24, it: 10, es: 12, kav: 6, ir: 6, de: 8, jp: 4 }, tag: {} },
    gangs: { label: 'Банды', o: { en: 50, es: 32, ru: 8, ir: 5, de: 5 }, tag: { s: 0.3 } },
    mafia: { label: 'Мафия', o: { it: 45, ru: 28, ir: 10, es: 9, jp: 8 }, tag: {} },
    gov: { label: 'Госструктуры', o: { en: 42, ru: 38, de: 20 }, tag: {} },
    elite: { label: 'Элита', o: { en: 40, it: 20, de: 15, ru: 15, es: 10 }, tag: { e: 0.65 } },
    street: { label: 'Улица', o: { en: 40, es: 25, ru: 20, ir: 10, de: 5 }, tag: { s: 0.55 } }
  };
  var ERAS = { modern: 'современное', retro: 'раритетное' };
  var LEN = { any: [7, MAX], short: [7, 12], long: [17, MAX] };
  var SKIN_CLASSES = { gangs: ['a', 's'], mafia: ['m'], gov: ['g'], elite: ['m'], street: ['s', 'a', 'c'] };
  var BANNED = ['carl_johnson', 'tommy_vercetti', 'claude_speed', 'niko_bellic', 'vito_corleone', 'michael_corleone', 'tony_montana', 'al_capone', 'pablo_escobar', 'walter_white', 'jesse_pinkman', 'tyler_durden', 'harry_potter', 'john_wick', 'james_bond', 'jack_sparrow', 'sam_fisher', 'donald_trump', 'joe_biden', 'barack_obama', 'vladimir_putin', 'elon_musk', 'adolf_hitler', 'george_floyd', 'bill_gates', 'mark_zuckerberg'];

  /* ---------- Состояние ---------- */
  var S = {
    f: { group: 'any', era: 'any', origin: 'any', length: 'any', gender: 'any' },
    lock: { first: false, last: false },
    cur: null, results: [], skinMode: 'style', skin: null, spinning: false,
    fav: [], hist: [], stats: { generated: 0, copied: 0, cards: 0, shares: 0, customs: 0, favorites: 0 },
    top: [], logged: !!CFG.logged
  };
  var D = { first: {}, last: {}, byFirst: {}, byLast: {}, allF: [], allL: [] };
  var SK = [];

  /* ---------- Хранилище ---------- */
  function lsGet(k, d) { try { var v = JSON.parse(localStorage.getItem(k)); return v == null ? d : v; } catch (e) { return d; } }
  function lsSet(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }

  /* ---------- Словарь ---------- */
  function buildDict(p) {
    D = { first: {}, last: {}, byFirst: {}, byLast: {}, allF: [], allL: [] };
    p.f.forEach(function (r) {
      var w = r[0], o = r[1], g = r[2], e = r[3];
      var a = D.first[o] = D.first[o] || {}; var b = a[g] = a[g] || {}; (b[e] = b[e] || []).push(w);
      (D.byFirst[w.toLowerCase()] = D.byFirst[w.toLowerCase()] || []).push({ o: o, g: g, e: e });
      D.allF.push(w);
    });
    p.l.forEach(function (r) {
      var w = r[0], o = r[1], t = r[2] || 'p';
      var a = D.last[o] = D.last[o] || {}; (a[t] = a[t] || []).push(w);
      (D.byLast[w.toLowerCase()] = D.byLast[w.toLowerCase()] || []).push({ o: o });
      D.allL.push(w);
    });
  }

  /* ---------- Транслит и формы ---------- */
  var TR = { 'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'yo', 'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm', 'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u', 'ф': 'f', 'х': 'kh', 'ц': 'ts', 'ч': 'ch', 'ш': 'sh', 'щ': 'shch', 'ъ': '', 'ы': 'y', 'ь': '', 'э': 'e', 'ю': 'yu', 'я': 'ya' };
  function cleanWord(s) {
    s = String(s || '').trim().toLowerCase().replace(/[а-яё]/g, function (c) { return TR[c] || ''; }).replace(/[^a-z]/g, '');
    return (s.length < 2 || s.length > 16) ? '' : cap(s);
  }
  function femSurname(s) {
    if (/(skiy|sky)$/.test(s)) return s.replace(/(skiy|sky)$/, 'skaya');
    if (/(ov|ev)$/.test(s)) return s + 'a';
    if (/(in|yn)$/.test(s)) return s + 'a';
    if (/(oy|iy)$/.test(s)) return s.replace(/(oy|iy)$/, 'aya');
    return s;
  }
  function isFemSurname(s) { return /(ova|eva|ina|yna|skaya|aya)$/.test(s.toLowerCase()); }
  function guessOrigin(w) {
    w = w.toLowerCase();
    if (/(yan|ian|dze|shvili|adze|iev|ayev)$/.test(w)) return 'kav';
    if (/(ov|ev|ova|eva|sky|skiy|skaya|enko|uk|yuk|ich|ina|yn)$/.test(w)) return 'ru';
    if (/(ini|etti|elli|oni|ino|ello|etto|zzi|ucci|ieri|ardi|ano|ese)$/.test(w)) return 'it';
    if (/(ez|es|illo|ero|ado|ido|ales)$/.test(w)) return 'es';
    if (/(mann|berg|stein|schmidt|ner|ler)$/.test(w)) return 'de';
    if (/(moto|kawa|yama|shi|saki|mura|hara|ta)$/.test(w)) return 'jp';
    if (/(son|ton|ford|field|well|wood|man|ley)$/.test(w)) return 'en';
    return null;
  }

  /* ---------- Выбор слов ---------- */
  function weighted(w) {
    var sum = 0, k; for (k in w) sum += w[k];
    var r = Math.random() * sum;
    for (k in w) { if (r < w[k]) return k; r -= w[k]; }
    return Object.keys(w)[0];
  }
  function firstPool(o, g, era) {
    var og = D.first[o] && D.first[o][g]; if (!og) return [];
    var pool = [];
    if (era === 'any') { ['modern', 'retro', 'any'].forEach(function (e) { pool = pool.concat(og[e] || []); }); }
    else {
      pool = (og[era] || []).concat(og.any || []);
      if (!pool.length) ['modern', 'retro'].forEach(function (e) { pool = pool.concat(og[e] || []); });
    }
    return pool;
  }
  function lastPool(o, tagPref) {
    var L = D.last[o]; if (!L) return [];
    for (var t in tagPref) { if (L[t] && L[t].length && Math.random() < tagPref[t]) return L[t]; }
    return (L.p && L.p.length) ? L.p : [].concat(L.s || [], L.e || []);
  }
  function pickLookup(list, wantOrigin) {
    if (!list || !list.length) return null;
    if (wantOrigin && wantOrigin !== 'any') { for (var i = 0; i < list.length; i++) if (list[i].o === wantOrigin) return list[i]; }
    return list[0];
  }
  /* Разбор своего слова -> {w, o, g} (o/g могут быть null) */
  function detect(kind, raw) {
    var w = cleanWord(raw); if (!w) return null;
    var hit = pickLookup((kind === 'first' ? D.byFirst : D.byLast)[w.toLowerCase()], S.f.origin);
    var o = hit ? hit.o : guessOrigin(w), g = null;
    if (kind === 'first') {
      g = hit && hit.g ? hit.g : (/(a|ya|ia|ie|ina|ella|elle|ette|ine)$/i.test(w) ? 'f' : 'm');
    } else if (isFemSurname(w) && o === 'ru') g = 'f';
    return { w: w, o: o, g: g, known: !!hit };
  }

  function banned(n) { return BANNED.indexOf(n.toLowerCase()) !== -1; }

  /* ---------- Генерация ---------- */
  function generateOne(fixed) {
    fixed = fixed || {};
    var def = GROUPS[S.f.group] || GROUPS.any, len = LEN[S.f.length] || LEN.any, best = null;
    for (var i = 0; i < 80; i++) {
      var o, g;
      if (fixed.first && fixed.first.o) { o = fixed.first.o; g = fixed.first.g || 'm'; }
      else if (fixed.last && fixed.last.o) { o = fixed.last.o; g = S.f.gender !== 'any' ? S.f.gender : (fixed.last.g || (Math.random() < 0.58 ? 'm' : 'f')); }
      else { o = S.f.origin !== 'any' ? S.f.origin : weighted(def.o); g = S.f.gender !== 'any' ? S.f.gender : (Math.random() < 0.58 ? 'm' : 'f'); }
      if (fixed.first && fixed.first.g) g = fixed.first.g;
      var fw = fixed.first ? fixed.first.w : null, lw = fixed.last ? fixed.last.w : null, fo = o, lo = o;
      if (!fw) { var fp = firstPool(o, g, S.f.era); if (!fp.length) continue; fw = pick(fp); }
      if (!lw) {
        var lp = lastPool(o, def.tag); if (!lp.length) continue; lw = pick(lp);
        if (g === 'f' && o === 'ru') lw = femSurname(lw);
      }
      var total = fw.length + 1 + lw.length;
      var cand = { first: { w: fw, o: fo, g: g }, last: { w: lw, o: lo }, nick: fw + '_' + lw, group: S.f.group, origin: o, gender: g, era: S.f.era, len: total };
      if (total > MAX) continue;
      if (banned(cand.nick)) continue;
      best = best || cand;
      if (total >= len[0] && total <= len[1]) return cand;
    }
    return best;
  }
  function currentFixed() {
    var fx = {};
    var cf = $('#ngCustomFirst').value, cl = $('#ngCustomLast').value;
    if (S.lock.first && S.cur) fx.first = S.cur.first;
    else if (cf) { var a = detect('first', cf); if (a) fx.first = a; }
    if (S.lock.last && S.cur) fx.last = S.cur.last;
    else if (cl) { var b = detect('last', cl); if (b) fx.last = b; }
    return fx;
  }

  /* ---------- Unicode-оформление ---------- */
  function mapAZ(s, up, low, special) {
    return Array.from(s).map(function (c) {
      var code = c.charCodeAt(0);
      if (code >= 65 && code <= 90) { if (special && special[c]) return special[c]; return String.fromCodePoint(up + code - 65); }
      if (code >= 97 && code <= 122) return String.fromCodePoint(low + code - 97);
      return c;
    }).join('');
  }
  var SMALL = 'ᴀʙᴄᴅᴇғɢʜɪᴊᴋʟᴍɴᴏᴘǫʀsᴛᴜᴠᴡxʏᴢ';
  var DS = { C: 'ℂ', H: 'ℍ', N: 'ℕ', P: 'ℙ', Q: 'ℚ', R: 'ℝ', Z: 'ℤ' };
  function fancyList(n) {
    var sp = n.replace('_', ' ');
    return [
      ['Готика', mapAZ(n, 0x1D56C, 0x1D586)], ['Рукопись', mapAZ(n, 0x1D4D0, 0x1D4EA)], ['Двойная линия', mapAZ(n, 0x1D538, 0x1D552, DS)],
      ['Моноширинный', mapAZ(n, 0x1D670, 0x1D68A)], ['Жирный', mapAZ(n, 0x1D5D4, 0x1D5EE)], ['Жирный курсив', mapAZ(n, 0x1D468, 0x1D482)],
      ['Малые капсы', n.toLowerCase().replace(/[a-z]/g, function (c) { return SMALL.charAt(c.charCodeAt(0) - 97); })],
      ['В кружках', mapAZ(n, 0x24B6, 0x24D0)], ['Широкий', mapAZ(n, 0xFF21, 0xFF41).replace('_', '＿')],
      ['С пробелом', sp], ['★彡 рамка 彡★', '★彡' + n + '彡★'], ['Скобки', '【' + n + '】'], ['Точки', '•°' + n + '°•'], ['Звёзды', '✦ ' + sp + ' ✦'], ['Корона', '♛ ' + sp + ' ♛'], ['Молнии', '⚡ ' + sp + ' ⚡']
    ];
  }

  /* ---------- Скины и карточка ---------- */
  var imgCache = {};
  function loadImg(src) {
    if (!imgCache[src]) imgCache[src] = new Promise(function (res) { var i = new Image(); i.onload = function () { res(i); }; i.onerror = function () { res(null); }; i.src = src; });
    return imgCache[src];
  }
  function pickSkin(cur) {
    if (!SK.length) return null;
    var g = cur.gender === 'f' ? 'f' : 'm', cls = (S.skinMode === 'style') ? SKIN_CLASSES[S.f.group] : null;
    var pool = SK.filter(function (r) { return r[1] === g && (!cls || cls.indexOf(r[2]) !== -1); });
    if (!pool.length) pool = SK.filter(function (r) { return r[1] === g; });
    if (!pool.length) pool = SK;
    return pool[rnd(pool.length)][0];
  }
  function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }
  var drawToken = 0;
  function drawCard() {
    var cv = $('#ngCanvas'); if (!cv || !S.cur) return Promise.resolve();
    var token = ++drawToken, cur = S.cur, skinKey = S.skin;
    var fontsReady = (document.fonts && document.fonts.load) ? Promise.all([document.fonts.load('700 80px Rajdhani'), document.fonts.load('600 18px Manrope'), document.fonts.load('600 14px "JetBrains Mono"')]).catch(function () {}) : Promise.resolve();
    return Promise.all([loadImg('/assets/img/nick/logo.png'), skinKey ? loadImg('/assets/img/nick/skins/' + skinKey + '.webp') : Promise.resolve(null), fontsReady]).then(function (r) {
      if (token !== drawToken) return;
      var logo = r[0], skin = r[1], ctx = cv.getContext('2d'), W = 1200, H = 630;
      ctx.clearRect(0, 0, W, H);
      ctx.fillStyle = '#0b0b0d'; ctx.fillRect(0, 0, W, H);
      ctx.strokeStyle = 'rgba(255,255,255,0.045)'; ctx.lineWidth = 1;
      for (var x = 0; x <= W; x += 48) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H); ctx.stroke(); }
      for (var y = 0; y <= H; y += 48) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke(); }
      var g1 = ctx.createRadialGradient(900, 330, 20, 900, 330, 560); g1.addColorStop(0, 'rgba(232,28,90,0.50)'); g1.addColorStop(1, 'rgba(232,28,90,0)');
      ctx.fillStyle = g1; ctx.fillRect(0, 0, W, H);
      var g2 = ctx.createRadialGradient(0, 0, 10, 0, 0, 520); g2.addColorStop(0, 'rgba(232,28,90,0.22)'); g2.addColorStop(1, 'rgba(232,28,90,0)');
      ctx.fillStyle = g2; ctx.fillRect(0, 0, W, H);
      ctx.fillStyle = 'rgba(232,28,90,0.16)'; ctx.beginPath(); ctx.moveTo(760, 0); ctx.lineTo(W, 0); ctx.lineTo(W, H); ctx.lineTo(560, H); ctx.closePath(); ctx.fill();
      ctx.strokeStyle = 'rgba(232,28,90,0.9)'; ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(760, 0); ctx.lineTo(560, H); ctx.stroke();
      if (skin) {
        var sh = 600, sw = skin.width * (sh / skin.height); if (sw > 520) { sw = 520; sh = skin.height * (sw / skin.width); }
        var sx = 905 - sw / 2, sy = H - sh;
        ctx.save(); ctx.imageSmoothingEnabled = true; ctx.imageSmoothingQuality = 'high';
        ctx.shadowColor = 'rgba(232,28,90,0.85)'; ctx.shadowBlur = 46; ctx.drawImage(skin, sx, sy, sw, sh);
        ctx.shadowBlur = 0; ctx.drawImage(skin, sx, sy, sw, sh); ctx.restore();
      }
      var lg = ctx.createLinearGradient(0, H - 150, 0, H); lg.addColorStop(0, 'rgba(11,11,13,0)'); lg.addColorStop(1, 'rgba(11,11,13,0.9)');
      ctx.fillStyle = lg; ctx.fillRect(0, H - 150, 760, 150);
      // шапка: логотип + название сайта
      if (logo) ctx.drawImage(logo, 56, 44, 50, 50 * logo.height / logo.width);
      ctx.fillStyle = '#fff'; ctx.font = '700 38px Rajdhani, sans-serif'; ctx.textBaseline = 'alphabetic';
      ctx.fillText('GALBRAITH', 120, 82);
      ctx.fillStyle = 'rgba(255,255,255,0.55)'; ctx.font = '600 14px "JetBrains Mono", monospace'; ctx.fillText('ГЕНЕРАТОР НИКОВ · SAMP RP', 122, 104);
      // подпись
      ctx.fillStyle = '#E81C5A'; roundRect(ctx, 56, 210, 150, 32, 16); ctx.fill();
      ctx.fillStyle = '#fff'; ctx.font = '700 14px "JetBrains Mono", monospace'; ctx.fillText('ТВОЙ НИК', 78, 232);
      // ник (подгон по ширине)
      var size = 112, text = cur.nick; ctx.font = '700 ' + size + 'px Rajdhani, sans-serif';
      while (ctx.measureText(text).width > 640 && size > 40) { size -= 4; ctx.font = '700 ' + size + 'px Rajdhani, sans-serif'; }
      ctx.save(); ctx.shadowColor = 'rgba(232,28,90,0.75)'; ctx.shadowBlur = 28; ctx.fillStyle = '#fff'; ctx.fillText(text, 56, 330 + (112 - size) * 0.2); ctx.restore();
      // теги
      var tags = [GROUPS[S.f.group] && S.f.group !== 'any' ? GROUPS[S.f.group].label : null, ORIGINS[cur.origin] || null, cur.era && cur.era !== 'any' ? ERAS[cur.era] : null, cur.gender === 'f' ? 'Женский' : 'Мужской'].filter(Boolean);
      var tx = 56; ctx.font = '600 18px Manrope, sans-serif';
      tags.forEach(function (t) {
        var w = ctx.measureText(t.toUpperCase()).width + 30; ctx.strokeStyle = 'rgba(255,255,255,0.35)'; ctx.lineWidth = 1.5; roundRect(ctx, tx, 372, w, 38, 19); ctx.stroke();
        ctx.fillStyle = '#fff'; ctx.fillText(t.toUpperCase(), tx + 15, 397); tx += w + 12;
      });
      // подвал
      ctx.fillStyle = 'rgba(255,255,255,0.8)'; ctx.font = '600 20px "JetBrains Mono", monospace';
      ctx.fillText(location.host + (CFG.path || '/generator-nikov'), 56, H - 48);
      ctx.fillStyle = 'rgba(255,255,255,0.45)'; ctx.font = '600 14px "JetBrains Mono", monospace'; ctx.fillText(cur.nick.length + ' / ' + MAX + ' символов · Galbraith', 56, H - 24);
    });
  }

  /* ---------- Отрисовка UI ---------- */
  function setReel(id, text) { var el = $(id); if (el) el.textContent = text; }
  function renderCurrent() {
    if (!S.cur) return;
    setReel('#ngFirst', S.cur.first.w); setReel('#ngLast', S.cur.last.w);
    var n = S.cur.nick.length; var l = $('#ngLen'); l.textContent = n + ' / ' + MAX; l.className = 'ng-len' + (n > MAX ? ' bad' : '');
    var tags = []; if (S.f.group !== 'any') tags.push(GROUPS[S.f.group].label); tags.push(ORIGINS[S.cur.origin] || ''); if (S.cur.era !== 'any') tags.push(ERAS[S.cur.era]); tags.push(S.cur.gender === 'f' ? 'женский' : 'мужской');
    $('#ngTags').innerHTML = tags.filter(Boolean).map(function (t) { return '<span>' + esc(t) + '</span>'; }).join('');
    var fav = isFav(S.cur.nick); var fb = $('#ngFav'); fb.textContent = (fav ? '★ В избранном' : '☆ В избранное'); fb.setAttribute('aria-pressed', fav ? 'true' : 'false'); fb.classList.toggle('on', fav);
    $$('#ngResults .ng-var').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-n') === S.cur.nick); });
    renderFancy();
  }
  function renderResults() {
    var box = $('#ngResults');
    box.innerHTML = S.results.map(function (r) {
      return '<button type="button" class="ng-var" data-n="' + esc(r.nick) + '"><span>' + esc(r.nick) + '</span><i>' + r.nick.length + '</i></button>';
    }).join('');
  }
  function renderFancy() {
    if (!S.cur) return;
    $('#ngFancy').innerHTML = fancyList(S.cur.nick).map(function (r) {
      return '<div class="ng-fi"><small>' + esc(r[0]) + '</small><span>' + esc(r[1]) + '</span><button type="button" class="btn btn-secondary btn-sm" data-copy="' + esc(r[1]) + '">Копировать</button></div>';
    }).join('');
  }
  function nickChip(n, extra) {
    return '<span class="ng-li"><button type="button" class="ng-li-n" data-use="' + esc(n) + '">' + esc(n) + '</button>' + (extra || '') + '<button type="button" class="ng-li-c" data-copy="' + esc(n) + '" title="Копировать">⧉</button></span>';
  }
  function renderLists() {
    $('#ngFavCount').textContent = S.fav.length;
    $('#ngFavs').innerHTML = S.fav.length ? S.fav.map(function (f) { return nickChip(f.n, '<button type="button" class="ng-li-x" data-unfav="' + esc(f.n) + '" title="Убрать">✕</button>'); }).join('') : '<div class="ng-hint">Пока пусто. Нажмите «☆ В избранное» у понравившегося ника.' + (S.logged ? '' : ' Войдите в аккаунт, чтобы избранное сохранялось на любом устройстве.') + '</div>';
    $('#ngHist').innerHTML = S.hist.length ? S.hist.map(function (h) { return nickChip(h.n); }).join('') : '<div class="ng-hint">История появится после первой генерации.</div>';
    $('#ngTop').innerHTML = S.top.length ? S.top.map(function (t, i) { return nickChip(t.nick, '<em>' + (i + 1) + '</em>'); }).join('') : '<div class="ng-hint">Пока нет данных за неделю.</div>';
  }

  /* ---------- Достижения ---------- */
  var ACH = [
    ['first', '🎲', 'Первый запуск', 'Сгенерировать первый ник', function (s) { return s.generated >= 1; }, function (s) { return [s.generated, 1]; }],
    ['spin100', '🌀', 'Крутильщик', 'Сгенерировать 100 ников', function (s) { return s.generated >= 100; }, function (s) { return [s.generated, 100]; }],
    ['spin500', '⚡', 'Мастер рулетки', 'Сгенерировать 500 ников', function (s) { return s.generated >= 500; }, function (s) { return [s.generated, 500]; }],
    ['fav1', '⭐', 'Нашёл идеальный', 'Добавить ник в избранное', function (s) { return s.favorites >= 1; }, function (s) { return [s.favorites, 1]; }],
    ['fav10', '📚', 'Коллекционер', '10 ников в избранном', function (s) { return s.favorites >= 10; }, function (s) { return [s.favorites, 10]; }],
    ['copy10', '📋', 'Копирайтер', 'Скопировать 10 ников', function (s) { return s.copied >= 10; }, function (s) { return [s.copied, 10]; }],
    ['card5', '🖼', 'Дизайнер', 'Скачать 5 карточек', function (s) { return s.cards >= 5; }, function (s) { return [s.cards, 5]; }],
    ['share1', '🔗', 'Делюсь', 'Поделиться подборкой', function (s) { return s.shares >= 1; }, function (s) { return [s.shares, 1]; }],
    ['custom1', '✍', 'Своё слово', 'Подобрать пару к своему имени или фамилии', function (s) { return s.customs >= 1; }, function (s) { return [s.customs, 1]; }]
  ];
  function achDone() { var o = {}; ACH.forEach(function (a) { o[a[0]] = a[4](S.stats); }); return o; }
  var prevAch = null;
  function renderAch(announce) {
    var done = achDone();
    $('#ngAch').innerHTML = ACH.map(function (a) {
      var p = a[5](S.stats), pct = Math.min(100, Math.round(p[0] / p[1] * 100)), ok = done[a[0]];
      return '<div class="ng-a' + (ok ? ' ok' : '') + '"><span class="ng-a-i">' + a[1] + '</span><b>' + esc(a[2]) + '</b><small>' + esc(a[3]) + '</small><div class="ng-a-bar"><i style="width:' + pct + '%"></i></div><em>' + Math.min(p[0], p[1]) + ' / ' + p[1] + '</em></div>';
    }).join('');
    if (announce && prevAch) ACH.forEach(function (a) { if (done[a[0]] && !prevAch[a[0]]) toast('🏆 Достижение: ' + a[2]); });
    prevAch = done;
  }
  function saveLocalStats() { if (!S.logged) lsSet('ng_stats', S.stats); }

  /* ---------- Сеть ---------- */
  function post(url, data, beacon) {
    var fd = new FormData(); for (var k in data) fd.append(k, data[k]); fd.append('csrf', CFG.csrf || '');
    if (beacon && navigator.sendBeacon) { navigator.sendBeacon(url, fd); return Promise.resolve(null); }
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': CFG.csrf || '' } }).then(function (r) { return r.json(); }).catch(function () { return null; });
  }
  var pendingGen = 0;
  function flushGen(beacon) { if (pendingGen > 0 && S.logged) { var n = pendingGen; pendingGen = 0; post('/api/nick-event', { type: 'gen', n: n }, beacon); } }
  function bump(type, nick) {
    var map = { copy: 'copied', card: 'cards', share: 'shares', custom: 'customs' };
    if (map[type]) S.stats[map[type]]++;
    saveLocalStats(); renderAch(true);
    if (type === 'gen') return;
    if (S.logged || type === 'copy' || type === 'card' || type === 'share') post('/api/nick-event', { type: type, nick: nick || '', style: S.f.group });
  }

  /* ---------- Действия ---------- */
  function isFav(n) { return S.fav.some(function (f) { return f.n === n; }); }
  function toggleFav() {
    if (!S.cur) return; var n = S.cur.nick;
    if (isFav(n)) {
      S.fav = S.fav.filter(function (f) { return f.n !== n; }); if (S.logged) post('/api/nick-fav', { nick: n, action: 'remove' });
    } else {
      S.fav.unshift({ n: n, s: S.skin || '' }); S.fav = S.fav.slice(0, 200);
      if (S.logged) post('/api/nick-fav', { nick: n, action: 'add', skin: S.skin || '' });
    }
    S.stats.favorites = S.fav.length; if (!S.logged) lsSet('ng_fav', S.fav);
    renderCurrent(); renderLists(); renderAch(true);
  }
  function copyText(t, label) {
    function ok() { toast('Скопировано: ' + t); }
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t).then(ok, function () { fallback(); }); else fallback();
    function fallback() { var ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); ok(); } catch (e) { prompt('Скопируйте ник:', t); } document.body.removeChild(ta); }
  }
  function setCurrent(c, opts) {
    opts = opts || {}; S.cur = c;
    if (!opts.keepSkin) S.skin = opts.skin || pickSkin(c);
    renderCurrent(); drawCard();
  }
  function pushHist(c) {
    S.hist = S.hist.filter(function (h) { return h.n !== c.nick; }); S.hist.unshift({ n: c.nick, g: c.group, t: Date.now() }); S.hist = S.hist.slice(0, 20); lsSet('ng_hist', S.hist); renderLists();
  }
  function updateHint() {
    var cf = $('#ngCustomFirst').value.trim(), cl = $('#ngCustomLast').value.trim(), h = $('#ngCustomHint'), parts = [];
    if (cf) { var a = detect('first', cf); parts.push(a ? ('Имя: <b>' + esc(a.w) + '</b>' + (a.o ? ' — ' + esc((ORIGINS[a.o] || '').toLowerCase()) + (a.g === 'f' ? ', женское' : ', мужское') : ' — происхождение не определено, подберём любую фамилию') + (a.known ? '' : ' (нет в словаре)')) : 'Имя: используйте буквы, 2–16 символов'); }
    if (cl) { var b = detect('last', cl); parts.push(b ? ('Фамилия: <b>' + esc(b.w) + '</b>' + (b.o ? ' — ' + esc((ORIGINS[b.o] || '').toLowerCase()) : ' — происхождение не определено')) : 'Фамилия: используйте буквы, 2–16 символов'); }
    var warn = '';
    if (cf && cl) { var f1 = cleanWord(cf), l1 = cleanWord(cl); if (f1 && l1 && banned(f1 + '_' + l1)) warn = ' <span class="warn">⚠ Такое сочетание обычно запрещено правилами серверов.</span>'; }
    h.innerHTML = parts.length ? parts.join('<br>') + warn : 'Введите имя или фамилию — вторая часть подберётся к ней по происхождению и роду.';
  }

  function spin(silent) {
    if (S.spinning || !D.allF.length) return;
    var fixed = currentFixed();
    var main = generateOne(fixed); if (!main) { toast('Не удалось подобрать вариант — смягчите фильтры'); return; }
    var list = [main], seen = {}; seen[main.nick] = 1;
    for (var i = 0; i < 40 && list.length < 8; i++) { var c = generateOne(fixed); if (c && !seen[c.nick]) { seen[c.nick] = 1; list.push(c); } }
    S.results = list; renderResults();
    var fixedAny = (fixed.first || fixed.last);
    if ((fixed.first && !S.lock.first && $('#ngCustomFirst').value) || (fixed.last && !S.lock.last && $('#ngCustomLast').value)) { if (!S.customCounted || S.customCounted !== main.nick) { S.customCounted = main.nick; bump('custom'); } }
    if (!silent) { S.stats.generated++; pendingGen++; saveLocalStats(); if (pendingGen >= 10) flushGen(false); }
    var finish = function () {
      S.spinning = false; $('#ngDisplay').classList.remove('spin');
      setCurrent(main, {}); if (!silent) { pushHist(main); renderAch(true); }
    };
    if (reduceMotion || silent) { finish(); return; }
    S.spinning = true; $('#ngDisplay').classList.add('spin');
    var t0 = Date.now(), dF = S.lock.first || (fixed.first && fixed.first.w) ? 0 : 900, dL = S.lock.last || (fixed.last && fixed.last.w) ? 0 : 1300;
    if (dF) $('#ngFirst').classList.add('rolling'); if (dL) $('#ngLast').classList.add('rolling');
    var timer = setInterval(function () {
      var el = Date.now() - t0;
      if (el < dF) setReel('#ngFirst', pick(D.allF)); else { setReel('#ngFirst', main.first.w); $('#ngFirst').classList.remove('rolling'); }
      if (el < dL) setReel('#ngLast', pick(D.allL)); else { setReel('#ngLast', main.last.w); $('#ngLast').classList.remove('rolling'); }
      if (el >= Math.max(dF, dL) + 40) { clearInterval(timer); finish(); }
    }, 60);
  }

  function share() {
    if (!S.cur) return;
    var list = S.results.length ? S.results.map(function (r) { return r.nick; }) : [S.cur.nick];
    list = [S.cur.nick].concat(list.filter(function (n) { return n !== S.cur.nick; })).slice(0, 8);
    var url = location.origin + (CFG.path || '/generator-nikov') + '?r=' + encodeURIComponent(list.join(',')) + (S.skin ? '&k=' + encodeURIComponent(S.skin) : '');
    copyText(url); bump('share', S.cur.nick);
  }
  function download() {
    if (!S.cur) return; var cv = $('#ngCanvas');
    drawCard().then(function () {
      cv.toBlob(function (b) {
        if (!b) return; var a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = S.cur.nick + '_galbraith.png';
        document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
        bump('card', S.cur.nick);
      }, 'image/png');
    });
  }
  function useNick(n) {
    var p = n.split('_'); if (p.length !== 2) return;
    var f = pickLookup(D.byFirst[p[0].toLowerCase()], S.f.origin), l = pickLookup(D.byLast[p[1].toLowerCase()], S.f.origin);
    var o = (f && f.o) || (l && l.o) || guessOrigin(p[1]) || 'en', g = (f && f.g) || (isFemSurname(p[1]) ? 'f' : 'm');
    setCurrent({ first: { w: p[0], o: o, g: g }, last: { w: p[1], o: o }, nick: n, group: S.f.group, origin: o, gender: g, era: f && f.e && f.e !== 'any' ? f.e : 'any', len: n.length }, {});
    window.scrollTo({ top: $('#tool').offsetTop - 80, behavior: 'smooth' });
  }

  /* ---------- Привязка событий ---------- */
  function bind() {
    $('#ngSpin').addEventListener('click', function () { spin(false); });
    $('#ngCopy').addEventListener('click', function () { if (S.cur) { copyText(S.cur.nick); bump('copy', S.cur.nick); } });
    $('#ngFav').addEventListener('click', toggleFav);
    $('#ngShare').addEventListener('click', share);
    $('#ngDownload').addEventListener('click', download);
    $('#ngReskin').addEventListener('click', function () { if (S.cur) { S.skin = pickSkin(S.cur); drawCard(); } });
    $('#ngSkinMode').addEventListener('click', function () { S.skinMode = S.skinMode === 'style' ? 'random' : 'style'; this.textContent = S.skinMode === 'style' ? 'Скин: по стилю' : 'Скин: случайный'; if (S.cur) { S.skin = pickSkin(S.cur); drawCard(); } });
    ['First', 'Last'].forEach(function (p) {
      var key = p.toLowerCase();
      $('#ngLock' + p).addEventListener('click', function () {
        if (!S.cur) return; S.lock[key] = !S.lock[key]; this.textContent = S.lock[key] ? '🔒' : '🔓'; this.setAttribute('aria-pressed', S.lock[key] ? 'true' : 'false'); this.parentNode.classList.toggle('locked', S.lock[key]);
      });
    });
    $$('.ng-chips').forEach(function (box) {
      box.addEventListener('click', function (e) {
        var b = e.target.closest('.ng-chip'); if (!b) return;
        $$('.ng-chip', box).forEach(function (x) { x.classList.remove('on'); }); b.classList.add('on'); S.f[box.getAttribute('data-filter')] = b.getAttribute('data-v');
      });
    });
    var ci = [$('#ngCustomFirst'), $('#ngCustomLast')];
    ci.forEach(function (i) { i.addEventListener('input', updateHint); i.addEventListener('keydown', function (e) { if (e.key === 'Enter') spin(false); }); });
    $('#ngCustomClear').addEventListener('click', function () { ci[0].value = ''; ci[1].value = ''; updateHint(); });
    $('#ngResults').addEventListener('click', function (e) { var b = e.target.closest('.ng-var'); if (b) { var r = S.results.filter(function (x) { return x.nick === b.getAttribute('data-n'); })[0]; if (r) setCurrent(r, {}); } });
    document.addEventListener('click', function (e) {
      var c = e.target.closest('[data-copy]'); if (c) { copyText(c.getAttribute('data-copy')); var n = c.getAttribute('data-copy'); if (/^[A-Z][a-z]+_[A-Z][a-z]+$/.test(n)) bump('copy', n); return; }
      var u = e.target.closest('[data-use]'); if (u) { useNick(u.getAttribute('data-use')); return; }
      var x = e.target.closest('[data-unfav]'); if (x) { var n2 = x.getAttribute('data-unfav'); S.fav = S.fav.filter(function (f) { return f.n !== n2; }); if (S.logged) post('/api/nick-fav', { nick: n2, action: 'remove' }); else lsSet('ng_fav', S.fav); S.stats.favorites = S.fav.length; renderLists(); renderCurrent(); }
    });
    $$('.ng-tabbar button').forEach(function (b) {
      b.addEventListener('click', function () {
        $$('.ng-tabbar button').forEach(function (x) { x.classList.remove('on'); }); b.classList.add('on');
        $$('.ng-panel').forEach(function (p) { p.classList.toggle('on', p.getAttribute('data-panel') === b.getAttribute('data-tab')); });
      });
    });
    var td = $('#ngTakeDay'); if (td) td.addEventListener('click', function () { var d = $('#ngDay'); useNick(d.getAttribute('data-nick')); var sk = d.getAttribute('data-skin'); if (sk) { S.skin = sk; drawCard(); } });
    var sg = $('#ngSuggest');
    if (sg) sg.addEventListener('submit', function (e) {
      e.preventDefault(); var fd = new FormData(sg), obj = {}; fd.forEach(function (v, k) { obj[k] = v; });
      post('/api/nick-suggest', obj).then(function (r) {
        var m = $('#ngSugMsg'); if (r && r.ok) { m.textContent = 'Спасибо! «' + r.word + '» отправлено на проверку.'; sg.reset(); }
        else { var er = { exists: 'Такое слово уже есть в словаре.', word: 'Некорректное слово.', rate: 'Слишком много предложений, попробуйте завтра.', login: 'Войдите в аккаунт.' }; m.textContent = (r && er[r.error]) || 'Не удалось отправить.'; }
      });
    });
    window.addEventListener('pagehide', function () { flushGen(true); });
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') flushGen(true); });
  }

  /* ---------- Старт ---------- */
  function applyShared() {
    var list = CFG.shared || []; if (!list.length) return false;
    var out = list.map(function (n) {
      var p = n.split('_'), f = pickLookup(D.byFirst[p[0].toLowerCase()]), l = pickLookup(D.byLast[p[1].toLowerCase()]);
      var o = (f && f.o) || (l && l.o) || guessOrigin(p[1]) || 'en', g = (f && f.g) || (isFemSurname(p[1]) ? 'f' : 'm');
      return { first: { w: p[0], o: o, g: g }, last: { w: p[1], o: o }, nick: n, group: 'any', origin: o, gender: g, era: 'any', len: n.length };
    });
    S.results = out; renderResults(); setCurrent(out[0], { skin: CFG.sharedSkin || undefined }); return true;
  }
  function init() {
    S.hist = lsGet('ng_hist', []);
    var stateP = fetch('/api/nick-state', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).catch(function () { return null; });
    var wordsP = fetch('/api/nick-words').then(function (r) { return r.json(); });
    var skinsP = fetch('/assets/img/nick/skins.json').then(function (r) { return r.json(); }).catch(function () { return []; });
    Promise.all([wordsP, skinsP, stateP]).then(function (r) {
      buildDict(r[0]); SK = r[1] || []; var st = r[2];
      if (st && st.ok) {
        S.logged = !!st.logged; S.top = st.top || [];
        if (S.logged) { S.fav = (st.favorites || []).map(function (f) { return { n: f.nick, s: f.skin }; }); S.stats = st.stats; }
      }
      if (!S.logged) { S.fav = lsGet('ng_fav', []); var ls = lsGet('ng_stats', null); if (ls) for (var k in ls) S.stats[k] = ls[k]; S.stats.favorites = S.fav.length; }
      bind(); renderLists(); prevAch = achDone(); renderAch(false); updateHint();
      if (!applyShared()) { spin(true); }
    }).catch(function () { var h = $('#ngCustomHint'); if (h) h.textContent = 'Не удалось загрузить словарь. Обновите страницу.'; });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
