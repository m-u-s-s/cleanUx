/**
 * L'annuaire des modules d'administration.
 *
 * Il affiche TOUT le registre, y compris ce qui n'est pas encore servi nativement, marqué et non
 * navigable. Masquer les modules non couverts donnerait une application qui a l'air complète et
 * un avancement que personne ne peut mesurer.
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';

notifyManager.setScheduler((callback) => callback());

jest.mock('@/storage/secureStore');

const mockNavigate = jest.fn();
jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
}));

// La table des écrans sur-mesure est mockée VIDE par défaut : les tests du moteur décrivent le
// cas nominal, et un seul test la remplit pour éprouver l'aiguillage.
jest.mock('@/admin/nativeScreens', () => ({ NATIVE_ADMIN_SCREENS: {} }));

import { apiClient } from '@/api';
import { AdminDirectoryScreen } from '@/admin/AdminDirectoryScreen';
import { NATIVE_ADMIN_SCREENS } from '@/admin/nativeScreens';

const apiMock = new MockAdapter(apiClient);

const CATALOG = {
  ok: true,
  groups: [
    {
      key: 'personnes',
      title: 'Personnes et comptes',
      modules: [
        { key: 'users', title: 'Utilisateurs', icon: 'people-outline', coverage: 'screen', route: 'admin/utilisateurs' },
        { key: 'kyc', title: 'Vérifications KYC', icon: 'finger-print-outline', coverage: 'pending', route: 'admin/kyc' },
      ],
    },
    {
      key: 'argent',
      title: 'Argent et conformité',
      modules: [
        { key: 'accounting', title: 'Comptabilité', icon: 'calculator-outline', coverage: 'descriptor', route: 'admin/accounting-v2' },
      ],
    },
  ],
  counts: { total: 3, covered: 2, pending: 1 },
};

function renderScreen() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <AdminDirectoryScreen />
    </QueryClientProvider>,
  );
}

describe('AdminDirectoryScreen', () => {
  beforeEach(() => {
    apiMock.reset();
    mockNavigate.mockClear();
  });

  it('rend les groupes et leurs modules', async () => {
    apiMock.onGet('/admin/catalog').reply(200, CATALOG);

    renderScreen();

    expect(await screen.findByText('Personnes et comptes')).toBeTruthy();
    expect(screen.getByText('Utilisateurs')).toBeTruthy();
    expect(screen.getByText('Vérifications KYC')).toBeTruthy();
    expect(screen.getByText('Argent et conformité')).toBeTruthy();
    expect(screen.getByText('Comptabilité')).toBeTruthy();
  });

  it('affiche l’avancement réel de la couverture', async () => {
    apiMock.onGet('/admin/catalog').reply(200, CATALOG);

    renderScreen();

    expect(await screen.findByText('2 / 3 modules disponibles')).toBeTruthy();
  });

  it('marque « à venir » ce qui n’est pas encore servi', async () => {
    apiMock.onGet('/admin/catalog').reply(200, CATALOG);

    renderScreen();

    expect(await screen.findByText('À venir')).toBeTruthy();
  });

  it('ouvre un module couvert dans le moteur de console', async () => {
    apiMock.onGet('/admin/catalog').reply(200, CATALOG);

    renderScreen();

    fireEvent.press(await screen.findByText('Utilisateurs'));

    // La clé de module EST la clé de ressource : le registre serveur refuse qu'elles divergent.
    expect(mockNavigate).toHaveBeenCalledWith('AdminResourceList', {
      resource: 'users',
      title: 'Utilisateurs',
    });
  });

  it('ouvre un module sur-mesure sur SON écran, pas sur le moteur', async () => {
    // Sans cet aiguillage, la table `NATIVE_ADMIN_SCREENS` pourrait exister sans jamais être
    // consultée : les modules sur-mesure s'ouvriraient sur la liste générique, et le garde-fou
    // de joignabilité resterait vert puisqu'il ne vérifie que la DÉCLARATION, pas l'usage.
    NATIVE_ADMIN_SCREENS.users = { screen: 'AdminUsers' };
    apiMock.onGet('/admin/catalog').reply(200, CATALOG);

    renderScreen();

    fireEvent.press(await screen.findByText('Utilisateurs'));

    expect(mockNavigate).toHaveBeenCalledWith('AdminUsers', { title: 'Utilisateurs' });

    delete NATIVE_ADMIN_SCREENS.users;
  });

  it('ouvre une synthèse sur l’écran de rapport, pas sur le moteur de liste', async () => {
    // Ouvrir une synthèse sur le moteur de liste montrerait un écran vide en prétendant couvrir
    // le domaine — le mensonge que le registre de couverture sert à empêcher.
    apiMock.onGet('/admin/catalog').reply(200, {
      ...CATALOG,
      groups: [{
        key: 'pilotage',
        title: 'Pilotage',
        modules: [{ key: 'home', title: 'Accueil admin', icon: 'home-outline', coverage: 'report', route: 'admin/home' }],
      }],
    });

    renderScreen();

    fireEvent.press(await screen.findByText('Accueil admin'));

    expect(mockNavigate).toHaveBeenCalledWith('AdminReport', {
      report: 'home',
      title: 'Accueil admin',
    });
  });

  it('ne navigue pas vers un module non couvert', async () => {
    apiMock.onGet('/admin/catalog').reply(200, CATALOG);

    renderScreen();

    fireEvent.press(await screen.findByText('Vérifications KYC'));

    // Ouvrir un écran vide serait pire que l'annoncer : la marque « à venir » doit tenir.
    expect(mockNavigate).not.toHaveBeenCalled();
  });

  it('filtre par titre', async () => {
    apiMock.onGet('/admin/catalog').reply(200, CATALOG);

    renderScreen();

    await screen.findByText('Utilisateurs');
    fireEvent.changeText(screen.getByLabelText('Rechercher un module'), 'compta');

    await waitFor(() => expect(screen.queryByText('Utilisateurs')).toBeNull());
    expect(screen.getByText('Comptabilité')).toBeTruthy();
  });

  it('laisse réessayer quand le serveur refuse', async () => {
    apiMock.onGet('/admin/catalog').replyOnce(500);

    renderScreen();

    const retry = await screen.findByLabelText('Réessayer');

    apiMock.onGet('/admin/catalog').reply(200, CATALOG);
    fireEvent.press(retry);

    await waitFor(() => expect(screen.getByText('Utilisateurs')).toBeTruthy());
  });
});
