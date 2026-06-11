import { Page, expect } from '@playwright/test';

/** Mot de passe partagé des comptes QA (cf. database/seeders/QaAccountsSeeder.php). */
export const QA_PASSWORD = 'QaPhase2!';

/** Emails seedés par rôle (cf. tools/visual-qa/modules.mjs CREDENTIALS). */
export const CREDENTIALS = {
  admin: 'admin@cleanux.test',
  provider_company: 'qa-provider-company@cleanux.test',
  entreprise: 'dominique.monnier@example.org',
  provider: 'bsanchez@example.org',
  client: 'lemoine.gabrielle@example.net',
} as const;

export type Role = keyof typeof CREDENTIALS;

/**
 * Connecte la page avec le compte QA du rôle donné via le formulaire /login.
 * Échoue si l'utilisateur reste sur /login (auth KO).
 */
export async function loginAs(page: Page, role: Role): Promise<void> {
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', CREDENTIALS[role]);
  await page.fill('input[name="password"]', QA_PASSWORD);

  await Promise.all([
    page.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 20_000 }).catch(() => {}),
    page.click('button[type="submit"], input[type="submit"]'),
  ]);
  await page.waitForLoadState('load', { timeout: 10_000 }).catch(() => {});

  expect(page.url(), `login failed for ${role} (${CREDENTIALS[role]})`).not.toContain('/login');
}
