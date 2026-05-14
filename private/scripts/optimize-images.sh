#!/usr/bin/env bash
# Convert raster images in public/assets/img to WebP variants.
# Requires: brew install webp
set -e
cd "$(dirname "$0")/../../public/assets/img"
shopt -s nullglob
for f in *.jpg *.png; do
  [ -f "$f" ] || continue
  base="${f%.*}"
  if [ -f "${base}.webp" ] && [ "${base}.webp" -nt "$f" ]; then
    echo "= ${base}.webp up to date"
    continue
  fi
  cwebp -q 82 "$f" -o "${base}.webp" >/dev/null
  echo "+ ${base}.webp"
done
echo "done"
