/**
 * LE MUR DE CONFIRMATION D'ADRESSE, ET SON EMPLACEMENT DANS L'AIGUILLAGE.
 *
 * Depuis le 2026-08-27 le serveur exige une adresse confirmée sur 530 de ses 537 routes
 * authentifiées. Contrairement au dossier d'inscription et au contrôle facial, qui ne concernent
 * que le prestataire de TERRAIN, celui-ci vaut pour tous les rôles.
 *
 * C'EST L'EMPLACEMENT QUI SE TESTE ICI, pas la règle — elle a ses propres cas dans
 * `shared/src/auth/__tests__`. Placé plus bas, ce mur enverrait un administrateur dans une console
 * dont chaque requête répond 403, et un gérant dans une société qu'il ne peut pas piloter.
 */
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { resolveSpace } from '@/admin/space';

const nonConfirmee = { email_verified: false };
const confirmee = { email_verified: true };

const admin = { is_admin: true, is_provider: false };
const provider = { is_admin: false, is_provider: true };
const superAdmin = { is_admin: true, is_super_admin: true };
const gerant = { is_admin: false, is_provider: true, can_manage_company: true };

const espace = (user: Record<string, unknown>, reste: Record<string, unknown> = {}) =>
  resolveSpace({ isLoading: false, isAuthenticated: true, user, ...reste });

const RELATIF = '../../src/navigation/RootNavigator.tsx';

describe('le mur de confirmation passe avant les casquettes', () => {
  it.each([
    ['un administrateur', admin],
    ['un prestataire', provider],
    ['un super administrateur', superAdmin],
    ['un gérant de société', gerant],
  ])('barre la route à %s dont l’adresse n’est pas confirmée', (_nom, casquette) => {
    expect(espace({ ...casquette, ...nonConfirmee })).toBe('emailNonConfirme');
  });

  /**
   * LE TÉMOIN. Sans lui, les cas ci-dessus passeraient au vert si la fonction rendait
   * `emailNonConfirme` pour tout le monde : on ne mesurerait plus l'adresse, mais une panne.
   */
  it.each([
    ['un administrateur', admin, 'admin'],
    ['un prestataire', provider, 'provider'],
    ['un super administrateur', superAdmin, 'superAdmin'],
    ['un gérant de société', gerant, 'providerCompany'],
  ])('laisse %s rejoindre son espace une fois l’adresse confirmée', (_nom, casquette, attendu) => {
    expect(espace({ ...casquette, ...confirmee })).toBe(attendu);
  });

  it('passe AVANT le dossier d’inscription : un prestataire non confirmé ne voit pas le parcours', () => {
    expect(espace({ ...provider, ...nonConfirmee }, { onboardingComplete: false })).toBe('emailNonConfirme');
  });

  it('passe AVANT le contrôle facial, pour la même raison', () => {
    expect(espace({ ...provider, ...nonConfirmee }, { faceCheckBlocks: true })).toBe('emailNonConfirme');
  });

  it('passe AVANT le sélecteur : choisir entre trois espaces qui refusent tous n’a aucun sens', () => {
    expect(espace({ is_admin: true, is_provider: true, ...nonConfirmee })).toBe('emailNonConfirme');
  });

  /** Le parc déjà installé porte des jetons émis avant ce champ : l'absence ne doit enfermer personne. */
  it('laisse passer un jeton qui ne porte pas encore le champ', () => {
    expect(espace(provider)).toBe('provider');
    expect(espace(admin)).toBe('admin');
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
