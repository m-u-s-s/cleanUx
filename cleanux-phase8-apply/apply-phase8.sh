#!/usr/bin/env bash
#
# Phase 8 — Application dans le repo existant.
#
# Ce script :
#   1. Copie les 13 fichiers source manquants vers leurs emplacements
#   2. Décommente les 2 imports JS dans resources/js/app.js
#   3. Affiche les étapes manuelles restantes (composer, vapid, build, icons)
#
# Workflow :
#   ./apply-phase8.sh --dry-run    # voir ce qui sera fait
#   ./apply-phase8.sh --backup     # sauvegarder l'état actuel
#   ./apply-phase8.sh --apply      # appliquer
#   ./apply-phase8.sh --rollback   # restaurer depuis backup

set -euo pipefail

ROOT="$(pwd)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="${ROOT}/.cleanup-backup-phase8"

MODE="${1:---help}"

# Mapping source → destination (relative au ROOT)
declare -a FILES_TO_COPY=(
    "app/Models/PushSubscription.php"
    "app/Services/Push/WebPushSender.php"
    "app/Notifications/Channels/WebPushChannel.php"
    "app/Http/Controllers/Push/PushSubscriptionController.php"
    "app/Console/Commands/GenerateVapidKeysCommand.php"
    "database/migrations/2026_05_07_160001_create_push_subscriptions.php"
    "public/manifest.webmanifest"
    "public/sw.js"
    "public/offline.html"
    "resources/js/pwa.js"
    "resources/js/push-notifications.js"
    "resources/views/components/push-toggle.blade.php"
    "resources/views/components/pwa-install-prompt.blade.php"
    "resources/views/components/mobile-bottom-nav.blade.php"
)

dry_run() {
    echo "=== Phase 8 — DRY RUN ==="
    echo ""
    echo "Files to copy:"
    for f in "${FILES_TO_COPY[@]}"; do
        local src="$SCRIPT_DIR/$f"
        local dst="$ROOT/$f"
        if [ ! -f "$src" ]; then
            echo "  ⚠️  MISSING in source pack: $f"
            continue
        fi
        if [ -f "$dst" ]; then
            echo "  ⚠️  WILL OVERWRITE: $f (dst exists)"
        else
            echo "  + NEW: $f"
        fi
    done
    echo ""
    echo "JS imports to uncomment in resources/js/app.js:"
    if grep -q "^// import './pwa';" "$ROOT/resources/js/app.js" 2>/dev/null; then
        echo "  ✓ // import './pwa'             → import './pwa'"
    fi
    if grep -q "^// import './push-notifications';" "$ROOT/resources/js/app.js" 2>/dev/null; then
        echo "  ✓ // import './push-notifications' → import './push-notifications'"
    fi
    echo ""
    echo "Manual steps after --apply:"
    echo "  1. composer install   (already has minishlink/web-push in composer.json)"
    echo "  2. php artisan migrate"
    echo "  3. php artisan webpush:vapid     (then copy keys to .env)"
    echo "  4. npm run build"
    echo "  5. Generate icons in public/icons/ (see notes below)"
    echo "  6. Add <x-push-toggle /> in profile page"
    echo "  7. Add <x-mobile-bottom-nav /> + <x-pwa-install-prompt /> in layouts"
}

backup() {
    echo "=== Backup current state to $BACKUP_DIR ==="
    rm -rf "$BACKUP_DIR"
    mkdir -p "$BACKUP_DIR"

    for f in "${FILES_TO_COPY[@]}"; do
        if [ -f "$ROOT/$f" ]; then
            mkdir -p "$BACKUP_DIR/$(dirname "$f")"
            cp "$ROOT/$f" "$BACKUP_DIR/$f"
        fi
    done

    # Backup app.js avant le sed
    if [ -f "$ROOT/resources/js/app.js" ]; then
        mkdir -p "$BACKUP_DIR/resources/js"
        cp "$ROOT/resources/js/app.js" "$BACKUP_DIR/resources/js/app.js"
    fi

    echo "Backup saved: $(find "$BACKUP_DIR" -type f | wc -l) files"
}

