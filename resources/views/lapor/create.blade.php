<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lapor Kejadian | Caraka</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 16px; background: #f4f4f5; }
        main { max-width: 480px; margin: auto; background: #fff; padding: 16px; border-radius: 8px; }
        h1 { margin-top: 0; }
        label { display: block; margin: 14px 0 4px; font-weight: bold; font-size: 14px; }
        select, input, textarea, button { width: 100%; box-sizing: border-box; padding: 10px; font-size: 16px; }
        textarea { min-height: 90px; }
        button { cursor: pointer; margin-top: 8px; }
        #kirim { margin-top: 20px; padding: 14px; font-weight: bold; }
        .catatan { background: #fef3c7; padding: 10px; border-radius: 4px; font-size: 14px; }
        .galat { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 4px; margin: 12px 0; font-size: 14px; }
        .galat ul { margin: 0; padding-left: 18px; }
        .kecil { font-size: 13px; color: #555; margin: 4px 0 0; }
        fieldset { border: 1px solid #ccc; border-radius: 6px; margin-top: 16px; padding: 4px 12px 12px; }
    </style>
</head>
<body>
<main>
    <h1>Lapor Kejadian</h1>

    <p class="catatan" style="background:#fee2e2;">Dalam keadaan darurat dan tidak sempat mengisi formulir, <a href="{{ route('sos.create') }}"><strong>tekan SOS</strong></a>.</p>

    <p class="catatan">
        <strong>Prototype untuk demo.</strong> Dalam keadaan darurat sungguhan, segera hubungi layanan darurat resmi (112).
    </p>

    @if ($errors->any())
        <div class="galat">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="form-lapor" method="POST" action="{{ route('lapor.store') }}">
        @csrf

        <label for="category_id">Jenis kejadian</label>
        <select id="category_id" name="category_id" required>
            <option value="">-- Pilih jenis kejadian --</option>
            @foreach ($parents as $parent)
                @if (isset($children[$parent->id]))
                    <optgroup label="{{ $parent->name }}">
                        @foreach ($children[$parent->id] as $child)
                            <option value="{{ $child->id }}" @selected(old('category_id') == $child->id)>{{ $child->name }}</option>
                        @endforeach
                    </optgroup>
                @else
                    <option value="{{ $parent->id }}" @selected(old('category_id') == $parent->id)>{{ $parent->name }}</option>
                @endif
            @endforeach
        </select>

        <label for="description">Keterangan singkat (opsional)</label>
        <textarea id="description" name="description" maxlength="1000">{{ old('description') }}</textarea>

        <label for="has_casualty">Ada korban luka?</label>
        <select id="has_casualty" name="has_casualty">
            <option value="">Tidak tahu</option>
            <option value="1" @selected(old('has_casualty') === '1')>Ya, ada korban luka</option>
            <option value="0" @selected(old('has_casualty') === '0')>Tidak ada korban luka</option>
        </select>

        <fieldset>
            <legend>Lokasi</legend>
            <button type="button" id="gps-btn">Ambil lokasi GPS</button>
            <p id="gps-status" class="kecil">GPS belum diambil.</p>
            <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude') }}">
            <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude') }}">
            <input type="hidden" id="accuracy" name="accuracy" value="{{ old('accuracy') }}">

            <label for="district_id">Kecamatan (wajib jika tidak memakai GPS)</label>
            <select id="district_id" name="district_id">
                <option value="">-- Pilih kecamatan --</option>
                @foreach ($districts as $district)
                    <option value="{{ $district->id }}" @selected(old('district_id') == $district->id)>{{ $district->name }}</option>
                @endforeach
            </select>

            <label for="landmark_text">Patokan lokasi (wajib jika tidak memakai GPS)</label>
            <input id="landmark_text" name="landmark_text" type="text" maxlength="255" value="{{ old('landmark_text') }}" placeholder="Contoh: depan pasar, dekat balai desa">
        </fieldset>

        <label for="reporter_phone">Nomor HP (opsional)</label>
        <input id="reporter_phone" name="reporter_phone" type="tel" inputmode="tel" value="{{ old('reporter_phone') }}" placeholder="Agar petugas bisa menghubungi Anda">
        <p class="kecil">Nomor HP hanya terlihat oleh petugas, dan tidak ditampilkan di halaman status.</p>

        <p id="pesan-klien" class="galat" hidden></p>
        <button type="submit" id="kirim">Kirim laporan</button>
    </form>
</main>

<script>
(function () {
    var KEY = 'caraka_lapor_draf';
    var form = document.getElementById('form-lapor');
    var gpsBtn = document.getElementById('gps-btn');
    var gpsStatus = document.getElementById('gps-status');
    var lat = document.getElementById('latitude');
    var lng = document.getElementById('longitude');
    var acc = document.getElementById('accuracy');
    var pesan = document.getElementById('pesan-klien');
    var kirim = document.getElementById('kirim');
    var fields = ['category_id', 'description', 'district_id', 'landmark_text', 'has_casualty'];

    function muatDraf() {
        try {
            var draf = JSON.parse(localStorage.getItem(KEY) || '{}');
            fields.forEach(function (nama) {
                var el = form.elements[nama];
                if (el && !el.value && draf[nama]) { el.value = draf[nama]; }
            });
        } catch (e) {}
    }

    function simpanDraf() {
        try {
            var draf = {};
            fields.forEach(function (nama) { draf[nama] = form.elements[nama].value; });
            localStorage.setItem(KEY, JSON.stringify(draf));
        } catch (e) {}
    }

    muatDraf();
    form.addEventListener('input', simpanDraf);
    form.addEventListener('change', simpanDraf);

    if (lat.value && lng.value) {
        gpsStatus.textContent = 'Lokasi GPS sudah terisi.';
    }

    gpsBtn.addEventListener('click', function () {
        if (!('geolocation' in navigator)) {
            gpsStatus.textContent = 'Browser tidak mendukung GPS. Pilih kecamatan dan tulis patokan lokasi.';
            return;
        }
        gpsStatus.textContent = 'Mengambil lokasi...';
        navigator.geolocation.getCurrentPosition(function (pos) {
            lat.value = pos.coords.latitude;
            lng.value = pos.coords.longitude;
            acc.value = Math.round(pos.coords.accuracy);
            gpsStatus.textContent = 'Lokasi terambil (akurasi sekitar ' + Math.round(pos.coords.accuracy) + ' meter).';
        }, function (err) {
            lat.value = ''; lng.value = ''; acc.value = '';
            gpsStatus.textContent = 'Lokasi tidak bisa diambil (' + err.message + '). Pilih kecamatan dan tulis patokan lokasi.';
        }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
    });

    form.addEventListener('submit', function (e) {
        var adaGps = lat.value !== '' && lng.value !== '';
        var manualLengkap = form.elements.district_id.value !== '' && form.elements.landmark_text.value.trim() !== '';

        if (!adaGps && !manualLengkap) {
            e.preventDefault();
            pesan.hidden = false;
            pesan.textContent = 'Ambil lokasi dengan GPS, atau pilih kecamatan dan tulis patokan lokasi.';
            return;
        }
        kirim.disabled = true;
        kirim.textContent = 'Mengirim...';
    });
})();
</script>
</body>
</html>
