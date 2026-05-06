#!/usr/bin/env bash
#
# CleanUx — Phase 0 Cleanup Script
#
# Lance ce script depuis la racine de ton projet CleanUx :
#   bash scripts/phase0_cleanup.sh
#
# Il va :
#  1. Sauvegarder les fichiers à modifier dans .phase0_backup/
#  2. Renommer Entreprise → Enterprise (services + classes + namespaces + imports)
#  3. Renommer le test EntrepriseRoutingServiceTest → EnterpriseRoutingServiceTest
#  4. Supprimer Bookings.php (orphelin, jamais utilisé)
#  5. Bump composer.json (php ^8.2, laravel ^10.47) — préparation Phase 3
#  6. Afficher un résumé
#
# REQUIS : sed, find, mkdir
# PRÉ-REQUIS : avoir copié le nouveau Booking.php (cf. PHASE0_CLEANUP_GUIDE.md §2)

set -euo pipefail

PROJECT_ROOT="${PROJECT_ROOT:-$(pwd)}"
BACKUP_DIR="$PROJECT_ROOT/.phase0_backup_$(date +%Y%m%d_%H%M%S)"

echo "=================================================================="
echo "CleanUx — Phase 0 Cleanup"
echo "=================================================================="
echo "Project root : $PROJECT_ROOT"
echo "Backup dir   : $BACKUP_DIR"
echo ""

# ──────────────────────────────────────────────────────────────────
# Garde-fous
# ──────────────────────────────────────────────────────────────────
if [[ ! -f "$PROJECT_ROOT/composer.json" ]]; then
  echo "❌ composer.json introuvable. Lance ce script depuis la racine du projet Laravel."
  exit 1
fi

if [[ ! -d "$PROJECT_ROOT/app/Models" ]]; then
  echo "❌ app/Models introuvable. Mauvaise structure projet ?"
  exit 1
fi

# ──────────────────────────────────────────────────────────────────
# Backup
# ──────────────────────────────────────────────────────────────────
echo "→ [1/6] Backup des fichiers à modifier"
mkdir -p "$BACKUP_DIR"

# Sauvegarder uniquement ce qui va changer
[[ -f "$PROJECT_ROOT/app/Models/Bookings.php" ]] && cp "$PROJECT_ROOT/app/Models/Bookings.php" "$BACKUP_DIR/" || true
[[ -d "$PROJECT_ROOT/app/Services/Entreprise" ]] && cp -r "$PROJECT_ROOT/app/Services/Entreprise" "$BACKUP_DIR/" || true
[[ -f "$PROJECT_ROOT/composer.json" ]] && cp "$PROJECT_ROOT/composer.json" "$BACKUP_DIR/composer.json.bak" || true

# Sauvegarder aussi les fichiers consommateurs (en cas de regret)
for f in \
  "app/Livewire/Client/FinanceDocumentsClient.php" \
  "app/Http/Controllers/Client/FinanceDocumentDownloadController.php" \
  "tests/Unit/EntrepriseRoutingServiceTest.php"; do
  if [[ -f "$PROJECT_ROOT/$f" ]]; then
    mkdir -p "$BACKUP_DIR/$(dirname "$f")"
    cp "$PROJECT_ROOT/$f" "$BACKUP_DIR/$f"
  fi
done

echo "  ✓ Backup créé dans $BACKUP_DIR"

# ──────────────────────────────────────────────────────────────────
# 2. Supprimer Bookings.php (orphelin)
# ──────────────────────────────────────────────────────────────────
echo "→ [2/6] Suppression de app/Models/Bookings.php (orphelin)"

if [[ -f "$PROJECT_ROOT/app/Models/Bookings.php" ]]; then
  # Vérifier qu'aucun code ne l'utilise (sinon on stoppe)
  # Le `|| true` évite que set -e ne tue le script quand grep trouve 0 match.
  USAGE_LIST=$(grep -rln "App\\\\Models\\\\Bookings\|Bookings::" "$PROJECT_ROOT/app" "$PROJECT_ROOT/tests" 2>/dev/null | grep -v "Bookings.php" || true)
  USAGE_COUNT=$(echo -n "$USAGE_LIST" | grep -c '' || true)

  if [[ "$USAGE_COUNT" -gt 0 ]]; then
    echo "  ⚠ ATTENTION: Bookings est encore référencé dans $USAGE_COUNT fichiers :"
    echo "$USAGE_LIST"
    echo "  ⚠ Aborting suppression. Mets à jour ces fichiers d'abord."
    exit 1
  fi

  rm "$PROJECT_ROOT/app/Models/Bookings.php"
  echo "  ✓ Bookings.php supprimé"