apply() {
    echo "=== Phase 8 — APPLY ==="

    if [ ! -d "$BACKUP_DIR" ]; then
        echo "ERROR: No backup. Run --backup first."
        exit 1
    fi

    # 1. Copier les fichiers source
    for f in "${FILES_TO_COPY[@]}"; do
        local src="$SCRIPT_DIR/$f"
        local dst="$ROOT/$f"
        if [ ! -f "$src" ]; then
            echo "  ⚠️  MISSING: $f"
            continue
        fi

        mkdir -p "$(dirname "$dst")"
        cp "$src" "$dst"
        echo "  ✓ $f"
    done

    # 2. Décommenter les imports dans app.js
    local app_js="$ROOT/resources/js/app.js"
    if [ -f "$app_js" ]; then
        sed -i.tmp -E "s|^// import './pwa';|import './pwa';|" "$app_js"
        sed -i.tmp -E "s|^// import './push-notifications';|import './push-notifications';|" "$app_js"
        rm -f "${app_js}.tmp"
        echo "  ✓ Uncommented JS imports in app.js"
    fi

    echo ""
    echo "=== Phase 8 files applied ==="
    echo ""
    echo "NEXT STEPS (manual):"
    echo ""
    echo "  1. composer install"
    echo "     (already in composer.json: minishlink/web-push)"
    echo ""
    echo "  2. php artisan migrate"
    echo ""
    echo "  3. php artisan webpush:vapid"
    echo "     Copy the 3 lines (VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT)"
    echo "     to your .env file"
    echo ""
    echo "  4. Generate PWA icons in public/icons/ (sizes 72-512 + 2 maskable)"
    echo "     Quick option: realfavicongenerator.net (upload logo, set #2563eb theme)"
    echo "     Manual (ImageMagick):"
    echo "       mkdir -p public/icons"
    echo "       for size in 72 96 128 144 152 192 384 512; do"
    echo "         convert logo.png -resize \${size}x\${size} public/icons/icon-\${size}.png"
    echo "       done"
    echo ""
    echo "  5. npm run build"
    echo ""
    echo "  6. Add to your profile page:"
    echo "     <x-push-toggle />"
    echo ""
    echo "  7. Add to your layouts (just before </body>):"
    echo "     <x-mobile-bottom-nav />"
    echo "     <x-pwa-install-prompt />"
    echo ""
    echo "  8. Test in Chrome DevTools → Application → Service Workers"
    echo ""
    echo "If anything goes wrong: ./apply-phase8.sh --rollback"
}

rollback() {
    echo "=== Phase 8 — ROLLBACK ==="

    if [ ! -d "$BACKUP_DIR" ]; then
        echo "ERROR: No backup at $BACKUP_DIR"
        exit 1
    fi

    # Supprimer les fichiers ajoutés (qui n'étaient pas dans le backup)
    for f in "${FILES_TO_COPY[@]}"; do
        if [ ! -f "$BACKUP_DIR/$f" ] && [ -f "$ROOT/$f" ]; then
            rm "$ROOT/$f"
            echo "  Removed (was new) $f"
        fi
    done

    # Restaurer ceux qui ont été overwrités
    cd "$BACKUP_DIR"
    find . -type f -name "*.php" -o -name "*.js" -o -name "*.json" -o -name "*.html" -o -name "*.webmanifest" | while read -r relfile; do
        local clean=${relfile#./}
        cp "$BACKUP_DIR/$clean" "$ROOT/$clean"
        echo "  Restored $clean"
    done
    cd "$ROOT"

    echo ""
    echo "Run: composer dump-autoload && php artisan optimize:clear && npm run build"
}

show_help() {
    cat <<'EOF'
Phase 8 — Apply PWA + Web Push to existing CleanUx repo

Usage: ./apply-phase8.sh [option]

  --dry-run     List what would change
  --backup      Save current state to .cleanup-backup-phase8/
  --apply       Apply Phase 8 (copy files + uncomment JS imports)
  --rollback    Restore from backup
  --help        This help

Recommended sequence:
  ./apply-phase8.sh --backup
  ./apply-phase8.sh --dry-run
  ./apply-phase8.sh --apply
  composer install
  php artisan migrate
  php artisan webpush:vapid    # copy keys to .env
  npm run build
  # Generate icons in public/icons/
  # Add <x-push-toggle /> in profile, <x-mobile-bottom-nav /> in layouts
EOF
}

case "$MODE" in
    --dry-run)  dry_run ;;
    --backup)   backup ;;
    --apply)    apply ;;
    --rollback) rollback ;;
    --help|*)   show_help ;;
esac
