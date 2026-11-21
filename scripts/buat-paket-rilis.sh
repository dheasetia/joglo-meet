#!/usr/bin/env bash
#
# Buat paket rilis .zip siap unggah ke shared hosting (tanpa SSH pun bisa).
# Isi: kode yang SUDAH DI-COMMIT + vendor/ produksi + aset public/build.
# Tidak ikut: .env, node_modules, tests, berkas lokal lain.
#
# Pemakaian (dari folder proyek):  bash scripts/buat-paket-rilis.sh [path-zip-keluaran]
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
VERSION="$(git rev-parse --short HEAD)"
OUT="${1:-$ROOT/../joglo-meet-rilis-$(date +%Y%m%d-%H%M)-$VERSION.zip}"

if [ -n "$(git status --porcelain)" ]; then
  echo "⚠  Ada perubahan yang belum di-commit; perubahan itu TIDAK ikut dalam paket."
fi

echo "1/4 Membangun aset (npm run build)…"
npm run build >/dev/null

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "2/4 Menyalin kode commit ${VERSION}…"
git archive HEAD | tar -x -C "$TMP"
cp -R public/build "$TMP/public/build"
rm -rf "$TMP/tests" "$TMP/.github" "$TMP/scripts"
# Folder hasil build Vite dev tidak boleh ikut
rm -f "$TMP/public/hot"

echo "3/4 Memasang vendor produksi (composer install --no-dev)…"
(cd "$TMP" && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet)

echo "4/4 Membuat zip…"
(cd "$TMP" && zip -qr "$OUT" . -x '*.DS_Store')

echo "✓ Paket rilis: $OUT ($(du -h "$OUT" | cut -f1))"
