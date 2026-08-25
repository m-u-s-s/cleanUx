/**
 * Le formatage des cellules du moteur de console.
 *
 * Le type vient du descripteur, jamais d'une devinette sur la valeur : deviner afficherait un
 * montant comme un nombre nu et un identifiant à sept chiffres comme un montant.
 */
import { formatCell } from '@/admin/console/format';

describe('formatCell', () => {
  it('rend « — » pour une valeur absente, jamais « 0 » ni « null »', () => {
    // Un zéro affiché pour une donnée manquante se lit comme une mesure, et personne ne va
    // vérifier. C'est le même principe que les compteurs `available` de l'accueil.
    expect(formatCell(null, 'number')).toBe('—');
    expect(formatCell(undefined, 'money')).toBe('—');
    expect(formatCell('', 'text')).toBe('—');
  });

  it('distingue un vrai zéro d’une absence', () => {
    expect(formatCell(0, 'number')).not.toBe('—');
    expect(formatCell(false, 'bool')).toBe('Non');
  });

  it('rend un booléen en toutes lettres', () => {
    expect(formatCell(true, 'bool')).toBe('Oui');
    expect(formatCell(false, 'bool')).toBe('Non');
  });

  it('rend une date au format belge', () => {
    expect(formatCell('2026-08-03T14:30:00+02:00', 'date')).toBe('03/08/2026');
  });

  it('ajoute l’heure pour un datetime', () => {
    expect(formatCell('2026-08-03T14:30:00Z', 'datetime')).toMatch(/^03\/08\/2026/);
    expect(formatCell('2026-08-03T14:30:00Z', 'datetime')).toMatch(/\d{2}:\d{2}$/);
  });

  it('rend une date illisible telle quelle plutôt que « Invalid Date »', () => {
    // La valeur brute permet au moins de comprendre d'où vient le problème.
    expect(formatCell('pas-une-date', 'date')).toBe('pas-une-date');
  });

  it('rend un montant en euros', () => {
    const rendu = formatCell(1234.5, 'money');

    expect(rendu).toContain('234');
    expect(rendu).toMatch(/€/);
  });

  /**
   * LA DEVISE VIENT DE LA DONNEE, PAS DU FORMATEUR.
   *
   * `currency: 'EUR'` etait code en dur : la console affichait TOUS les montants en euros,
   * y compris ceux d'une zone facturee en dirhams. Sur un ecran de pilotage, un montant faux
   * se propage en decisions fausses.
   */
  it('rend un montant dans la devise qu on lui donne', () => {
    const rendu = formatCell(1234.5, 'money', 'MAD');

    expect(rendu).toContain('234');
    expect(rendu).not.toMatch(/€/);
  });

  /** TEMOIN — sans devise fournie, l'euro reste le defaut : le comportement d'avant tient. */
  it('retombe sur l euro quand aucune devise n est donnee', () => {
    expect(formatCell(1234.5, 'money', null)).toMatch(/€/);
    expect(formatCell(1234.5, 'money')).toMatch(/€/);
  });

  /** TEMOIN — une devise VIDE ne doit pas produire un format casse, mais le defaut. */
  it('traite une devise vide comme une absence', () => {
    expect(formatCell(1234.5, 'money', '')).toMatch(/€/);
  });

  it('rend un montant illisible tel quel', () => {
    expect(formatCell('beaucoup', 'money')).toBe('beaucoup');
  });

  it('laisse le texte intact', () => {
    expect(formatCell('Zoé Admin', 'text')).toBe('Zoé Admin');
    expect(formatCell('en_attente', 'badge')).toBe('en_attente');
  });
});
