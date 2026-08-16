/**
 * L'AIGUILLAGE DU CONTRÔLE FACIAL — l'ordre des conditions, et le défaut sûr.
 *
 * Deux choses se jouent ici, et une seule est visible à l'œil :
 *
 *   1. L'ORDRE. Le contrôle facial se teste APRÈS l'administration, la société et le dossier
 *      d'inscription. Placé plus haut, il enfermerait un administrateur ou un gérant hors de leur
 *      propre espace pour un selfie qu'ils n'ont aucune raison de donner — c'est exactement
 *      l'erreur que le parcours d'inscription avait déjà commise avec l'administrateur.
 *
 *   2. LE DÉFAUT SÛR. `undefined` laisse passer. Une requête de statut qui échoue ne doit pas
 *      enfermer un prestataire hors de son application : le serveur refusera de toute façon la
 *      mise en ligne, l'acceptation et le départ. La sécurité se joue là, pas ici.
 */
import { resolveSpace } from '@/admin/space';
import { faceCheckBloqueLeTerrain } from '@/faceCheck';

const provider = { is_admin: false, is_provider: true };
const admin = { is_admin: true, is_provider: false };
const gerant = { is_admin: false, is_provider: true, can_manage_company: true };

const base = { isLoading: false, isAuthenticated: true, onboardingComplete: true } as const;

describe('resolveSpace et le contrôle facial', () => {
  it('ouvre le contrôle quand il barre la route', () => {
    expect(resolveSpace({ ...base, user: provider, faceCheckBlocks: true })).toBe('faceCheck');
  });

  it('laisse passer quand rien ne barre la route', () => {
    expect(resolveSpace({ ...base, user: provider, faceCheckBlocks: false })).toBe('provider');
  });

  it('laisse passer quand on ne sait pas', () => {
    expect(resolveSpace({ ...base, user: provider, faceCheckBlocks: undefined })).toBe('provider');
  });

  it('n’enferme jamais un administrateur', () => {
    expect(resolveSpace({ ...base, user: admin, faceCheckBlocks: true })).toBe('admin');
  });

  it('n’enferme jamais un gérant de société', () => {
    expect(resolveSpace({ ...base, user: gerant, faceCheckBlocks: true })).toBe('providerCompany');
  });

  it('le dossier d’inscription passe AVANT le visage', () => {
    // Sans pièce d'identité déposée, l'appariement des deux n'aurait rien à comparer.
    expect(
      resolveSpace({
        isLoading: false,
        isAuthenticated: true,
        user: provider,
        onboardingComplete: false,
        faceCheckBlocks: true,
      }),
    ).toBe('providerOnboarding');
  });
});

describe('faceCheckBloqueLeTerrain', () => {
  it('ne bloque pas un prestataire hors périmètre', () => {
    expect(faceCheckBloqueLeTerrain({ required: false, state: 'ok' })).toBe(false);
  });

  it('ne bloque pas sans statut connu', () => {
    expect(faceCheckBloqueLeTerrain(undefined)).toBe(false);
  });

  it('bloque sur chacun des états qui exigent un geste', () => {
    const etats = [
      'face_enrolment_required',
      'face_check_required',
      'face_check_pending',
      'face_check_blocked',
    ] as const;

    etats.forEach((state) => {
      expect(faceCheckBloqueLeTerrain({ required: true, state })).toBe(true);
    });
  });

  it('ne bloque pas quand tout est en règle', () => {
    expect(faceCheckBloqueLeTerrain({ required: true, state: 'ok' })).toBe(false);
  });
});
