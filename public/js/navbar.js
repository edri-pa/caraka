(function () {
    var nav = document.querySelector('.nav-kaca');
    if (!nav) { return; }

    var daftar = nav.querySelector('.nav-daftar');
    var cair = nav.querySelector('.nav-cair');
    if (!daftar || !cair) { return; }

    var tautan = Array.prototype.slice.call(daftar.querySelectorAll('a'));
    var aktif = daftar.querySelector('a[aria-current="page"]');
    var kurangiGerak = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var KUNCI = 'caraka_nav_cair';

    function ukur(el) {
        var d = daftar.getBoundingClientRect();
        var r = el.getBoundingClientRect();
        return { x: r.left - d.left, w: r.width };
    }

    function pasang(p) {
        cair.style.setProperty('--x', p.x + 'px');
        cair.style.setProperty('--w', p.w + 'px');
        cair.classList.add('siap');
    }

    function ke(el) {
        if (!el) { cair.classList.remove('siap'); return; }
        pasang(ukur(el));
    }

    nav.classList.add('js');

    // Gerak cair antarhalaman: mulai dari posisi tautan aktif di halaman sebelumnya.
    var awal = null;
    try {
        awal = JSON.parse(sessionStorage.getItem(KUNCI) || 'null');
        sessionStorage.removeItem(KUNCI);
    } catch (e) {}

    if (aktif && awal && !kurangiGerak) {
        var transisi = cair.style.transition;
        cair.style.transition = 'none';
        pasang(awal);
        void cair.offsetWidth;
        cair.style.transition = transisi;
    }

    ke(aktif);

    tautan.forEach(function (a) {
        a.addEventListener('mouseenter', function () { ke(a); });
        a.addEventListener('focus', function () { ke(a); });
        a.addEventListener('click', function () {
            try {
                if (aktif && a !== aktif) { sessionStorage.setItem(KUNCI, JSON.stringify(ukur(aktif))); }
            } catch (e) {}
        });
    });

    daftar.addEventListener('mouseleave', function () { ke(aktif); });
    daftar.addEventListener('focusout', function () { ke(aktif); });
    window.addEventListener('resize', function () { ke(aktif); });
    window.addEventListener('load', function () { ke(aktif); });

    // Di HP, sembunyikan bilah bawah saat papan ketik terbuka agar tidak menutupi isian.
    document.addEventListener('focusin', function (e) {
        if (e.target && e.target.matches && e.target.matches('input, textarea, select')) {
            nav.classList.add('tersembunyi');
        }
    });
    document.addEventListener('focusout', function () { nav.classList.remove('tersembunyi'); });
})();
