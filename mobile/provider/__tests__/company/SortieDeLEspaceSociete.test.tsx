/**
 * ON DOIT POUVOIR SORTIR DE L'ESPACE SOCIÉTÉ — DESCENDRE SUR LE TERRAIN, ET SE DÉCONNECTER.
 *
 * POURQUOI CE FICHIER EXISTE. `reachability.test.ts` garde déjà l'issue... en asserant que
 * `RootNavigator.tsx` contient `name="Profile"`. C'est une assertion sur la DÉCLARATION d'une
 * route, pas sur le fait qu'on y arrive : `navigate('Profile')` n'existe nulle part dans cette
 * application, aucun lien profond n'y mène, et la barre à cinq onglets de l'espace société n'en
 * parle pas. Un gérant entré dans son espace n'avait plus aucun moyen de se déconnecter.
 *
 * ET LE RETOUR NE PEUT PAS PASSER PAR `clear()` ICI — c'est la différence avec l'application
 * cliente et avec la console d'administration. `resolveSpace` rend `providerCompany` dès que
 * `can_manage_company` est vrai et que le choix n'est pas explicitement `provider` : effacer le
 * choix laisse donc un gérant non-administrateur exactement où il était. Le dernier test de ce
 * fichier fige ce fait, parce que c'est lui qui dicte la forme du bouton.
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';
import { NavigationContainer } from '@react-navigation/native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';

notifyManager.setScheduler((callback) => callback());

const mockAuth = { user: null as unknown, isAuthenticated: false, isLoading: false, logout: jest.fn() };
const mockOnboarding = { data: undefined as unknown, isLoading: false, isError: false };
const mockPreference = { space: undefined as unknown, isLoading: false, choose: jest.fn(), clear: jest.fn() };

jest.mock('@/auth', () => ({ useAuth: () => mockAuth }));
jest.mock('@/onboarding', () => ({
  useOnboardingProgress: () => mockOnboarding,
  isJourneyComplete: (d: unknown) => (d as { complete?: boolean } | undefined)?.complete === true,
}));
jest.mock('@/admin/useSpacePreference', () => ({
  useSpacePreference: () => mockPreference,
}));
jest.mock('@/presence', () => ({
  ...jest.requireActual('@/presence'),
  usePresenceHeartbeat: () => undefined,
}));

jest.mock('@/navigation/TabNavigator', () => {
  const { Text, View } = require('react-native');

  return { TabNavigator: () => <View testID="provider-tabs"><Text>Espace terrain</Text></View> };
});

jest.mock('@/screens/LoginScreen', () => {
  const { Text, View } = require('react-native');

  return { LoginScreen: () => <View testID="login-screen"><Text>Connexion</Text></View> };
});

/*
 * Les cinq écrans d'onglets appellent `/provider/company/*` ; ce n'est pas le sujet ici. L'écran de
 * profil société, LUI, n'est pas bouché — c'est précisément ce qu'on mesure.
 */
jest.mock('@/screens/company/CompanyOverviewScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanyOverviewScreen: () => <View testID="ecran-accueil"><Text>Ma société</Text></View> };
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
jest.mock('@/screens/company/CompanySitesScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanySitesScreen: () => <View testID="ecran-sites"><Text>Sites desservis</Text></View> };
});

import { RootNavigator } from '@/navigation/RootNavigator';
import { resolveSpace } from '@/admin/space';

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

const gerant = {
  id: 7,
  name: 'Patronne',
  email: 'patronne@exemple.be',
  is_admin: false,
  is_provider: true,
  can_manage_company: true,
};

async function ouvrirLEspaceSocietePuisLeProfil() {
  mockAuth.user = gerant;
  mockAuth.isAuthenticated = true;

  renderRoot();
  await screen.findByTestId('ecran-accueil');

  fireEvent.press(screen.getByText('Profil'));
}

describe('Espace société prestataire — la sortie', () => {
  beforeEach(() => {
    mockAuth.user = null;
    mockAuth.isAuthenticated = false;
    mockAuth.isLoading = false;
    mockAuth.logout.mockClear();
    mockOnboarding.data = { complete: true };
    mockOnboarding.isLoading = false;
    mockOnboarding.isError = false;
    mockPreference.space = undefined;
    mockPreference.isLoading = false;
    mockPreference.choose.mockClear();
    mockPreference.clear.mockClear();
  });

  it('offre un onglet Profil dans la barre de l’espace société', async () => {
    mockAuth.user = gerant;
    mockAuth.isAuthenticated = true;

    renderRoot();
    await screen.findByTestId('ecran-accueil');

    expect(screen.getByText('Profil')).toBeTruthy();
  });

  it('laisse se déconnecter depuis l’espace société', async () => {
    await ouvrirLEspaceSocietePuisLeProfil();

    fireEvent.press(screen.getByText('Se déconnecter'));

    expect(mockAuth.logout).toHaveBeenCalled();
  });

  it('laisse le gérant descendre sur le terrain', async () => {
    // Dans une petite société, la patronne nettoie souvent elle-même. Le choix doit être explicite
    // — voir le test suivant, qui dit pourquoi effacer ne suffirait pas.
    await ouvrirLEspaceSocietePuisLeProfil();

    fireEvent.press(screen.getByText('Aller à l’espace terrain'));

    expect(mockPreference.choose).toHaveBeenCalledWith('provider');
  });

  it('n’offre pas le terrain à un gérant qui n’intervient pas', async () => {
    // Un gérant sans casquette prestataire n'a rien à faire dans la pile terrain : chaque écran y
    // appelle des routes gardées `role:employe`.
    mockAuth.user = { ...gerant, is_provider: false };
    mockAuth.isAuthenticated = true;

    renderRoot();
    await screen.findByTestId('ecran-accueil');
    fireEvent.press(screen.getByText('Profil'));

    expect(screen.queryByText('Aller à l’espace terrain')).toBeNull();
    // La déconnexion, elle, reste due à tout le monde.
    expect(screen.getByText('Se déconnecter')).toBeTruthy();
  });

  it('EFFACER LE CHOIX NE LIBÈRE PAS un gérant non-administrateur', () => {
    /*
     * Le fait qui dicte la forme du bouton ci-dessus.
     *
     * Côté client et côté console, `clear()` renvoie au sélecteur. Ici non : `resolveSpace` teste
     * `pilotSociete && chosenSpace !== 'provider'` AVANT tout le reste, et le sélecteur n'est
     * proposé qu'aux comptes administrateur. Un bouton « Changer d'espace » copié de la console
     * aurait donc rendu la main… au même écran, en donnant l'illusion d'une sortie.
     */
    const apresEffacement = resolveSpace({
      isLoading: false,
      isAuthenticated: true,
      user: { is_admin: false, is_provider: true, can_manage_company: true },
      onboardingComplete: true,
      chosenSpace: undefined,
    });

    expect(apresEffacement).toBe('providerCompany');
  });
});
