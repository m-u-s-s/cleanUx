/**
 * L'ESPACE SOCIÉTÉ PRESTATAIRE DANS L'AIGUILLAGE DE DÉMARRAGE.
 *
 * POURQUOI UN TROISIÈME ESPACE. Les cinq écrans société — répartition, équipe, équipes terrain,
 * tâches, canaux — pendaient de l'onglet Profil, derrière une liste de boutons. Un gérant ouvre son
 * application POUR eux : ils sont son métier, pas un réglage. Et l'onglet Profil vit dans
 * `TabNavigator`, qui monte `usePresenceHeartbeat()` — un gérant au bureau apparaissait donc
 * DISPONIBLE au dispatch de sa propre société.
 *
 * L'ORDRE DES CONDITIONS EST TOUT, comme pour l'espace d'administration :
 *
 *   - la société se teste APRÈS l'administration : un compte qui est les deux garde le choix ;
 *   - elle se teste AVANT `providerOnboarding` : un gérant n'a pas de dossier de terrain à
 *     compléter, et l'y soumettre l'enfermerait hors de sa propre société ;
 *   - elle ne se déclenche JAMAIS sur le seul `organization_type`, qui vaut `provider_company`
 *     pour le nettoyeur comme pour le patron.
 */
import { resolveSpace } from '@/admin/space';

/** Un gérant : membre d'une société ET habilité à la piloter (`missions.view_all` côté serveur). */
const gerant = { is_admin: false, is_provider: true, can_manage_company: true };

/** Un nettoyeur de la même société : membre, sans droit de pilotage. */
const nettoyeur = { is_admin: false, is_provider: true, can_manage_company: false };

const gerantEtAdmin = { is_admin: true, is_provider: true, can_manage_company: true };

describe('resolveSpace — espace société prestataire', () => {
  it('ouvre l’espace société au gérant', () => {
    expect(
      resolveSpace({
        isLoading: false,
        isAuthenticated: true,
        user: gerant,
        onboardingComplete: true,
      }),
    ).toBe('providerCompany');
  });

  it('N’Y ENVOIE PAS le nettoyeur, qui garde son espace terrain', () => {
    expect(
      resolveSpace({
        isLoading: false,
        isAuthenticated: true,
        user: nettoyeur,
        onboardingComplete: true,
      }),
    ).toBe('provider');
  });

  it('n’impose pas au gérant le parcours de vérification terrain', () => {
    // Le dossier de terrain est INCOMPLET, et c'est sans importance : un gérant ne pose pas de
    // pièce d'identité pour piloter ses équipes. L'y soumettre le laisserait dehors.
    expect(
      resolveSpace({
        isLoading: false,
        isAuthenticated: true,
        user: gerant,
        onboardingComplete: false,
      }),
    ).toBe('providerCompany');
  });

  it('laisse le nettoyeur passer par son parcours de vérification', () => {
    expect(
      resolveSpace({
        isLoading: false,
        isAuthenticated: true,
        user: nettoyeur,
        onboardingComplete: false,
      }),
    ).toBe('providerOnboarding');
  });

  it('fait choisir un compte à la fois administrateur et gérant', () => {
    expect(
      resolveSpace({ isLoading: false, isAuthenticated: true, user: gerantEtAdmin }),
    ).toBe('switcher');
  });

  it('respecte le choix « terrain » d’un gérant qui intervient aussi', () => {
    // Un patron de petite société nettoie souvent lui-même. Le retenir dans l'espace de pilotage
    // lui retirerait ses missions.
    expect(
      resolveSpace({
        isLoading: false,
        isAuthenticated: true,
        user: gerant,
        onboardingComplete: true,
        chosenSpace: 'provider',
      }),
    ).toBe('provider');
  });

  it('respecte le choix « société » et n’ouvre pas l’administration à sa place', () => {
    expect(
      resolveSpace({
        isLoading: false,
        isAuthenticated: true,
        user: gerantEtAdmin,
        onboardingComplete: true,
        chosenSpace: 'providerCompany',
      }),
    ).toBe('providerCompany');
  });

  it('ignore le drapeau quand le serveur ne l’a pas envoyé', () => {
    // Le parc installé porte des jetons émis avant ce champ. Sans lui, le compte est traité en
    // prestataire de terrain — ce qu'il était hier, et ce qui ne ferme aucune porte.
    expect(
      resolveSpace({
        isLoading: false,
        isAuthenticated: true,
        user: { is_admin: false, is_provider: true },
        onboardingComplete: true,
      }),
    ).toBe('provider');
  });
});
