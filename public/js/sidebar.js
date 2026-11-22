(function () {
    'use strict';

    var side = document.getElementById('sidebar');

    if (!side) {
        return;
    }

    var body = document.body;
    var tutup = document.getElementById('side-tutup');
    var buka = document.getElementById('side-buka');
    var tirai = document.getElementById('side-tirai');
    var menu = side.querySelector('.side-menu');
    var cair = side.querySelector('.side-cair');
    var mq = window.matchMedia('(min-width: 900px)');
    var KUNCI = 'caraka_menu';
    var KUNCI_CAIR = 'caraka_side_cair';
    var kurangiGerak = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function desktop() {
        return mq.matches;
    }

    function terbuka() {
        var nilai = body.getAttribute('data-menu');

        return desktop() ? nilai !== 'tersembunyi' : nilai === 'terbuka';
    }

    function perbaruiAria() {
        var nilai = terbuka() ? 'true' : 'false';

        if (tutup) { tutup.setAttribute('aria-expanded', nilai); }
        if (buka) { buka.setAttribute('aria-expanded', nilai); }
    }

    function beritahuUkuran() {
        // Peta (Leaflet) perlu tahu bahwa lebar kolom isi berubah setelah animasi selesai.
        setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 400);
    }

    function atur(bukaMenu, simpan) {
        body.setAttribute('data-menu', bukaMenu ? 'terbuka' : 'tersembunyi');

        if (simpan && desktop()) {
            try { localStorage.setItem(KUNCI, bukaMenu ? 'terbuka' : 'tersembunyi'); } catch (e) {}
        }

        perbaruiAria();
        beritahuUkuran();
    }

    function awal() {
        var pilihan = null;

        try { pilihan = localStorage.getItem(KUNCI); } catch (e) {}

        body.setAttribute('data-menu', desktop() ? (pilihan === 'tersembunyi' ? 'tersembunyi' : 'terbuka') : 'tersembunyi');
        perbaruiAria();
    }

    if (tutup) {
        tutup.addEventListener('click', function () {
            atur(false, true);
            if (buka) { buka.focus(); }
        });
    }

    if (buka) {
        buka.addEventListener('click', function () {
            atur(true, true);
            if (tutup && !desktop()) { tutup.focus(); }
        });
    }

    if (tirai) {
        tirai.addEventListener('click', function () {
            atur(false, false);
            if (buka) { buka.focus(); }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !desktop() && terbuka()) {
            atur(false, false);
            if (buka) { buka.focus(); }
        }
    });

    if (mq.addEventListener) {
        mq.addEventListener('change', awal);
    } else if (mq.addListener) {
        mq.addListener(awal);
    }

    awal();

    // Indikator cair vertikal pada daftar menu.
    if (menu && cair) {
        var tautan = Array.prototype.slice.call(menu.querySelectorAll('a'));
        var aktif = menu.querySelector('a[aria-current="page"]');

        var ukur = function (el) {
            var d = menu.getBoundingClientRect();
            var r = el.getBoundingClientRect();

            return { y: r.top - d.top + menu.scrollTop, h: r.height };
        };

        var pasang = function (p) {
            cair.style.setProperty('--y', p.y + 'px');
            cair.style.setProperty('--h', p.h + 'px');
            cair.classList.add('siap');
        };

        var ke = function (el) {
            if (!el) { cair.classList.remove('siap'); return; }
            pasang(ukur(el));
        };

        side.classList.add('js');

        var awalCair = null;

        try {
            awalCair = JSON.parse(sessionStorage.getItem(KUNCI_CAIR) || 'null');
            sessionStorage.removeItem(KUNCI_CAIR);
        } catch (e) {}

        if (aktif && awalCair && !kurangiGerak) {
            var transisi = cair.style.transition;
            cair.style.transition = 'none';
            pasang(awalCair);
            void cair.offsetWidth;
            cair.style.transition = transisi;
        }

        ke(aktif);

        tautan.forEach(function (a) {
            a.addEventListener('mouseenter', function () { ke(a); });
            a.addEventListener('focus', function () { ke(a); });
            a.addEventListener('click', function () {
                try {
                    if (aktif && a !== aktif) { sessionStorage.setItem(KUNCI_CAIR, JSON.stringify(ukur(aktif))); }
                } catch (e) {}
            });
        });

        menu.addEventListener('mouseleave', function () { ke(aktif); });
        menu.addEventListener('focusout', function () { ke(aktif); });
        window.addEventListener('resize', function () { ke(aktif); });
        window.addEventListener('load', function () { ke(aktif); });
    }
})();
