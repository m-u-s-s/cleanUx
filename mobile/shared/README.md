# Shared Mobile Modules

The following modules are shared between `mobile/client/` and `mobile/provider/`.
**The canonical source is always `mobile/client/src/`.**

## Shared modules

| Module | Description |
|---|---|
| `theme/` | Design tokens: colors, spacing, radius, typography, shadows, animations, `useColorScheme`, `useThemeColors` |
| `ui/` | 15+ reusable components (Button, Card, Badge, Avatar, Input, etc.) |
| `storage/` | `expo-secure-store` wrapper (typed get/set/delete) |
| `api/` | Axios client + interceptors + request/response types |
| `realtime/` | Pusher/Reverb client + channel helpers |
| `sentry/` | Crash reporting init (`@sentry/react-native`) |
| `config/` | Env validation (API_URL, WS_URL, feature flags) |
| `ErrorBoundary.tsx` | Top-level crash boundary |
| `__mocks__/` | Jest mocks for native modules (shared between both apps) |

## How to sync

After editing shared code in `mobile/client/src/`, run from the repo root:

```bash
bash mobile/scripts/sync-shared.sh
```

Or via npm from either app directory:

```bash
npm run sync-shared
```

The script copies the canonical source from `client/src/` to `provider/src/`
for all shared modules listed above, including `__mocks__`.

## Why not a monorepo workspace package?

Metro bundler does not resolve imports outside the project root without
custom configuration (`resolver.nodeModulesPaths` / `watchFolders`). The
sync-script approach keeps things simple with zero Metro config changes and
works with Expo managed workflow.

**Migration path to a proper workspace** (when team size justifies it):

1. Create `mobile/shared/` as an npm workspace package (`package.json` with `name: "@cleanux/mobile-shared"`).
2. Add `workspaces: ["shared", "client", "provider"]` to a root `mobile/package.json`.
3. Update `metro.config.js` in each app to add `watchFolders: [path.resolve(__dirname, '../shared')]`.
4. Replace the sync script with a `workspace:*` dependency in each app.
