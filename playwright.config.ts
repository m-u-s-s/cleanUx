import { defineConfig, devices } from '@playwright/test';

/**
 * Audit MEDIUM — E2E par rôle. Smoke flows authentifiés (client, prestataire,
 * admin, entreprise cliente, entreprise prestataire) contre une app servie.
 *
 * Comptes seedés par QaAccountsSeeder (mot de passe partagé `QaPhase2!`).
 * Base URL via E2E_BASE_URL (défaut http://127.0.0.1:8000). En CI, l'app est
 * servie par `php artisan serve` (cf. .github/workflows/ci.yml job `e2e`).
 */
export default defineConfig({
  testDir: './e2e',
  // The app under test is served by single-threaded `php artisan serve`
  // (PHP_CLI_SERVER_WORKERS is unsupported on Windows). Parallel workers
  // hammer it with concurrent requests and deadlock → cascading goto
  // timeouts. Keep the smoke suite serial so it is deterministic locally
  // and in CI. Override with `--workers=N` against a real multi-worker host.
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  timeout: 45_000,
  expect: { timeout: 10_000 },
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
});
