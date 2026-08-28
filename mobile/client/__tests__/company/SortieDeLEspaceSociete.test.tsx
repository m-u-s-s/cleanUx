/**
 * ON DOIT POUVOIR SORTIR DE L'ESPACE SOCIÉTÉ — S'EN ALLER, ET SE DÉCONNECTER.
 *
 * POURQUOI CE FICHIER EXISTE. `CompanyReachability.test.ts` garde déjà l'issue... en asserant que
 * `RootNavigator.tsx` contient `name="Profile"`. C'est une assertion sur la DÉCLARATION d'une
 * route, pas sur le fait qu'on y arrive : `navigate('Profile')` n'existe nulle part dans cette
 * application, aucun lien profond n'y mène, et la barre d'onglets société n'en parle pas. La route
 * était montée et injoignable — un utilisateur entré dans l'espace société ne pouvait plus ni
 * revenir chez lui, ni se déconnecter.
 *
 * C'est la quatrième occurrence de la même famille dans ce dépôt : un écran complet, testé, monté,
 * que personne n'atteint. Ce test-ci PRESSE la barre au lieu de lire le fichier — c'est la seule
 * façon de prouver qu'un chemin existe.
 *
 * L'espace d'administration de l'application prestataire fait autorité ici : sa console porte un
 * onglet « Profil » qui tient `logout()` et le retour d'espace. Les deux espaces société ne
 * l'avaient pas.
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';
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

jest.mock('@/navigation/TabNavigator', () => {
  const { Text, View } = require('react-native');

  return { TabNavigator: () => <View testID="onglets-perso"><Text>Espace perso</Text></View> };
});

/*
 * Les quatre écrans d'onglets appellent `/client/company/*` ; ce n'est pas le sujet ici. L'écran de
 * profil société, LUI, n'est pas bouché — c'est précisément ce qu'on mesure.
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

// La presentation de premiere ouverture est declaree DEJA VUE : ces tests portent sur la
// navigation d'un utilisateur qui revient, pas sur le carrousel d'accueil.
jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue('true'),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

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

const responsableSites = { id: 2, name: 'Responsable', email: 'responsable@exemple.be', is_entreprise: true };

async function ouvrirLEspaceSocietePuisLeProfil() {
  mockAuth.user = responsableSites;
  mockAuth.isAuthenticated = true;
  mockPreference.space = 'clientCompany';

  renderRoot();
  await screen.findByTestId('ecran-accueil-societe');

  fireEvent.press(screen.getByText('Profil'));
}

describe('Espace société cliente — la sortie', () => {
  beforeEach(() => {
    mockAuth.user = null;
    mockAuth.isAuthenticated = false;
    mockAuth.isLoading = false;
    mockAuth.logout.mockClear();
    mockPreference.space = undefined;
    mockPreference.isLoading = false;
    mockPreference.clear.mockClear();
  });

  it('offre un onglet Profil dans la barre de l’espace société', async () => {
    mockAuth.user = responsableSites;
    mockAuth.isAuthenticated = true;
    mockPreference.space = 'clientCompany';

    renderRoot();
    await screen.findByTestId('ecran-accueil-societe');

    // La barre est le SEUL point d'entrée de cet espace : les écrans profonds se poussent depuis
    // l'accueil, et rien d'autre n'est visible en permanence.
    expect(screen.getByText('Profil')).toBeTruthy();
  });

  it('laisse se déconnecter depuis l’espace société', async () => {
    await ouvrirLEspaceSocietePuisLeProfil();

    fireEvent.press(screen.getByText('Se déconnecter'));

    expect(mockAuth.logout).toHaveBeenCalled();
  });

  it('laisse revenir à son espace personnel', async () => {
    /*
     * L'organisation est un rattachement du compte, pas un compte distinct : la responsable des
     * locaux d'une société commande aussi son propre ménage. L'enfermer côté société lui retire ses
     * réservations — le défaut que `clear()` a déjà corrigé deux fois ici.
     */
    await ouvrirLEspaceSocietePuisLeProfil();

    fireEvent.press(screen.getByText('Changer d’espace'));

    expect(mockPreference.clear).toHaveBeenCalled();
  });
});
