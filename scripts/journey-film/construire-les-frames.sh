#!/usr/bin/env bash
#
# Brio — « Le Film » : fabrique les séquences de frames servies par la home.
#
# DEUX FILMS, UN PAR THÈME. Le mode clair montre la mission de JOUR, le mode sombre la
# mission de NUIT. Ce ne sont pas deux étalonnages du même rendu : ce sont deux tournages
# distincts, 14 plans chacun.
#
# Entrée  : deux dossiers de 14 MP4 de 5 s (1920x1088, 24 fps), nommés shot01..shot14.
# Sortie  : public/images/journey-film/<variante>/{desktop,mobile}/NNN.avif + poster,
#           et un frames.json unique qui décrit les deux.
#
# Les prompts qui ont produit les plans vivent dans
# docs/superpowers/specs/2026-09-10-journey-film-prompts.md : un plan se refait sans refaire
# les treize autres.
#
# Usage : scripts/journey-film/construire-les-frames.sh <dossier-jour> <dossier-nuit>

set -euo pipefail

SOURCE_JOUR="${1:?Donnez le dossier des plans de JOUR (shot01.mp4 .. shot14.mp4)}"
SOURCE_NUIT="${2:?Donnez le dossier des plans de NUIT}"
RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SORTIE="$RACINE/public/images/journey-film"

PLANS=14
FRAMES_PAR_PLAN=25          # 121 images source, une sur cinq -> 25 exactement, sans dérive
CPU_USED=8                  # libaom : 8 encode vite pour ~3 % de poids en plus

# Le plein jour coûte plus cher que la nuit : plus de détail, moins de noir. Mesuré à 100 %,
# c'est le plan de VILLE VUE DU CIEL qui décide — au-delà de 44 ses toitures se ramollissent,
# alors que les plans d'intérieur tiennent jusqu'à 48 sans différence visible.
CRF_JOUR_DESKTOP=44
CRF_JOUR_MOBILE=48
CRF_NUIT_DESKTOP=40
CRF_NUIT_MOBILE=42

encoder() { # $1=png source  $2=avif cible  $3=crf
    ffmpeg -y -loglevel error -i "$1" \
        -c:v libaom-av1 -still-picture 1 -crf "$3" -cpu-used "$CPU_USED" \
        -pix_fmt yuv420p -f avif "$2"
}

# ---------------------------------------------------------------- une variante
fabriquer() { # $1=nom de variante  $2=dossier source  $3=crf desktop  $4=crf mobile
    local variante="$1" source="$2" crf_d="$3" crf_m="$4"
    local travail cible index i j nom

    travail="$(mktemp -d)"
    cible="$SORTIE/$variante"

    rm -rf "$cible"
    mkdir -p "$cible/desktop" "$cible/mobile"
    echo "→ variante « $variante » depuis $source"

    # `select=not(mod(n,5))` prend les images 0,5,…,120 : exactement 25 par plan, donc des
    # chapitres qui tombent juste. Un `fps=` aurait dérivé de 0,2 image par plan.
    # `crop=1920:1080:0:4` : le modèle rend sur un multiple de 16, soit 1088, pas 1080.
    for i in $(seq -w 1 $PLANS); do
        [ -f "$source/shot$i.mp4" ] || { echo "Plan manquant : $source/shot$i.mp4" >&2; exit 1; }

        ffmpeg -y -loglevel error -i "$source/shot$i.mp4" \
            -vf "select='not(mod(n\,5))',crop=1920:1080:0:4,scale=1280:720:flags=lanczos" \
            -vsync 0 "$travail/d_${i}_%03d.png"

        ffmpeg -y -loglevel error -i "$source/shot$i.mp4" \
            -vf "select='not(mod(n\,5))',crop=1920:1080:0:4,scale=768:432:flags=lanczos" \
            -vsync 0 "$travail/m_${i}_%03d.png"
    done

    # Les frames sont numérotées GLOBALEMENT : le décodeur ne connaît que des index 0..N-1.
    index=0
    for i in $(seq -w 1 $PLANS); do
        for j in $(seq 1 $FRAMES_PAR_PLAN); do
            j="$(printf '%03d' "$j")"          # ffmpeg écrit d_01_001.png, pas d_01_1.png
            nom="$(printf '%03d' $index)"
            encoder "$travail/d_${i}_${j}.png" "$cible/desktop/$nom.avif" "$crf_d" &
            encoder "$travail/m_${i}_${j}.png" "$cible/mobile/$nom.avif" "$crf_m" &
            index=$((index + 1))
        done
        wait                     # une vague par plan : on ne lance pas 700 encodages d'un coup
        echo "   plan $i encodé ($index frames)"
    done

    # Le poster est servi AVANT la première frame et reste le visuel des navigateurs sans AVIF :
    # il est donc en WebP et en JPEG, deux formats que tout le monde décode.
    ffmpeg -y -loglevel error -i "$travail/d_01_001.png" -c:v libwebp -quality 78 "$cible/poster.webp"
    ffmpeg -y -loglevel error -i "$travail/d_01_001.png" -q:v 4 "$cible/poster.jpg"

    rm -rf "$travail"
}

mkdir -p "$SORTIE"
fabriquer jour "$SOURCE_JOUR" "$CRF_JOUR_DESKTOP" "$CRF_JOUR_MOBILE"
fabriquer nuit "$SOURCE_NUIT" "$CRF_NUIT_DESKTOP" "$CRF_NUIT_MOBILE"

# ---------------------------------------------------------------- manifeste
total=$((PLANS * FRAMES_PAR_PLAN))

# LA VERSION CHANGE A CHAQUE FABRICATION, ET C'EST TOUT L'INTERET.
# Les fichiers gardent leurs noms d'un tournage a l'autre : sans ce jeton dans l'URL, le
# navigateur d'un visiteur qui a deja vu le film lui reservirait l'ANCIEN depuis son cache.
# Mesure du 2026-09-11 : le film refait en plein jour restait nocturne apres deux rechargements.
version="$(date -u +%Y%m%d%H%M%S)"

{
    printf '{\n'
    printf '  "version": "%s",\n' "$version"
    printf '  "total": %d,\n' "$total"
    printf '  "parPlan": %d,\n' "$FRAMES_PAR_PLAN"
    printf '  "plans": %d,\n' "$PLANS"
    printf '  "tailles": { "desktop": [1280, 720], "mobile": [768, 432] },\n'
    printf '  "variantes": { "clair": "jour", "sombre": "nuit" },\n'
    printf '  "poster": "poster.webp"\n'
    printf '}\n'
} > "$SORTIE/frames.json"

echo "✓ 2 variantes × $total frames × 2 tailles"
du -sh "$SORTIE/jour" "$SORTIE/nuit" "$SORTIE"
