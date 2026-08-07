/**
 * L'ESPACE SOCIÉTÉ, MONTÉ POUR DE VRAI.
 *
 * `spaceProviderCompany.test.ts` fige la DÉCISION ; ce fichier vérifie que `RootNavigator` la SUIT.
 * Les deux sont nécessaires : l'espace société de l'application prestataire existait déjà en écrans
 * complets et testés, derrière une porte qu'aucun test ne franchissait — cinq écrans livrés,
 * joignables par personne.
 *
 * Il garde aussi la règle qui ne se voit dans AUCUN écran : le battement de présence ne doit pas
 * battre ici.
 */
import React from 'react';
import { render, screen } from '@testing-library/react-native';
import { NavigationContainer } from '@react-navigation/native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';

notifyManager.setScheduler((callback) => callback());

const mockAuth = { user: null as unknown, isAuthenticated: false, isLoading: false, logout: jest.fn() };
const mockOnboarding = { data: undefined as unknown, isLoading: false, isError: false };

/** Compté à chaque montage : c'est la preuve que l'espace société ne le déclenche pas. */
const mockBattementsDePresence = jest.fn();

jest.mock('@/auth', () => ({ useAuth: () => mockAuth }));
jest.mock('@/onboarding', () => ({
  useOnboardingProgress: () => mockOnboarding,
  isJourneyComplete: (d: unknown) => (d as { complete?: boolean } | undefined)?.complete === true,
}));
/*
 * On remplace le SEUL battement, pas tout le module.
 *
 * Une fabrique qui rend `{ usePresenceHeartbeat }` seul retire `usePresence`, dont dépend
 * `PresencePill` dans l'espace terrain — l'espace d'en face tombait alors pour une raison qui n'a
 * rien à voir avec ce qu'on mesure ici.
 */
jest.mock('@/presence', () => ({
  ...jest.requireActual('@/presence'),
  usePresenceHeartbeat: () => mockBattementsDePresence(),
}));

// L'espace terrain monte une carte native et tout un tableau de bord ; seule sa PRÉSENCE à l'écran
// nous intéresse, pas son contenu.
jest.mock('@/navigation/TabNavigator', () => {
  const { Text, View } = require('react-native');

  return { TabNavigator: () => <View testID="provider-tabs"><Text>Espace terrain</Text></View> };
});

jest.mock('@/screens/LoginScreen', () => {
  const { Text, View } = require('react-native');

  return { LoginScreen: () => <View testID="login-screen"><Text>Connexion</Text></View> };
});

/*
 * Les écrans société appellent `/provider/company/*` ; l'aiguillage n'est pas leur sujet.
 *
 * Chaque bouchon est écrit EN ENTIER dans sa fabrique plutôt que produit par une aide partagée :
 * `jest.mock` est hissé au-dessus des `const` du fichier, si bien qu'une aide déclarée plus haut
 * n'existe pas encore au moment où la fabrique s'exécute.
 */
jest.mock('@/screens/company/CompanyOverviewScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanyOverviewScreen: () => <View testID="ecran-accueil"><Text>Ma société</Text></View> };
});
jest.mock('@/screens/company/CompanySitesScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanySitesScreen: () => <View testID="ecran-sites"><Text>Sites desservis</Text></View> };
});
jest.mock('@/screens/company/CompanyDispatchScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanyDispatchScreen: () => <View testID="ecran-repartition"><Text>Répartition</Text></View> };
});
jest.mock('@/screens/company/CompanyFieldTeamsScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanyFieldTeamsScreen: () => <View testID="ecran-equipes"><Text>Équipes terrain</Text></View> };
});
jest.mock('@/screens/company/CompanyTasksScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanyTasksScreen: () => <View testID="ecran-taches"><Text>Tâches</Text></View> };
});
jest.mock('@/screens/company/CompanyChannelsScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanyChannelsScreen: () => <View testID="ecran-canaux"><Text>Canaux</Text></View> };
});

import { RootNavigator } from '@/navigation/RootNavigator';

function renderRoot() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <NavigationContainer>
        <RootNavigator />
      </NavigationContainer>
    </QueryClientProvider>,
  );
}

const gerant = { id: 7, name: 'Patronne', is_admin: false, is_provider: true, can_manage_company: true };
const nettoyeur = { id: 8, name: 'Nettoyeur', is_admin: false, is_provider: true, can_manage_company: false };

describe('RootNavigator — espace société prestataire', () => {
  beforeEach(() => {
    mockAuth.user = null;
    mockAuth.isAuthenticated = false;
    mockAuth.isLoading = false;
    mockOnboarding.data = { complete: true };
    mockOnboarding.isLoading = false;
    mockOnboarding.isError = false;
    mockBattementsDePresence.mockClear();
  });

  it('ouvre l’accueil société à un gérant, dès le démarrage', async () => {
    mockAuth.user = gerant;
    mockAuth.isAuthenticated = true;

    renderRoot();

    // L'accueil société est le PREMIER onglet : un gérant veut savoir où en est la journée avant
    // d'agir dessus. Et c'est un espace, pas une liste de boutons enfouie dans le profil.
    expect(await screen.findByTestId('ecran-accueil')).toBeTruthy();
    // La répartition existe, un onglet plus loin — elle n'est pas ce qui s'ouvre en premier.
    expect(screen.queryByTestId('ecran-repartition')).toBeNull();
  });

  it('NE FAIT PAS BATTRE la présence dans l’espace société', async () => {
    /*
     * LA RÈGLE QUI NE SE VOIT DANS AUCUN ÉCRAN.
     *
     * `TabNavigator` monte `usePresenceHeartbeat()` — délibérément, en un point unique : un
     * prestataire de terrain connecté est un prestataire joignable. Un GÉRANT assis à son bureau ne
     * l'est pas. Rendre l'espace société à l'intérieur des onglets terrain le ferait apparaître
     * DISPONIBLE dans le dispatch de sa propre société, et le moteur d'affectation lui proposerait
     * des missions qu'il ne fera jamais.
     *
     * Ce test échouera si quelqu'un remonte l'espace société dans la pile terrain « pour
     * factoriser » — un changement qui n'a l'air de rien et qui fausse le dispatch.
     */
    mockAuth.user = gerant;
    mockAuth.isAuthenticated = true;

    renderRoot();
    await screen.findByTestId('ecran-accueil');

    expect(mockBattementsDePresence).not.toHaveBeenCalled();
  });

  it('n’impose pas au gérant le parcours de vérification terrain', async () => {
    // Un gérant n'a pas de pièce d'identité à scanner pour répartir les missions de ses équipes.
    mockAuth.user = gerant;
    mockAuth.isAuthenticated = true;
    mockOnboarding.data = { complete: false };

    renderRoot();

    expect(await screen.findByTestId('ecran-accueil')).toBeTruthy();
  });

  it('laisse le nettoyeur dans son espace terrain', async () => {
    mockAuth.user = nettoyeur;
    mockAuth.isAuthenticated = true;

    renderRoot();

    // Le nettoyeur appartient à la MÊME société que le gérant — même `organization_type`. S'il
    // atterrissait dans l'espace de pilotage, il perdrait ses missions, ses revenus et sa présence.
    expect(await screen.findByTestId('provider-tabs')).toBeTruthy();
    expect(screen.queryByTestId('ecran-accueil')).toBeNull();
  });
});
