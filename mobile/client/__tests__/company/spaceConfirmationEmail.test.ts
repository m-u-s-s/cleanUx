/**
 * LE MUR DE CONFIRMATION D'ADRESSE, ET SON EMPLACEMENT DANS L'AIGUILLAGE.
 *
 * Depuis le 2026-08-27 le serveur exige une adresse confirmée sur 530 de ses 537 routes
 * authentifiées. Le sélecteur d'espace n'a rien à proposer à qui ne peut ouvrir aucun écran.
 *
 * C'EST L'EMPLACEMENT QUI SE TESTE ICI, pas la règle — elle a ses propres cas dans
 * `shared/src/auth/__tests__`.
 */
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { resolveClientSpace } from '@/company/space';

const nonConfirmee = { email_verified: false };
const confirmee = { email_verified: true };

const particulier = { is_entreprise: false };
const membreSociete = { is_entreprise: true };

const espace = (user: Record<string, unknown>, chosenSpace?: 'personal' | 'clientCompany') =>
  resolveClientSpace({ isLoading: false, isAuthenticated: true, user, chosenSpace });

const RELATIF = '../../src/navigation/RootNavigator.tsx';

describe('le mur de confirmation passe avant le choix d’espace', () => {
  it('barre la route à un particulier dont l’adresse n’est pas confirmée', () => {
    expect(espace({ ...particulier, ...nonConfirmee })).toBe('emailNonConfirme');
  });

  it('barre la route à un membre de société, choix retenu ou non', () => {
    expect(espace({ ...membreSociete, ...nonConfirmee })).toBe('emailNonConfirme');
    expect(espace({ ...membreSociete, ...nonConfirmee }, 'clientCompany')).toBe('emailNonConfirme');
  });

  /**
   * LE TÉMOIN. Sans lui, les cas ci-dessus passeraient au vert si la fonction rendait
   * `emailNonConfirme` pour tout le monde : on ne mesurerait plus l'adresse, mais une panne.
   */
  it('laisse chacun rejoindre son espace une fois l’adresse confirmée', () => {
    expect(espace({ ...particulier, ...confirmee })).toBe('personal');
    expect(espace({ ...membreSociete, ...confirmee })).toBe('switcher');
    expect(espace({ ...membreSociete, ...confirmee }, 'clientCompany')).toBe('clientCompany');
  });

  it('ne prend pas le pas sur la connexion : sans compte, c’est la connexion qui décide', () => {
    expect(resolveClientSpace({ isLoading: false, isAuthenticated: false, user: null })).toBe('login');
  });

  /** Le parc déjà installé porte des jetons émis avant ce champ : l'absence ne doit enfermer personne. */
  it('laisse passer un jeton qui ne porte pas encore le champ', () => {
    expect(espace(particulier)).toBe('personal');
    expect(espace(membreSociete)).toBe('switcher');
  });
});

/**
 * L'AUTRE MOITIÉ : une porte y mène-t-elle ?
 *
 * Un état d'aiguillage que le navigateur ne rend nulle part laisserait un écran blanc — c'est le
 * mode d'échec dominant de ce dépôt, et `tsc` n'en dit rien : le discriminant serait simplement
 * jamais comparé.
 */
describe('le navigateur rend cet état', () => {
  it('compare le discriminant ET monte l’écran', () => {
    const source = readFileSync(join(__dirname, RELATIF), 'utf8');

    expect(source).toContain("space === 'emailNonConfirme'");
    expect(source).toContain('<EcranConfirmationEmail />');
    expect(source).toMatch(/import \{ EcranConfirmationEmail \}/);
  });
});
