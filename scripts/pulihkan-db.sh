#!/usr/bin/env bash
# Memulihkan database Caraka dari berkas cadangan. MENIMPA isi database saat ini.
# Pemakaian: scripts/pulihkan-db.sh berkas.dump
set -euo pipefail
cd "$(dirname "$0")/.."

berkas="${1:-}"

if [ -z "$berkas" ] || [ ! -s "$berkas" ]; then
    echo "Pemakaian: scripts/pulihkan-db.sh <berkas.dump>" >&2
    exit 1
fi

read -r -p "Ini MENIMPA isi database saat ini dengan $berkas. Ketik YA untuk lanjut: " jawab

if [ "$jawab" != "YA" ]; then
    echo "Dibatalkan."
    exit 1
fi

echo "Membuat cadangan pengaman sebelum memulihkan..."
./scripts/cadangan-db.sh "$HOME/cadangan-caraka/sebelum-pulih"

if ! ./vendor/bin/sail exec -T pgsql sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner' < "$berkas"; then
    echo "pg_restore melaporkan peringatan atau galat. Periksa hasilnya dengan: sail artisan migrate:status" >&2
fi

echo "Selesai. Periksa: sail artisan migrate:status"
