#!/usr/bin/env bash
# Cadangan database Caraka (format kustom pg_dump, terkompresi).
# Pemakaian: scripts/cadangan-db.sh [folder_tujuan]
set -euo pipefail
cd "$(dirname "$0")/.."

folder="${1:-$HOME/cadangan-caraka}"
mkdir -p "$folder"
berkas="$folder/caraka-$(date +%Y%m%d-%H%M%S).dump"

./vendor/bin/sail exec -T pgsql sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$berkas"

if [ ! -s "$berkas" ]; then
    rm -f "$berkas"
    echo "GAGAL: berkas cadangan kosong. Pastikan 'sail up -d' sudah dijalankan." >&2
    exit 1
fi

echo "Cadangan tersimpan: $berkas ($(du -h "$berkas" | cut -f1))"

# Simpan 14 cadangan terbaru saja.
ls -1t "$folder"/caraka-*.dump 2>/dev/null | tail -n +15 | xargs -r rm -f
