#!/usr/bin/env bash
# sync-shared.sh — copies shared modules from mobile/client/src → mobile/provider/src
#
# Usage:
#   From repo root:   bash mobile/scripts/sync-shared.sh
#   From mobile dir:  bash scripts/sync-shared.sh
#   Via npm script:   npm run sync-shared   (from either mobile/client or mobile/provider)
#
# Run this after editing any of the SHARED_MODULES in mobile/client/src/.
# The canonical source is always mobile/client/src/.

set -euo pipefail

# Resolve repo root regardless of where the script is called from
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
CLIENT_SRC="${REPO_ROOT}/mobile/client/src"
PROVIDER_SRC="${REPO_ROOT}/mobile/provider/src"
CLIENT_MOCKS="${REPO_ROOT}/mobile/client/__mocks__"
PROVIDER_MOCKS="${REPO_ROOT}/mobile/provider/__mocks__"

# Modules that are shared between client and provider apps.
# theme/ui/storage/api/realtime/sentry/config and ErrorBoundary cover ~30% of code.
SHARED_DIRS=(
  "theme"
  "ui"
  "storage"
  "api"
  "realtime"
  "sentry"
  "config"
)

SHARED_FILES=(
  "ErrorBoundary.tsx"
)

echo "Syncing shared mobile modules: ${CLIENT_SRC} → ${PROVIDER_SRC}"
echo ""

# ── Directories ──────────────────────────────────────────────────────────────
for module in "${SHARED_DIRS[@]}"; do
  SRC="${CLIENT_SRC}/${module}"
  DST="${PROVIDER_SRC}/${module}"

  if [ -d "${SRC}" ]; then
    rm -rf "${DST}"
    cp -r "${SRC}" "${DST}"
    echo "  [sync] ${module}/"
  else
    echo "  [skip] ${module}/ (not found in client/src)"
  fi
done

# ── Files ─────────────────────────────────────────────────────────────────────
for file in "${SHARED_FILES[@]}"; do
  SRC="${CLIENT_SRC}/${file}"
  DST="${PROVIDER_SRC}/${file}"

  if [ -f "${SRC}" ]; then
    cp "${SRC}" "${DST}"
    echo "  [sync] ${file}"
  else
    echo "  [skip] ${file} (not found in client/src)"
  fi
done

# ── __mocks__ ─────────────────────────────────────────────────────────────────
if [ -d "${CLIENT_MOCKS}" ]; then
  echo ""
  echo "Syncing __mocks__: ${CLIENT_MOCKS} → ${PROVIDER_MOCKS}"
  mkdir -p "${PROVIDER_MOCKS}"
  for src in "${CLIENT_MOCKS}"/*; do
    name="$(basename "${src}")"
    dst="${PROVIDER_MOCKS}/${name}"
    if [ -d "${src}" ]; then
      rm -rf "${dst}"
      cp -r "${src}" "${dst}"
    else
      cp "${src}" "${dst}"
    fi
    echo "  [sync] __mocks__/${name}"
  done
fi

echo ""
echo "Done. Shared modules synced from client → provider."
