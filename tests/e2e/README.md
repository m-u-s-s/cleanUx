# E2E Tests — Client Mobile POC

## Prerequisites

1. Database seeded with beta + legacy users:
   ```bash
   php artisan tinker --execute="
   \$beta = \App\Models\User::firstOrCreate(['email' => 'beta@cleanux.test'], ['name' => 'Beta', 'password' => bcrypt('password'), 'role' => 'client', 'is_active' => true]);
   \$legacy = \App\Models\User::firstOrCreate(['email' => 'legacy@cleanux.test'], ['name' => 'Legacy', 'password' => bcrypt('password'), 'role' => 'client', 'is_active' => true]);
   \Laravel\Pennant\Feature::for(\$beta)->activate('client-mobile-v2');
   "
   ```

2. Terminal 1: `php artisan serve`
3. Terminal 2: `npm run dev`
4. Terminal 3: `npx playwright test --config=tests/e2e/playwright.config.ts`

## Coverage

- ✅ V2 home renders with adaptive light mode
- ✅ Quick action dispatches `cleanux:client-action` window event
- ✅ Mission live switches to dark mode automatically
- ✅ Legacy blade renders when feature flag is off

Runs on `mobile-webkit` (iPhone 13 Pro) and `mobile-chromium` (Pixel 7) — 4 tests × 2 device projects = 8 results.
