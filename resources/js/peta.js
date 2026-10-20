import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Tooltip dibuat dari teks biasa (bukan HTML) supaya nama fasilitas tidak bisa menyisipkan kode.
function teks() {
    var el = document.createElement('div');
    el.textContent = Array.prototype.join.call(arguments, '');

    return el;
}

function mulaiPeta() {
    var wadah = document.getElementById('peta');
    var sumber = document.getElementById('peta-data');

    if (!wadah || !sumber) {
        return;
    }

    var data;
    try {
        data = JSON.parse(sumber.textContent);
    } catch (e) {
        return;
    }

    if (!data || !data.kejadian) {
        return;
    }

    var k = data.kejadian;
    var peta = L.map(wadah, { scrollWheelZoom: false }).setView([k.lat, k.lng], 14);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(peta);

    var titik = [[k.lat, k.lng]];

    if (k.akurasi) {
        L.circle([k.lat, k.lng], { radius: k.akurasi, color: '#dc2626', weight: 1, fillOpacity: 0.08 }).addTo(peta);
    }

    L.circleMarker([k.lat, k.lng], { radius: 9, color: '#991b1b', fillColor: '#ef4444', fillOpacity: 0.95 })
        .addTo(peta)
        .bindTooltip(teks('Lokasi kejadian'), { permanent: true, direction: 'top' });

    (data.fasilitas || []).forEach(function (f) {
        L.circleMarker([f.lat, f.lng], { radius: 7, color: '#1e3a8a', fillColor: '#3b82f6', fillOpacity: 0.95 })
            .addTo(peta)
            .bindTooltip(teks(f.nama, ' (', f.jenis, '), sekitar ', (f.jarak / 1000).toFixed(1), ' km'));
        titik.push([f.lat, f.lng]);
    });

    if (titik.length > 1) {
        peta.fitBounds(titik, { padding: [30, 30], maxZoom: 16 });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mulaiPeta);
} else {
    mulaiPeta();
}
