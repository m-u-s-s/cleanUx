#!/usr/bin/env bash
#
# Brio — « Le Film » : fabrique la séquence de frames servie par la home.
#
# Entrée  : 14 plans MP4 de 5 s (1920x1088, 24 fps) rendus par Higgsfield, nommés shot01..shot14.
# Sortie  : public/images/journey-film/{desktop,mobile}/NNN.avif + poster + frames.json
#
# Les prompts qui ont produit les plans vivent dans
# docs/superpowers/specs/2026-09-10-journey-film-prompts.md : un plan se refait sans refaire
# les treize autres.
#
# Usage : scripts/journey-film/construire-les-frames.sh <dossier-des-plans>

set -euo pipefail

SOURCE="${1:?Donnez le dossier qui contient shot01.mp4 .. shot14.mp4}"
RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SORTIE="$RACINE/public/images/journey-film"
TRAVAIL="$(mktemp -d)"
trap 'rm -rf "$TRAVAIL"' EXIT

PLANS=14
FRAMES_PAR_PLAN=25          # 121 images source, une sur cinq -> 25 exactement, sans dérive
CRF_DESKTOP=40              # mesuré : CRF 32 / 38 / 42 indistinguables à 100 % sur ce contenu
CRF_MOBILE=42               # petit écran : la tolérance y est plus large encore
CPU_USED=8                  # libaom : 8 encode vite pour ~3 % de poids en plus

echo "→ Sortie : $SORTIE"
rm -rf "$SORTIE"
mkdir -p "$SORTIE/desktop" "$SORTIE/mobile"

# ---------------------------------------------------------------- 1. extraction
# `select=not(mod(n,5))` prend les images 0,5,…,120 : exactement 25 par plan, donc des
# chapitres qui tombent juste. Un `fps=` aurait dérivé de 0,2 image par plan.
index=0
for i in $(seq -w 1 $PLANS); do
    plan="$SOURCE/shot$i.mp4"
    [ -f "$plan" ] || { echo "Plan manquant : $plan" >&2; exit 1; }

    ffmpeg -y -loglevel error -i "$plan" \
        -vf "select='not(mod(n\,5))',crop=1920:1080:0:4,scale=1280:720:flags=lanczos" \
        -vsync 0 "$TRAVAIL/d_${i}_%03d.png"

    ffmpeg -y -loglevel error -i "$plan" \
        -vf "select='not(mod(n\,5))',crop=1920:1080:0:4,scale=768:432:flags=lanczos" \
        -vsync 0 "$TRAVAIL/m_${i}_%03d.png"
done

# ---------------------------------------------------------------- 2. encodage AVIF
# Les frames sont numérotées GLOBALEMENT : le décodeur ne connaît que des index 0..N-1.
encoder() { # $1=png source  $2=avif cible  $3=crf
    ffmpeg -y -loglevel error -i "$1" \
        -c:v libaom-av1 -still-picture 1 -crf "$3" -cpu-used "$CPU_USED" \
        -pix_fmt yuv420p -f avif "$2"
}

index=0
for i in $(seq -w 1 $PLANS); do
    for j in $(seq 1 $FRAMES_PAR_PLAN); do
        j="$(printf '%03d' "$j")"          # ffmpeg ecrit d_01_001.png, pas d_01_1.png
        nom="$(printf '%03d' $index)"
        encoder "$TRAVAIL/d_${i}_${j}.png" "$SORTIE/desktop/$nom.avif" "$CRF_DESKTOP" &
        encoder "$TRAVAIL/m_${i}_${j}.png" "$SORTIE/mobile/$nom.avif" "$CRF_MOBILE" &
        index=$((index + 1))
    done
    wait                     # une vague par plan : on ne lance pas 700 encodages d'un coup
    echo "  plan $i encodé ($index frames au total)"
done

# ---------------------------------------------------------------- 3. poster
# Le poster est servi AVANT la première frame et reste le visuel des navigateurs sans AVIF :
# il est donc en WebP et en JPEG, deux formats que tout le monde décode.
ffmpeg -y -loglevel error -i "$TRAVAIL/d_01_001.png" -c:v libwebp -quality 78 "$SORTIE/poster.webp"
ffmpeg -y -loglevel error -i "$TRAVAIL/d_01_001.png" -q:v 4 "$SORTIE/poster.jpg"

# ---------------------------------------------------------------- 4. manifeste
total=$((PLANS * FRAMES_PAR_PLAN))
{
    printf '{\n'
    printf '  "version": 1,\n'
    printf '  "total": %d,\n' "$total"
    printf '  "parPlan": %d,\n' "$FRAMES_PAR_PLAN"
    printf '  "plans": %d,\n' "$PLANS"
    printf '  "tailles": { "desktop": [1280, 720], "mobile": [768, 432] },\n'
    printf '  "poster": "poster.webp"\n'
    printf '}\n'
} > "$SORTIE/frames.json"

echo "✓ $total frames × 2 tailles"
du -sh "$SORTIE/desktop" "$SORTIE/mobile" "$SORTIE"
