/**
 * L'ESPACE SOCIÉTÉ CLIENTE, MONTÉ POUR DE VRAI.
 *
 * `space.test.ts` fige la DÉCISION ; ce fichier vérifie que `RootNavigator` la SUIT. Les deux sont
 * nécessaires, et l'histoire de ce dépôt le prouve : l'espace société de l'application prestataire
 * existait en écrans complets et testés, derrière une porte qu'aucun test ne franchissait.
 */
import React from 'react';
import { render, screen } from '@testing-library/react-native';
import { NavigationContainer } from '@react-navigation/native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';

notifyManager.setScheduler((callback) => callback());

const mockAuth = { user: null as unknown, isAuthenticated: false, isLoading: false, logout: jest.fn() };
const mockPreference = { space: undefined as unknown, isLoading: false, choose: jest.fn(), clear: jest.fn() };

jest.mock('@/auth', () => ({ useAuth: () => mockAuth }));
jest.mock('@/company/useClientSpacePreference', () => ({
  useClientSpacePreference: () => mockPreference,
}));

jest.mock('@/screens/LoginScreen', () => {
  const { Text, View } = require('react-native');

  return { LoginScreen: () => <View testID="login-screen"><Text>Connexion</Text></View> };
});

// L'espace personnel monte une carte, des notifications et tout un accueil ; seule sa PRÉSENCE
// nous intéresse ici, pas son contenu.
jest.mock('@/navigation/TabNavigator', () => {
  const { Text, View } = require('react-native');

  return { TabNavigator: () => <View testID="onglets-perso"><Text>Espace perso</Text></View> };
});

/*
 * Les écrans société appellent `/client/company/*`. Chaque bouchon est écrit EN ENTIER dans sa
 * fabrique : `jest.mock` est hissé au-dessus des `const` du fichier, si bien qu'une aide déclarée
 * plus haut n'existe pas encore au moment où la fabrique s'exécute.
 */
jest.mock('@/screens/company/CompanyOverviewScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanyOverviewScreen: () => <View testID="ecran-accueil-societe"><Text>Mon entreprise</Text></View> };
});
jest.mock('@/screens/company/CompanySitesScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanySitesScreen: () => <View testID="ecran-locaux"><Text>Mes locaux</Text></View> };
});
jest.mock('@/screens/company/CompanyBookingsScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanyBookingsScreen: () => <View testID="ecran-reservations"><Text>Réservations</Text></View> };
});
jest.mock('@/screens/company/CompanyBillingScreen', () => {
  const { Text, View } = require('react-native');

  return { CompanyBillingScreen: () => <View testID="ecran-facturation"><Text>Facturation</Text></View> };
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

const particuliere = { id: 1, name: 'Particulière', is_entreprise: false };
const responsableSites = { id: 2, name: 'Responsable', is_entreprise: true };

describe('RootNavigator — espace société cliente', () => {
  beforeEach(() => {
    mockAuth.user = null;
    mockAuth.isAuthenticated = false;
    mockAuth.isLoading = false;
    mockPreference.space = undefined;
    mockPreference.isLoading = false;
  });

  it('ne pose aucune question à un particulier', async () => {
    // Lui montrer un sélecteur serait lui demander de trancher entre une porte et un mur.
    mockAuth.user = particuliere;
    mockAuth.isAuthenticated = true;

    renderRoot();

    expect(await screen.findByTestId('onglets-perso')).toBeTruthy();
    expect(screen.queryByTestId('client-space-switcher')).toBeNull();
  });

  it('fait choisir un membre de société qui n’a pas encore choisi', async () => {
    mockAuth.user = responsableSites;
    mockAuth.isAuthenticated = true;

    renderRoot();

    expect(await screen.findByTestId('client-space-switcher')).toBeTruthy();
    expect(screen.queryByTestId('onglets-perso')).toBeNull();
  });

  it('ouvre l’accueil société quand c’est le choix retenu', async () => {
    mockAuth.user = responsableSites;
    mockAuth.isAuthenticated = true;
    mockPreference.space = 'clientCompany';

    renderRoot();

    expect(await screen.findByTestId('ecran-accueil-societe')).toBeTruthy();
    expect(screen.queryByTestId('onglets-perso')).toBeNull();
  });

  it('rend ses propres réservations à qui a choisi « perso »', async () => {
    // Elle gère vingt immeubles au bureau et commande son ménage le samedi : le choix « perso »
    // ne doit pas être réinterprété à chaque lancement.
    mockAuth.user = responsableSites;
    mockAuth.isAuthenticated = true;
    mockPreference.space = 'personal';

    renderRoot();

    expect(await screen.findByTestId('onglets-perso')).toBeTruthy();
    expect(screen.queryByTestId('ecran-accueil-societe')).toBeNull();
  });

  it('ignore un choix « société » devenu caduc après un départ', async () => {
    /*
     * Quelqu'un quitte son entreprise : le choix reste écrit localement, `is_entreprise` retombe à
     * faux. Sans la règle « le droit avant le choix », l'application ouvrirait un espace dont
     * chaque écran répond 403, et dont il ne pourrait pas sortir — le sélecteur ne s'affiche
     * qu'à qui a le choix.
     */
    mockAuth.user = particuliere;
    mockAuth.isAuthenticated = true;
    mockPreference.space = 'clientCompany';

    renderRoot();

    expect(await screen.findByTestId('onglets-perso')).toBeTruthy();
    expect(screen.queryByTestId('ecran-accueil-societe')).toBeNull();
  });
});
