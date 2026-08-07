import { Page, expect, type Cookie } from '@playwright/test';

/** Mot de passe partagé des comptes QA (cf. database/seeders/QaAccountsSeeder.php). */
export const QA_PASSWORD = 'QaPhase2!';

/** Emails seedés par rôle (cf. tools/visual-qa/modules.mjs CREDENTIALS). */
export const CREDENTIALS = {
  admin: 'admin@brio.test',
  provider_company: 'qa-provider-company@brio.test',
  entreprise: 'dominique.monnier@example.org',
  provider: 'bsanchez@example.org',
  client: 'lemoine.gabrielle@example.net',
} as const;

export type Role = keyof typeof CREDENTIALS;

/**
 * Sessions déjà ouvertes, par rôle.
 *
 * La connexion est limitée à CINQ TENTATIVES PAR MINUTE et par compte (cf.
 * FortifyServiceProvider). Or plusieurs tests emploient le même rôle : en repassant par le
 * formulaire à chaque fois, la suite se faisait limiter et les derniers tests échouaient — avec un
 * message « login failed » qui désignait un rôle, alors que le rôle n'y était pour rien. Le
 * symptôme se déplaçait d'ailleurs d'un rôle à l'autre selon l'ordre d'exécution, ce qui est la
 * signature d'une limitation et non d'un défaut d'authentification.
 *
 * On ouvre donc UNE session par rôle et on réinjecte ses cookies ensuite. C'est aussi ce que fait
 * un utilisateur réel : il ne se reconnecte pas entre deux pages.
 *
 * La suite tourne en `workers: 1` (cf. playwright.config.ts) : ce cache est partagé par tous les
 * tests du run.
 */
const sessions = new Map<Role, Cookie[]>();

/**
 * Connecte la page avec le compte QA du rôle donné — par le formulaire la première fois, puis en
 * réutilisant la session.
 *
 * Échoue si l'utilisateur reste sur /login (authentification KO).
 */
export async function loginAs(page: Page, role: Role): Promise<void> {
  const known = sessions.get(role);

  if (known) {
    await page.context().addCookies(known);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    // Une session réinjectée peut avoir expiré : on retombe alors sur le formulaire, et on
    // repasse par une vraie connexion plutôt que de laisser le test échouer sur un cookie mort.
    if (!page.url().includes('/login')) {
      return;
    }

    sessions.delete(role);
  }

  await page.goto('/login', { waitUntil: 'domcontentloaded' });

  // Le champ doit être RÉELLEMENT prêt : cliquer sur un formulaire encore en cours de rendu
  // envoyait une requête que rien n'attendait ensuite.
  const email = page.locator('input[name="email"]');
  await email.waitFor({ state: 'visible', timeout: 20_000 });
  await email.fill(CREDENTIALS[role]);
  await page.locator('input[name="password"]').fill(QA_PASSWORD);

  /*
   * On attend la RÉPONSE du POST, pas un changement d'URL.
   *
   * L'ancienne version enveloppait l'attente dans un `.catch(() => {})` : un dépassement de délai
   * était donc avalé, et l'assertion suivante annonçait « login failed » alors que la connexion
   * n'avait simplement pas eu le temps d'aboutir. Le message accusait le rôle ; le rôle n'y était
   * pour rien, et le symptôme se déplaçait d'un run à l'autre.
   *
   * Le statut de cette réponse dit exactement ce qui s'est passé — 302 réussi, 419 jeton expiré,
   * 429 trop de tentatives, 422 identifiants refusés — et il est repris dans le message d'échec.
   */
  const [response] = await Promise.all([
    page.waitForResponse(
      (r) => r.url().includes('/login') && r.request().method() === 'POST',
      { timeout: 45_000 },
    ),
    // Entrée dans le champ mot de passe plutôt qu'un clic sur le bouton : c'est la soumission
    // NATIVE du formulaire. Le clic partait parfois sans qu'aucune requête ne suive — le bouton
    // est un composant stylé, et selon l'état d'hydratation de la page le geste se perdait. Le
    // symptôme changeait de rôle à chaque exécution, ce qui l'a longtemps fait passer pour un
    // problème d'authentification.
    page.locator('input[name="password"]').press('Enter'),
  ]);

  const status = response.status();

  await page.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 45_000 }).catch(() => {});

  expect(
    page.url(),
    `login failed for ${role} (${CREDENTIALS[role]}) — POST /login a répondu ${status}`,
  ).not.toContain('/login');

  sessions.set(role, await page.context().cookies());
}
