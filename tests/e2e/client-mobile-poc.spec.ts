import { test, expect } from '@playwright/test';

test.describe('Client Mobile POC', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', process.env.E2E_USER_EMAIL || 'beta@cleanux.test');
    await page.fill('input[name="password"]', process.env.E2E_USER_PASSWORD || 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL(/dashboard/);
  });

  test('renders V2 home with adaptive light mode', async ({ page }) => {
    await page.goto('/dashboard/client');
    await expect(page.locator('#client-home-island')).toBeVisible();
    const theme = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
    expect(theme).toBe('light');
  });

  test('quick action dispatches event', async ({ page }) => {
    await page.goto('/dashboard/client');
    await page.evaluate(() => {
      (window as any).__lastEvent = null;
      window.addEventListener('cleanux:client-action', (e: any) => {
        (window as any).__lastEvent = e.detail;
      });
    });
    await page.locator('[data-test="quick-action"]').first().click();
    const detail = await page.evaluate(() => (window as any).__lastEvent);
    expect(detail?.id).toBe('urgent');
  });

  test('switches to dark mode on active mission', async ({ page }) => {
    await page.goto('/dashboard/client/missions/1/tracking');
    await expect(page.locator('#mission-live-island')).toBeVisible();
    const theme = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
    expect(theme).toBe('dark');
  });

  test('falls back to legacy blade if feature flag off', async ({ page, context }) => {
    await context.clearCookies();
    await page.goto('/login');
    await page.fill('input[name="email"]', process.env.E2E_LEGACY_USER_EMAIL || 'legacy@cleanux.test');
    await page.fill('input[name="password"]', process.env.E2E_LEGACY_USER_PASSWORD || 'password');
    await page.click('button[type="submit"]');
    await page.goto('/dashboard/client');
    await expect(page.locator('#client-home-island')).toHaveCount(0);
  });
});
