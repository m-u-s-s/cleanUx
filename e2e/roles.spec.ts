import { test, expect } from '@playwright/test';
import { loginAs, type Role } from './helpers/auth';

/**
 * Smoke E2E par rôle : chaque compte QA se connecte et atteint son espace
 * authentifié sans 403 ni retour /login.
 */

test.describe('Auth par rôle', () => {
  const roles: Role[] = ['admin', 'client', 'provider', 'entreprise', 'provider_company'];

  for (const role of roles) {
    test(`${role} se connecte et atteint son dashboard`, async ({ page }) => {
      await loginAs(page, role);

      const resp = await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
      expect(resp?.status(), `${role} → /dashboard doit répondre 2xx/3xx`).toBeLessThan(400);
      expect(page.url(), `${role} ne doit pas être renvoyé au login`).not.toContain('/login');
    });
  }
});

test.describe('Accès aux espaces par rôle', () => {
  const cases: Array<{ role: Role; path: string }> = [
    { role: 'admin', path: '/admin/dashboard' },
    { role: 'client', path: '/dashboard/client' },
    { role: 'provider', path: '/dashboard/employe' },
  ];

  for (const { role, path } of cases) {
    test(`${role} accède à ${path}`, async ({ page }) => {
      await loginAs(page, role);
      const resp = await page.goto(path, { waitUntil: 'domcontentloaded' });
      expect(resp?.status(), `${role} → ${path} doit répondre 2xx`).toBeLessThan(400);
    });
  }
});

test('le client peut ouvrir le parcours de réservation', async ({ page }) => {
  await loginAs(page, 'client');
  const resp = await page.goto('/dashboard/client/rendez-vous/nouveau', { waitUntil: 'domcontentloaded' });
  expect(resp?.status()).toBeLessThan(400);
});

test('un visiteur non authentifié est redirigé vers le login sur une page protégée', async ({ page }) => {
  await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded' });
  expect(page.url()).toContain('/login');
});
