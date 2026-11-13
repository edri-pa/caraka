#!/usr/bin/env bash
# Menyalakan semua yang dibutuhkan untuk demo dalam urutan yang benar.
# Pemakaian: scripts/mulai-demo.sh [--tanpa-tunnel] [--bangun]
set -uo pipefail
cd "$(dirname "$0")/.."

TANPA_TUNNEL=0
BANGUN=0

for a in "$@"; do
    case "$a" in
        --tanpa-tunnel) TANPA_TUNNEL=1 ;;
        --bangun) BANGUN=1 ;;
        *) echo "Opsi tidak dikenal: $a" >&2; exit 2 ;;
    esac
done

STATE="$HOME/.caraka-demo"
mkdir -p "$STATE"

langkah() { echo; echo "==> $*"; }
gagal() { echo "GAGAL: $*" >&2; exit 1; }

langkah "Memeriksa Docker"
docker info >/dev/null 2>&1 || gagal "Docker tidak terjangkau. Buka Docker Desktop, tunggu hijau, lalu ulangi."

langkah "Menyalakan layanan (sail up -d)"
./vendor/bin/sail up -d || gagal "sail up -d gagal."

langkah "Menunggu database siap"
siap=0
for i in $(seq 1 40); do
    if ./vendor/bin/sail exec -T pgsql sh -c 'pg_isready -U "$POSTGRES_USER" -d "$POSTGRES_DB"' >/dev/null 2>&1; then
        siap=1
        break
    fi
    sleep 2
done
[ "$siap" -eq 1 ] || gagal "Database belum siap setelah 80 detik. Periksa: sail ps"

langkah "Menyalakan Reverb (real-time)"
if [ -f "$STATE/reverb.pid" ] && kill -0 "$(cat "$STATE/reverb.pid")" 2>/dev/null; then
    echo "Reverb sudah berjalan."
else
    nohup ./vendor/bin/sail artisan reverb:start > "$STATE/reverb.log" 2>&1 &
    echo $! > "$STATE/reverb.pid"
    sleep 4
fi

langkah "Menyiapkan tampilan (aset)"
rm -f public/hot
if [ "$BANGUN" -eq 1 ] || [ ! -f public/build/manifest.json ]; then
    ./vendor/bin/sail npm run build || gagal "Build aset gagal. Coba: sail npm install"
else
    echo "Aset hasil build sudah ada (pakai --bangun untuk membangun ulang setelah mengubah tampilan)."
fi

langkah "Memeriksa kesiapan"
if ! ./vendor/bin/sail artisan demo:periksa; then
    gagal "Belum siap. Perbaiki masalah KRITIS di atas, lalu ulangi."
fi

if [ "$TANPA_TUNNEL" -eq 0 ]; then
    langkah "Membuka tunnel HTTPS (cloudflared)"
    if ! command -v cloudflared >/dev/null 2>&1; then
        echo "cloudflared tidak terpasang: tunnel dilewati. Demo hanya dari laptop."
    else
        if [ -f "$STATE/tunnel.pid" ] && kill -0 "$(cat "$STATE/tunnel.pid")" 2>/dev/null; then
            echo "Tunnel sudah berjalan."
        else
            : > "$STATE/tunnel.log"
            nohup cloudflared tunnel --url http://localhost:80 > "$STATE/tunnel.log" 2>&1 &
            echo $! > "$STATE/tunnel.pid"
        fi

        url=""
        for i in $(seq 1 30); do
            url=$(grep -o 'https://[a-zA-Z0-9-]*\.trycloudflare\.com' "$STATE/tunnel.log" | head -n 1)
            [ -n "$url" ] && break
            sleep 1
        done

        if [ -n "$url" ]; then
            echo "$url" > "$STATE/alamat.txt"
        else
            echo "Alamat tunnel belum terbaca. Lihat: $STATE/tunnel.log"
        fi
    fi
fi

langkah "Siap"
echo "Laptop (dispatcher dan responder): http://localhost"

if [ "$TANPA_TUNNEL" -eq 0 ] && [ -s "$STATE/alamat.txt" ]; then
    echo "HP warga (HTTPS):                 $(cat "$STATE/alamat.txt")/sos"
    echo "                                  $(cat "$STATE/alamat.txt")/lapor"
    echo "Selama tunnel menyala, seluruh aplikasi bisa dibuka siapa pun yang tahu alamat itu."
fi

echo "Data demo fiktif (opsional): sail artisan demo:isi"
echo "Mengakhiri demo:             scripts/akhiri-demo.sh"
