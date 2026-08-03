/**
 * L'aiguillage d'espace, monté pour de vrai.
 *
 * `space.test.ts` fige la décision ; ce fichier vérifie que `RootNavigator` la SUIT — qu'un
 * administrateur voit bien la console et non les onglets prestataire, et qu'il n'est pas retenu
 * par le parcours de vérification qui gardait jusqu'ici l'entrée de l'application.
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react-native';
import { NavigationContainer } from '@react-navigation/native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';

notifyManager.setScheduler((callback) => callback());

const mockAuth = { user: null as unknown, isAuthenticated: false, isLoading: false, logout: jest.fn() };
const mockOnboarding = { data: undefined as unknown, isLoading: false, isError: false };

jest.mock('@/auth', () => ({ useAuth: () => mockAuth }));
jest.mock('@/onboarding', () => ({
  useOnboardingProgress: () => mockOnboarding,
  isJourneyComplete: (d: unknown) => (d as { complete?: boolean } | undefined)?.complete === true,
}));

// Les écrans de la console appellent le serveur ; l'aiguillage n'est pas leur sujet.
jest.mock('@/admin/AdminHomeScreen', () => {
  const { Text } = require('react-native');

  return { AdminHomeScreen: () => <Text>Vue d’ensemble</Text> };
});
jest.mock('@/admin/AdminDirectoryScreen', () => {
  const { Text } = require('react-native');

  return { AdminDirectoryScreen: () => <Text>Modules</Text> };
});

// L'écran de connexion consomme tout le module `@/auth` (useLogin, useBiometricAuth…), que ce
// fichier remplace par le seul `useAuth`. Il n'est pas le sujet ici.
jest.mock('@/screens/LoginScreen', () => {
  const { Text, View } = require('react-native');

  return { LoginScreen: () => <View testID="login-screen"><Text>Connexion</Text></View> };
});

// Le TabNavigator prestataire monte le battement de présence et une carte native.
jest.mock('@/navigation/TabNavigator', () => {
  const { Text, View } = require('react-native');

  return { TabNavigator: () => <View testID="provider-tabs"><Text>Espace terrain</Text></View> };
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

describe('RootNavigator — aiguillage d’espace', () => {
  beforeEach(() => {
    mockAuth.user = null;
    mockAuth.isAuthenticated = false;
    mockAuth.isLoading = false;
    mockOnboarding.data = { complete: true };
    mockOnboarding.isLoading = false;
    mockOnboarding.isError = false;
  });

  it('ouvre la console pour un administrateur', async () => {
    mockAuth.user = { id: 1, name: 'Admin', is_admin: true, is_provider: false };
    mockAuth.isAuthenticated = true;

    renderRoot();

    expect(await screen.findByTestId('admin-navigator')).toBeTruthy();
    expect(screen.queryByTestId('provider-tabs')).toBeNull();
  });

  it('n’enferme pas l’administrateur dans le parcours prestataire', async () => {
    // Le défaut corrigé : l'administrateur n'a pas de dossier prestataire, donc le parcours le
    // jugeait éternellement incomplet et ne le laissait jamais entrer.
    mockAuth.user = { id: 1, name: 'Admin', is_admin: true, is_provider: false };
    mockAuth.isAuthenticated = true;
    mockOnboarding.data = { complete: false };

    renderRoot();

    expect(await screen.findByTestId('admin-navigator')).toBeTruthy();
  });

  it('garde son espace au prestataire', async () => {
    mockAuth.user = { id: 2, name: 'Presta', is_admin: false, is_provider: true };
    mockAuth.isAuthenticated = true;

    renderRoot();

    expect(await screen.findByTestId('provider-tabs')).toBeTruthy();
    expect(screen.queryByTestId('admin-navigator')).toBeNull();
  });

  it('fait choisir la double casquette', async () => {
    mockAuth.user = { id: 3, name: 'Les deux', is_admin: true, is_provider: true };
    mockAuth.isAuthenticated = true;

    renderRoot();

    expect(await screen.findByTestId('space-switcher')).toBeTruthy();
    expect(screen.queryByTestId('admin-navigator')).toBeNull();
    expect(screen.queryByTestId('provider-tabs')).toBeNull();
  });

  it('renvoie à la connexion quand personne n’est authentifié', async () => {
    renderRoot();

    await waitFor(() => expect(screen.getByTestId('root-navigator')).toBeTruthy());
    expect(screen.queryByTestId('admin-navigator')).toBeNull();
  });
});
