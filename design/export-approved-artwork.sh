#!/usr/bin/env bash
# Builds the WordPress.org banner and icon exports from the owner-approved raster artwork in design/approved-artwork/.
#   design/export-approved-artwork.sh      (needs ImageMagick 7: `magick`)
# The sources are checked against asset-manifest.json first. Nothing is redrawn: the icon is only scaled; the banner is
# trimmed by 21 px at the bottom edge (2172x724 to 2172x703) so it has the 3.088:1 shape WordPress.org uses, then scaled.
# The trim removes only blank paper below the text and the interface sketch; no text, selection corner or control is cut.
# Output: .wordpress-org/banner-1544x500.png, banner-772x250.png, icon-256x256.png, icon-128x128.png
set -euo pipefail
cd "$(dirname "$0")/.."
src=design/approved-artwork
for f in $(python3 -c "import json;print(' '.join(e['file'] for e in json.load(open('$src/asset-manifest.json'))))"); do
	want="$(python3 -c "import json;print([e['sha256'] for e in json.load(open('$src/asset-manifest.json')) if e['file']=='$f'][0])")"
	got="$(shasum -a 256 "$src/$f" | cut -d' ' -f1)"
	[ "$want" = "$got" ] || { echo "$f does not match asset-manifest.json" >&2; exit 1; }
done
banner="$src/afs-banner-paper-and-ink-approved-source.png"
icon="$src/afs-icon-outer-corners-approved-source.png"
for spec in 1544x500 772x250; do
	magick "$banner" -crop 2172x703+0+0 +repage -filter Lanczos -resize "${spec}!" -strip -define png:compression-level=9 ".wordpress-org/banner-${spec}.png"
done
for s in 256 128; do
	magick "$icon" -filter Lanczos -resize "${s}x${s}" -strip -define png:compression-level=9 ".wordpress-org/icon-${s}x${s}.png"
done
magick identify .wordpress-org/banner-*.png .wordpress-org/icon-*.png
