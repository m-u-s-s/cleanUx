/**
 * LES ONGLETS DE L'ESPACE SOCIÉTÉ SUIVENT LES CLÉS DU SERVEUR.
 *
 * `can_manage_company` ouvre l'espace — il ne dit pas ce qu'on y fait. Un responsable qualité l'a
 * (il porte `missions.view_all`) sans avoir ni `missions.dispatch` ni `team.view` : la barre lui
 * proposait pourtant Répartition et Équipes terrain, deux onglets dont les API répondent 403 depuis
 * que le lot 1 a posé ses gardes.
 *
 * ON MONTE LE NAVIGATEUR ET ON REGARDE LA BARRE, plutôt que de lire la source du fichier de
 * navigation : un onglet déclaré n'est pas un onglet rendu, et c'est exactement la distinction que
 * ce dépôt a déjà payée.
 */
import React from 'react';
import { render, screen } from '@testing-library/react-native';
import { NavigationContainer } from '@react-navigation/native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';

notifyManager.setScheduler((callback) => callback());

const mockAuth = { user: null as unknown, logout: jest.fn() };

jest.mock('@/auth', () => ({
  useAuth: () => mockAuth,
  can: jest.requireActual('../../../shared/src/auth/permissions').can,
}));

jest.mock('@/screens/company/CompanyOverviewScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanyOverviewScreen: () => <View testID="ecran-accueil"><Text>Ma société</Text></View> };
});
jest.mock('@/screens/company/CompanyDispatchScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanyDispatchScreen: () => <View testID="ecran-repartition"><Text>Écran répartition</Text></View> };
});
jest.mock('@/screens/company/CompanyFieldTeamsScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanyFieldTeamsScreen: () => <View testID="ecran-equipes"><Text>Écran équipes</Text></View> };
});
jest.mock('@/screens/company/CompanyTasksScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanyTasksScreen: () => <View testID="ecran-taches"><Text>Écran tâches</Text></View> };
});
jest.mock('@/screens/company/CompanyChannelsScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanyChannelsScreen: () => <View testID="ecran-canaux"><Text>Écran canaux</Text></View> };
});
jest.mock('@/screens/company/CompanyProfileScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanyProfileScreen: () => <View testID="ecran-profil"><Text>Écran profil</Text></View> };
});

import { ProviderCompanyNavigator } from '@/company/ProviderCompanyNavigator';

function monter() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <NavigationContainer>
        <ProviderCompanyNavigator />
      </NavigationContainer>
    </QueryClientProvider>,
  );
}

describe('ProviderCompanyNavigator — onglets selon les clés', () => {
  it('donne au gérant sa barre complète', async () => {
    mockAuth.user = {
      can_manage_company: true,
      organization_permissions: ['missions.view_all', 'missions.dispatch', 'team.view'],
    };

    monter();

    expect(await screen.findByText('Accueil')).toBeTruthy();
    expect(screen.getByText('Répartition')).toBeTruthy();
    expect(screen.getByText('Équipes')).toBeTruthy();
    expect(screen.getByText('Tâches')).toBeTruthy();
    expect(screen.getByText('Profil')).toBeTruthy();
  });

  it('retire au responsable qualité les deux onglets qui lui répondraient 403', async () => {
    mockAuth.user = {
      can_manage_company: true,
      organization_permissions: ['missions.view_all', 'missions.quality', 'analytics.view'],
    };

    monter();

    expect(await screen.findByText('Accueil')).toBeTruthy();
    expect(screen.queryByText('Répartition')).toBeNull();
    expect(screen.queryByText('Équipes')).toBeNull();

    // Ce qui reste doit rester : les tâches sont bornées dans la requête, et le profil est la
    // SEULE porte de sortie de cet espace.
    expect(screen.getByText('Tâches')).toBeTruthy();
    expect(screen.getByText('Profil')).toBeTruthy();
  });

  it('applique le défaut-refus quand le serveur ne déclare aucune clé', async () => {
    mockAuth.user = { can_manage_company: true };

    monter();

    expect(await screen.findByText('Accueil')).toBeTruthy();
    expect(screen.queryByText('Répartition')).toBeNull();
    expect(screen.getByText('Profil')).toBeTruthy();
  });
});
