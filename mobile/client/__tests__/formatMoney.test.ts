import { formatMontant, formatCentimes, DEVISE_PAR_DEFAUT } from '@/format/money';

/**
 * LE FORMATEUR MONÉTAIRE PARTAGÉ.
 *
 * Il n'en existait aucun : chaque écran recopiait la même ligne, avec DEUX valeurs figées —
 * `'fr-BE'` et `currency: 'EUR'`. La plateforme sert aussi le Maroc, et un montant en
 * dirhams s'affichait en euros. Ce n'est pas un défaut de format : c'est un engagement
 * commercial qu'on ne tiendra pas.
 *
 * Ce test existe aussi pour que l'alias `@/format` soit vérifié dans les TROIS tables qui le
 * décrivent — tsconfig, Babel et Jest. Elles ne se contrôlent pas entre elles, et `tsc` reste
 * vert quand seule celle de Jest manque : la suite tombe alors à l'import, sans rapport
 * apparent avec le module qu'on vient d'ajouter.
 */
describe('formatMontant', () => {
  it('respecte la devise qu on lui donne', () => {
    const rendu = formatMontant(1234.5, 'MAD');

    expect(rendu).toContain('234');
    expect(rendu).not.toMatch(/€/);
  });

  it('retombe sur la devise par defaut quand aucune n est fournie', () => {
    expect(formatMontant(10)).toMatch(/€/);
    expect(formatMontant(10, null)).toMatch(/€/);
    expect(DEVISE_PAR_DEFAUT).toBe('EUR');
  });

  /** TÉMOIN — une devise VIDE se traite comme une absence, pas comme un format cassé. */
  it('traite une devise vide comme une absence', () => {
    expect(() => formatMontant(10, '')).not.toThrow();
    expect(formatMontant(10, '')).toMatch(/€/);
  });

  /** TÉMOIN — une valeur non finie ne produit ni « NaN € » ni une exception. */
  it('rend un tiret pour une valeur qui n en est pas une', () => {
    expect(formatMontant(Number.NaN)).toBe('—');
    expect(formatMontant(Number.POSITIVE_INFINITY)).toBe('—');
  });
});

describe('formatCentimes', () => {
  /**
   * LA DIVISION PAR CENT VIT ICI, ET NULLE PART AILLEURS.
   *
   * Elle était recopiée dans chaque appelant. Une division oubliée affiche cent fois le prix
   * réel — sur un écran de paiement, c'est le genre d'erreur qu'un client remarque avant nous.
   */
  it('divise les centimes une seule fois', () => {
    const rendu = formatCentimes(5704);

    expect(rendu).toContain('57');
    expect(rendu).not.toContain('5704');
    expect(rendu).not.toContain('5 704');
  });

  it('respecte la devise sur des centimes aussi', () => {
    expect(formatCentimes(5704, 'MAD')).not.toMatch(/€/);
  });

  it('rend un tiret pour une valeur qui n en est pas une', () => {
    expect(formatCentimes(Number.NaN)).toBe('—');
  });
});

describe('les decimales', () => {
  /**
   * DEUX APPELANTS ARRONDISSAIENT DEJA A L'UNITE — une fourchette d'offre immediate et un
   * plafond d'assurance. Sans cette option, les brancher sur le formateur partage leur
   * aurait ajoute des centimes : un progres de coherence paye par une regression d'affichage.
   */
  it('rend un montant sans centimes quand on le demande', () => {
    const rendu = formatCentimes(4500, 'EUR', 0);

    expect(rendu).toContain('45');
    expect(rendu).not.toContain('00');
    expect(rendu).not.toMatch(/[,.]\d/);
  });

  /** TEMOIN — sans l'option, les centimes restent. */
  it('garde les centimes quand on ne demande rien', () => {
    expect(formatCentimes(4550, 'EUR')).toMatch(/[,.]\d\d/);
  });

  /** TEMOIN — l'option n'ecrase pas la devise. */
  it('respecte la devise avec l option', () => {
    expect(formatCentimes(4500, 'MAD', 0)).not.toMatch(/€/);
  });
});
