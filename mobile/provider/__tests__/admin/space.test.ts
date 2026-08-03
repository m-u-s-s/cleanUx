/**
 * L'aiguillage d'espace au démarrage.
 *
 * Le défaut corrigé : `RootNavigator` faisait passer TOUT compte authentifié par le parcours de
 * vérification prestataire, puis par des onglets dont chaque écran appelle des routes gardées
 * `role:employe`. Un administrateur obtenait donc un jeton valide et une application qui échoue
 * partout — alors que le serveur, lui, l'accepte explicitement dans cet APK.
 *
 * La décision est extraite en fonction pure parce que l'ordre de ses conditions est tout : tester
 * cet ordre à travers un arbre de navigation monté demanderait de simuler la moitié de l'app pour
 * observer une seule branche.
 */
import { resolveSpace } from '@/admin/space';

const admin = { is_admin: true, is_provider: false };
const provider = { is_admin: false, is_provider: true };
const doubleCasquette = { is_admin: true, is_provider: true };

describe('resolveSpace', () => {
  it('attend pendant le chargement de la session', () => {
    expect(resolveSpace({ isLoading: true, isAuthenticated: false, user: null })).toBe('loading');
  });

  it('renvoie à la connexion quand personne n’est authentifié', () => {
    expect(resolveSpace({ isLoading: false, isAuthenticated: false, user: null })).toBe('login');
  });

  it('ouvre l’espace admin pour un administrateur', () => {
    expect(resolveSpace({ isLoading: false, isAuthenticated: true, user: admin })).toBe('admin');
  });

  it('garde son espace au prestataire', () => {
    expect(
      resolveSpace({
        isLoading: false,
        isAuthenticated: true,
        user: provider,
        onboardingComplete: true,
      }),
    ).toBe('provider');
  });

  it('fait choisir la double casquette', () => {
    expect(
      resolveSpace({ isLoading: false, isAuthenticated: true, user: doubleCasquette }),
    ).toBe('switcher');
  });

  it('respecte l’espace déjà choisi par la double casquette', () => {
    expect(
      resolveSpace({
        isLoading: false,
        isAuthenticated: true,
        user: doubleCasquette,
        chosenSpace: 'provider',
        onboardingComplete: true,
      }),
    ).toBe('provider');

    expect(
      resolveSpace({
        isLoading: false,
        isAuthenticated: true,
        user: doubleCasquette,
        chosenSpace: 'admin',
      }),
    ).toBe('admin');
  });

  it('n’enferme jamais un administrateur dans le parcours prestataire', () => {
    // Le cœur du défaut. Un administrateur n'a pas de dossier prestataire à compléter : évaluer
    // le parcours avant la qualité d'administrateur le laissait dehors de sa propre console.
    expect(
      resolveSpace({
        isLoading: false,
        isAuthenticated: true,
        user: admin,
        onboardingComplete: false,
      }),
    ).toBe('admin');
  });

  it('envoie le prestataire au dossier tant qu’il est incomplet', () => {
    expect(
      resolveSpace({
        isLoading: false,
        isAuthenticated: true,
        user: provider,
        onboardingComplete: false,
      }),
    ).toBe('providerOnboarding');
  });

  it('laisse passer le prestataire quand l’état du dossier est inconnu', () => {
    // Comportement existant préservé : une ERREUR de chargement laisse passer. Mieux vaut un
    // tableau de bord partiellement bloqué par le serveur qu'un utilisateur enfermé hors de son
    // application parce qu'une requête a échoué.
    expect(
      resolveSpace({
        isLoading: false,
        isAuthenticated: true,
        user: provider,
        onboardingComplete: undefined,
      }),
    ).toBe('provider');
  });

  it('traite un compte sans casquette connue comme prestataire', () => {
    // Les jetons émis avant que `/auth/me` ne serve `is_provider` ne portent aucun drapeau. Le
    // parc déjà installé est prestataire : le défaut le renvoie chez lui, pas dans une console
    // d'administration qui lui répondrait 403 partout.
    expect(
      resolveSpace({
        isLoading: false,
        isAuthenticated: true,
        user: {},
        onboardingComplete: true,
      }),
    ).toBe('provider');
  });
});
