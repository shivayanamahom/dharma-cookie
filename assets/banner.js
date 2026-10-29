/* Тело функции (c) = настройки из PHP. Без // комментариев и с точками с запятой: файл сворачивается в одну строку. */
var d = document, K = c.n;
var rx = new RegExp('(?:^|; )' + K + '=([^;]*)');
var read = function () { var m = rx.exec(d.cookie); return m ? decodeURIComponent(m[1]) : ''; };
var done = function () { return read() === c.v; };
var el = function (tag, cls, txt) { var e = d.createElement(tag); if (cls) { e.className = cls; } if (txt) { e.textContent = txt; } return e; };
var para = function (box, s) {
    var p = el('p', 'dck__p'), re = /\[([^\]]+)\]\(([^)\s]+)\)/g, i = 0, m, a;
    while ((m = re.exec(s))) {
        if (m.index > i) { p.appendChild(d.createTextNode(s.slice(i, m.index))); }
        a = el('a', '', m[1]);
        a.href = m[2];
        p.appendChild(a);
        i = re.lastIndex;
    }
    if (i < s.length) { p.appendChild(d.createTextNode(s.slice(i))); }
    box.appendChild(p);
};
var hide = function () { var e = d.getElementById('dck'); if (e) { e.remove(); } };
var accept = function () {
    d.cookie = K + '=' + encodeURIComponent(c.v) + ';max-age=' + (c.d * 86400) + ';path=' + c.p + ';SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : '');
    hide();
    d.dispatchEvent(new CustomEvent('dharmacookie:accept'));
};
var show = function () {
    if (d.getElementById('dck') || !d.body) { return; }
    var box = el('div', 'dck dck--' + c.pos + ' dck--' + c.th), txt = el('div', 'dck__x'), btn = el('button', 'dck__b' + (c.bc ? ' ' + c.bc : ' dck__ok'), c.b), ic;
    box.id = 'dck';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-label', c.ti);
    txt.appendChild(el('div', 'dck__t', c.ti));
    c.tx.forEach(function (s) { para(txt, s); });
    if (c.ic) { ic = el('span', c.ic); ic.setAttribute('aria-hidden', 'true'); btn.appendChild(ic); }
    btn.type = 'button';
    btn.addEventListener('click', accept);
    box.appendChild(txt);
    box.appendChild(btn);
    d.body.appendChild(box);
};
window.DharmaCookie = { show: show, accepted: done };
d.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-cookie-settings]') : null;
    if (t) { e.preventDefault(); show(); }
});
if (c.t || !done()) {
    if (d.readyState === 'loading') { d.addEventListener('DOMContentLoaded', show); } else { show(); }
}
