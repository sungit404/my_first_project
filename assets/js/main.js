// Modal helpers — open/close overlay boxes by id (admin pages: amenities, room types, users)
function openModal(id) {
    var m = document.getElementById(id);
    if (!m) return;
    m.classList.add('open');
    var f = m.querySelector('input:not([type=hidden]), textarea, select');
    if (f) setTimeout(function () { f.focus(); }, 50);
}
function closeModal(id) {
    var m = document.getElementById(id);
    if (m) m.classList.remove('open');
}
// Click on the dark backdrop closes the modal (clicks inside .modal-box do not)
document.addEventListener('click', function (e) {
    if (e.target.classList && e.target.classList.contains('modal-overlay')) e.target.classList.remove('open');
});

// Public navbar: hamburger dropdown on phones
(function () {
    var burger = document.getElementById('navBurger');
    var links = document.querySelector('.nav-links');
    if (!burger || !links) return;
    var close = function () { links.classList.remove('open'); burger.setAttribute('aria-expanded', 'false'); };
    burger.addEventListener('click', function () {
        var isOpen = links.classList.toggle('open');
        burger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    links.addEventListener('click', function (e) {
        if (e.target && e.target.tagName === 'A') close();
    });
    document.addEventListener('click', function (e) {
        if (!links.classList.contains('open')) return;
        if (!links.contains(e.target) && !burger.contains(e.target)) close();
    });
})();

// Find a Room: mobile filters/compare slide-in drawer
(function () {
    var col = document.getElementById('filterCol');
    var backdrop = document.getElementById('drawerBackdrop');
    if (!col || !backdrop) return;
    var close = function () { col.classList.remove('open'); backdrop.classList.remove('open'); };
    var toggle = document.getElementById('filterToggle');
    if (toggle) toggle.addEventListener('click', function () { col.classList.add('open'); backdrop.classList.add('open'); });
    var btn = document.getElementById('filterClose');
    if (btn) btn.addEventListener('click', close);
    backdrop.addEventListener('click', close);
})();

// Scroll reveal for room cards
document.addEventListener('DOMContentLoaded', function () {
    var els = document.querySelectorAll('.room-card');
    if ('IntersectionObserver' in window && els.length) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en, i) {
                if (en.isIntersecting) {
                    setTimeout(function () { en.target.classList.add('in-view'); }, i * 50);
                    io.unobserve(en.target);
                }
            });
        }, { threshold: 0.1 });
        els.forEach(function (el) { io.observe(el); });
    } else { els.forEach(function (el) { el.classList.add('in-view'); }); }

    // Gallery thumbnails
    document.querySelectorAll('.gallery-thumbs img').forEach(function (t) {
        t.addEventListener('click', function () {
            var main = document.getElementById('galleryMain');
            if (main) { main.src = t.dataset.full; }
            document.querySelectorAll('.gallery-thumbs img').forEach(function (x) { x.classList.remove('on'); });
            t.classList.add('on');
        });
    });

    // Limit compare selection to 3
    var boxes = document.querySelectorAll('input[name="ids[]"]');
    boxes.forEach(function (b) {
        b.addEventListener('change', function () {
            var n = document.querySelectorAll('input[name="ids[]"]:checked').length;
            if (n > 3) { b.checked = false; alert('You can compare up to 3 rooms at a time.'); }
        });
    });

    // Amenity price inputs only active when ticked
    document.querySelectorAll('.amen-pick input[type=checkbox]').forEach(function (cb) {
        var price = cb.closest('.row').querySelector('input[type=number]');
        var sync = function () { price.disabled = !cb.checked; };
        cb.addEventListener('change', sync); sync();
    });

    // Password visibility eye — wraps every password input with a show/hide button
    document.querySelectorAll('.field input[type="password"]').forEach(function (input) {
        if (input.closest('.pw-box')) return;
        var field = input.closest('.field');
        var box = document.createElement('div');
        box.className = 'pw-box';
        input.parentNode.insertBefore(box, input);
        box.appendChild(input);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pw-toggle';
        btn.title = 'Show password';
        btn.setAttribute('aria-label', 'Show password');
        btn.innerHTML =
            '<svg class="i-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>' +
            '<svg class="i-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
        box.appendChild(btn);
        if (field) field.classList.add('has-pw');
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.classList.toggle('on', show);
            btn.title = show ? 'Hide password' : 'Show password';
            btn.setAttribute('aria-label', btn.title);
        });
    });

});
