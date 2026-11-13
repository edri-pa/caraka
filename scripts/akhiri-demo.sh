#!/usr/bin/env bash
# Mengakhiri demo: menghentikan tunnel dan Reverb yang dinyalakan scripts/mulai-demo.sh.
# Pemakaian: scripts/akhiri-demo.sh [--matikan-layanan]
set -uo pipefail
cd "$(dirname "$0")/.."

STATE="$HOME/.caraka-demo"

hentikan() {
    nama="$1"
    berkas="$STATE/$1.pid"

    if [ -f "$berkas" ]; then
        pid="$(cat "$berkas")"
        if kill "$pid" 2>/dev/null; then
            echo "$nama dihentikan."
        else
            echo "$nama sudah tidak berjalan."
        fi
        rm -f "$berkas"
    fi
}

hentikan tunnel
hentikan reverb

./vendor/bin/sail exec -T laravel.test sh -c 'pkill -f "artisan reverb:start" || true' >/dev/null 2>&1
rm -f "$STATE/alamat.txt"

if [ "${1:-}" = "--matikan-layanan" ]; then
    ./vendor/bin/sail stop
fi

echo "Selesai. Tunnel dimatikan, jadi alamat HTTPS tidak lagi bisa dibuka."
echo "Jika demo memakai data fiktif, hapus dengan: sail artisan demo:isi --hapus"