else
  echo "  ✓ Bookings.php déjà absent — rien à faire"
fi

# ──────────────────────────────────────────────────────────────────
# 3. Renommer Entreprise → Enterprise (namespace + classes + dossier)
# ──────────────────────────────────────────────────────────────────
echo "→ [3/6] Migration Entreprise → Enterprise"

ENT_DIR="$PROJECT_ROOT/app/Services/Entreprise"
ENG_DIR="$PROJECT_ROOT/app/Services/Enterprise"

if [[ -d "$ENT_DIR" ]]; then
  mkdir -p "$ENG_DIR"

  for src in "$ENT_DIR"/*.php; do
    [[ -e "$src" ]] || continue
    base=$(basename "$src")
    # Renommer le préfixe de la classe : EntrepriseFoo.php → EnterpriseFoo.php
    new_base="${base/Entreprise/Enterprise}"
    dst="$ENG_DIR/$new_base"

    # Lire, transformer namespace + nom de classe, écrire
    sed -e 's|namespace App\\Services\\Entreprise;|namespace App\\Services\\Enterprise;|g' \
        -e 's|class Entreprise|class Enterprise|g' \
        "$src" > "$dst"

    rm "$src"
    echo "  ✓ $base → Enterprise/$new_base"
  done

  # Supprimer le dossier vide
  rmdir "$ENT_DIR" 2>/dev/null && echo "  ✓ dossier app/Services/Entreprise supprimé" || \
    echo "  ⚠ dossier app/Services/Entreprise non vide — vérification manuelle requise"
else
  echo "  ✓ Pas de app/Services/Entreprise — déjà migré ou jamais existant"
fi

# Mettre à jour les imports dans tout le code (app/, tests/, routes/, config/)
echo "→ [4/6] Mise à jour des imports App\\Services\\Entreprise → App\\Services\\Enterprise"

UPDATED_FILES=$(grep -rln "App\\\\Services\\\\Entreprise\|use App\\\\Services\\\\Entreprise" \
  "$PROJECT_ROOT/app" "$PROJECT_ROOT/tests" "$PROJECT_ROOT/routes" "$PROJECT_ROOT/config" \
  2>/dev/null || true)

if [[ -n "$UPDATED_FILES" ]]; then
  while IFS= read -r f; do
    [[ -z "$f" ]] && continue
    sed -i.bak \
      -e 's|App\\Services\\Entreprise\\Entreprise|App\\Services\\Enterprise\\Enterprise|g' \
      -e 's|App\\Services\\Entreprise|App\\Services\\Enterprise|g' \
      -e 's|EntrepriseRoutingService|EnterpriseRoutingService|g' \
      -e 's|EntrepriseBookingPolicyResolver|EnterpriseBookingPolicyResolver|g' \
      "$f"
    rm -f "${f}.bak"
    echo "  ✓ $f"
  done <<< "$UPDATED_FILES"
else
  echo "  ✓ Aucun import à mettre à jour"
fi

# Renommer le test fichier si présent
TEST_OLD="$PROJECT_ROOT/tests/Unit/EntrepriseRoutingServiceTest.php"
TEST_NEW="$PROJECT_ROOT/tests/Unit/EnterpriseRoutingServiceTest.php"
if [[ -f "$TEST_OLD" ]]; then
  # Mettre à jour le nom de classe à l'intérieur
  sed -i.bak 's|class EntrepriseRoutingServiceTest|class EnterpriseRoutingServiceTest|g' "$TEST_OLD"
  rm -f "${TEST_OLD}.bak"
  mv "$TEST_OLD" "$TEST_NEW"
  echo "  ✓ test renommé : EntrepriseRoutingServiceTest → EnterpriseRoutingServiceTest"
fi

# ──────────────────────────────────────────────────────────────────
# 5. Bump composer.json pour Phase 3 (Reverb)
# ──────────────────────────────────────────────────────────────────
echo "→ [5/6] Vérification composer.json (php ^8.2+, laravel/framework ^10.47+)"

COMPOSER="$PROJECT_ROOT/composer.json"

# Lecture des versions courantes
CURRENT_PHP=$(grep -oE '"php":\s*"[^"]+"' "$COMPOSER" | head -1 | grep -oE '\^[0-9]+\.[0-9]+' || echo "?")
CURRENT_LARAVEL=$(grep -oE '"laravel/framework":\s*"[^"]+"' "$COMPOSER" | head -1 | grep -oE '\^[0-9]+\.[0-9]+' || echo "?")

echo "  php actuel        : $CURRENT_PHP"
echo "  laravel actuel    : $CURRENT_LARAVEL"

PHP_NEEDS_BUMP=0
LARAVEL_NEEDS_BUMP=0

# Comparer ^8.x → ok si x >= 2
if [[ "$CURRENT_PHP" =~ ^\^([0-9]+)\.([0-9]+)$ ]]; then
  major="${BASH_REMATCH[1]}"
  minor="${BASH_REMATCH[2]}"
  if [[ "$major" -lt 8 ]] || { [[ "$major" -eq 8 ]] && [[ "$minor" -lt 2 ]]; }; then
    PHP_NEEDS_BUMP=1
  fi
fi

# Laravel ^10.x → ok si x >= 47, ou ^11.x+
if [[ "$CURRENT_LARAVEL" =~ ^\^([0-9]+)\.([0-9]+)$ ]]; then
  major="${BASH_REMATCH[1]}"
  minor="${BASH_REMATCH[2]}"
  if [[ "$major" -lt 10 ]] || { [[ "$major" -eq 10 ]] && [[ "$minor" -lt 47 ]]; }; then
    LARAVEL_NEEDS_BUMP=1
  fi
fi

if [[ "$PHP_NEEDS_BUMP" -eq 1 ]]; then
  sed -i.bak 's|"php": "\^8\.[01]"|"php": "^8.2"|g' "$COMPOSER"
  rm -f "${COMPOSER}.bak"
  echo "  ✓ php bumpé vers ^8.2"
fi

if [[ "$LARAVEL_NEEDS_BUMP" -eq 1 ]]; then
  sed -i.bak 's|"laravel/framework": "\^10\.[0-9]\+"|"laravel/framework": "^10.47"|g' "$COMPOSER"
  rm -f "${COMPOSER}.bak"
  echo "  ✓ laravel/framework bumpé vers ^10.47"
fi

if [[ "$PHP_NEEDS_BUMP" -eq 0 ]] && [[ "$LARAVEL_NEEDS_BUMP" -eq 0 ]]; then
  echo "  ✓ déjà à jour pour Reverb (Phase 3) — aucun bump nécessaire"
fi

# ──────────────────────────────────────────────────────────────────
# 6. Récapitulatif
# ──────────────────────────────────────────────────────────────────
echo ""
echo "→ [6/6] Récapitulatif"
echo "  ✓ Bookings.php supprimé"
echo "  ✓ Services Entreprise → Enterprise (namespace + classes + imports)"
echo "  ✓ composer.json bumped"
echo ""
echo "📦 Backup conservé : $BACKUP_DIR"
echo ""
echo "Étapes suivantes :"
echo "  1. cp /chemin/vers/cleanux-phase0/app/Models/Booking.php app/Models/Booking.php"
echo "     (NE PAS oublier — le nouveau Booking inclut les traits/casts/booted de Bookings)"
echo "  2. composer update"
echo "  3. composer dump-autoload"
echo "  4. php artisan test"
echo "  5. git status pour voir tous les changements"
echo "  6. git diff pour relire avant commit"
echo ""
echo "Si quelque chose casse :"
echo "  rsync -av $BACKUP_DIR/ $PROJECT_ROOT/"
echo "  git checkout app/  # selon l'état initial"
echo ""
echo "✅ Phase 0 cleanup terminé."
