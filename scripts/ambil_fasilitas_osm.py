#!/usr/bin/env python3
"""
Mengambil KANDIDAT fasilitas dari OpenStreetMap (Overpass API) di sekitar Kabupaten Temanggung.

Hasilnya BUKAN data terverifikasi: nama, jenis, koordinat, layanan IGD, dan jam buka
harus diperiksa manual sebelum diimpor. Data berasal dari kontributor OpenStreetMap (ODbL).

Cara pakai (dari folder proyek):
    python3 scripts/ambil_fasilitas_osm.py [berkas_keluaran.csv]
"""

import csv
import json
import socket
import sys
import urllib.error
import urllib.parse
import urllib.request
from datetime import date

# Kotak pencarian (selatan, barat, utara, timur). Longgar: bisa memuat fasilitas kabupaten tetangga.
SELATAN, BARAT, UTARA, TIMUR = -7.55, 109.95, -7.10, 110.40
ENDPOINT = "https://overpass-api.de/api/interpreter"
USER_AGENT = "caraka-prototype/0.1 (proyek pribadi; pengambilan data sekali jalan)"

KOTAK = f"({SELATAN},{BARAT},{UTARA},{TIMUR})"
QUERY = f"""
[out:json][timeout:90];
(
  nwr["amenity"="hospital"]{KOTAK};
  nwr["healthcare"="hospital"]{KOTAK};
  nwr["amenity"="clinic"]{KOTAK};
  nwr["amenity"="fire_station"]{KOTAK};
  nwr["amenity"="police"]{KOTAK};
  nwr["office"="government"]["name"~"BPBD|Penanggulangan Bencana",i]{KOTAK};
);
out center tags;
"""

KOLOM = [
    "nama", "jenis", "wilayah", "kecamatan", "lintang", "bujur",
    "buka_24_jam", "ada_igd", "kontak", "kontak_terverifikasi", "sumber", "tanggal_cek",
]


def tebak_jenis(tag, nama):
    huruf = nama.lower()

    if tag.get("amenity") == "fire_station":
        return "pos_damkar"
    if tag.get("amenity") == "police":
        return "polres" if "polres" in huruf else "polsek"
    if "bpbd" in huruf or "penanggulangan bencana" in huruf:
        return "kantor_bpbd"
    if "puskesmas" in huruf:
        return "puskesmas"
    if tag.get("amenity") == "hospital" or tag.get("healthcare") == "hospital":
        return "rumah_sakit"
    if tag.get("amenity") == "clinic":
        return "klinik"

    return "lainnya"


def main():
    argumen = [a for a in sys.argv[1:] if not a.startswith("--")]
    pilihan = [a for a in sys.argv[1:] if a.startswith("--")]
    keluaran = argumen[0] if argumen else "database/data/fasilitas-kandidat.csv"

    if "--cetak-kueri" in pilihan:
        print(QUERY)
        return 0

    if "--ipv4" in pilihan:
        # Paksa IPv4 (membantu jika IPv6 di WSL tidak terjangkau).
        asli = socket.getaddrinfo
        socket.getaddrinfo = lambda host, port, family=0, *a, **k: asli(host, port, socket.AF_INET, *a, **k)

    berkas_json = next((p.split("=", 1)[1] for p in pilihan if p.startswith("--berkas=")), None)
    endpoint = next((p.split("=", 1)[1] for p in pilihan if p.startswith("--endpoint=")), ENDPOINT)

    if berkas_json:
        try:
            with open(berkas_json, encoding="utf-8") as sumber:
                data = json.load(sumber)
        except (OSError, json.JSONDecodeError) as galat:
            print(f"Gagal membaca berkas: {galat}", file=sys.stderr)
            return 1
    else:
        permintaan = urllib.request.Request(
            endpoint,
            data=urllib.parse.urlencode({"data": QUERY}).encode("utf-8"),
            headers={"User-Agent": USER_AGENT, "Accept": "application/json"},
        )

        try:
            with urllib.request.urlopen(permintaan, timeout=120) as respons:
                data = json.load(respons)
        except (urllib.error.URLError, TimeoutError, json.JSONDecodeError) as galat:
            print(f"Gagal mengambil data: {galat}", file=sys.stderr)
            return 1

    hari_ini = date.today().isoformat()
    terlihat = set()
    baris = []
    tanpa_nama = 0
    di_luar = 0

    for el in data.get("elements", []):
        tag = el.get("tags", {})
        nama = (tag.get("name") or "").strip()

        if not nama:
            tanpa_nama += 1
            continue

        if el.get("type") == "node":
            lat, lon = el.get("lat"), el.get("lon")
        else:
            pusat = el.get("center") or {}
            lat, lon = pusat.get("lat"), pusat.get("lon")

        if lat is None or lon is None:
            continue

        if not (SELATAN <= lat <= UTARA and BARAT <= lon <= TIMUR):
            di_luar += 1
            continue

        kunci = nama.lower()
        if kunci in terlihat:
            continue
        terlihat.add(kunci)

        jenis = tebak_jenis(tag, nama)
        igd = "ya" if (jenis == "rumah_sakit" and tag.get("emergency") == "yes") else "tidak"

        baris.append({
            "nama": nama,
            "jenis": jenis,
            "wilayah": "Temanggung",
            "kecamatan": "",
            "lintang": f"{lat:.6f}",
            "bujur": f"{lon:.6f}",
            "buka_24_jam": "tidak",
            "ada_igd": igd,
            "kontak": "",
            "kontak_terverifikasi": "",
            "sumber": "OpenStreetMap (Overpass API), belum diverifikasi; IGD dan jam buka belum dikonfirmasi",
            "tanggal_cek": hari_ini,
        })

    baris.sort(key=lambda b: (b["jenis"], b["nama"].lower()))

    for urutan, b in enumerate(baris, start=1):
        b["kontak"] = f"DEMO-{urutan:04d}"

    with open(keluaran, "w", newline="", encoding="utf-8") as berkas:
        penulis = csv.DictWriter(berkas, fieldnames=KOLOM)
        penulis.writeheader()
        penulis.writerows(baris)

    ringkas = {}
    for b in baris:
        ringkas[b["jenis"]] = ringkas.get(b["jenis"], 0) + 1

    print(f"Tersimpan: {len(baris)} kandidat di {keluaran}")
    for jenis, jumlah in sorted(ringkas.items()):
        print(f"  {jenis}: {jumlah}")
    print(f"Dilewati: {tanpa_nama} tanpa nama, {di_luar} di luar kotak pencarian.")
    print("PERIKSA setiap baris: wilayah (kabupaten tetangga?), kecamatan, jenis, IGD, dan jam buka.")

    return 0


if __name__ == "__main__":
    sys.exit(main())
