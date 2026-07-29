/**
 * Préférences de notification : l'écran doit refléter le serveur, pas l'inventer.
 *
 * Il affichait une fiction. Il définissait ses propres catégories — `chat` et `system`, qui
 * n'existent nulle part côté serveur — initialisait TOUT à « activé » sans jamais lire les
 * préférences réelles, et sauvegardait sur `/notifications/preferences`, une adresse sans route.
 * La requête partait en 404 et l'écran annonçait pourtant « Vos préférences ont été mises à jour ».
 *
 * Ces tests verrouillent les trois choses qu'aucun test ne couvrait : l'adresse appelée, la forme
 * de la charge, et le fait que l'état affiché vienne du serveur.
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MockAdapter from 'axios-mock-adapter';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true }),
  default: {
    addEventListener: jest.fn(() => () => undefined),
    fetch: jest.fn().mockResolvedValue({ isConnected: true }),
  },
}));

jest.mock('@/ui', () => {
  const { View, Text, TouchableOpacity } = require('react-native');
  return {
    Screen: ({ children }: any) => <View>{children}</View>,
    Divider: () => <View />,
    Button: ({ label, onPress, disabled }: any) => (
      <TouchableOpacity onPress={onPress} accessibilityLabel={label} disabled={disabled}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
  };
});

jest.mock('@/theme', () => ({
  colors: {
    brand: { 500: '#6366f1' },
    surface: { 500: '#737373', 700: '#404040', 900: '#171717' },
  },
  spacing: { xs: 4, sm: 8, md: 16, lg: 24 },
  typography: {
    fontSize: { xs: 12, sm: 14, base: 16, xl: 20 },
    fontWeight: { semibold: '600', bold: '700' },
  },
}));

import { Alert } from 'react-native';
import { apiClient } from '@/api';
import { NotificationPreferencesScreen } from '@/screens/NotificationPreferencesScreen';

/**
 * On espionne l'Alert reellement importe par l'ecran plutot que de substituer un module : un
 * faux pose sur un autre chemin de resolution resterait vert meme si l'ecran cessait d'alerter.
 */
const mockAlert = jest.spyOn(Alert, 'alert').mockImplementation(() => undefined);

const apiMock = new MockAdapter(apiClient);
const ROUTE = '/client/notifications/preferences';

/** Vocabulaire réel du serveur — celui que l'écran inventait. */
function servePreferences(overrides: Record<string, Record<string, boolean>> = {}) {
  apiMock.onGet(ROUTE).reply(200, {
    channels: ['email', 'sms', 'push'],
    categories: ['transactional', 'marketing', 'security'],
    forced_on: [{ channel: 'email', category: 'security' }],
    preferences: {
      email: { transactional: true, marketing: false, security: true, ...overrides.email },
      sms: { transactional: true, marketing: true, security: true, ...overrides.sms },
      push: { transactional: true, marketing: true, security: true, ...overrides.push },
    },
  });
}

function renderScreen() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={client}>
      <NotificationPreferencesScreen />
    </QueryClientProvider>,
  );
}

function bulkCalls() {
  return apiMock.history['put']!.filter(c => c.url === `${ROUTE}/bulk`);
}

beforeEach(() => {
  apiMock.reset();
  mockAlert.mockClear();
  servePreferences();
});

describe('Préférences de notification', () => {
  /** L'état affiché doit être celui du serveur, pas « tout activé » par défaut. */
  it("affiche l'état réel des préférences", async () => {
    renderScreen();

    await waitFor(() => expect(screen.getByTestId('preference-switch-email-marketing')).toBeTruthy());
    expect(screen.getByTestId('preference-switch-email-marketing').props.value).toBe(false);
    expect(screen.getByTestId('preference-switch-sms-marketing').props.value).toBe(true);
  });

  /** Les catégories viennent du serveur : celles que l'écran inventait n'existent pas. */
  it('ne rend que les catégories déclarées par le serveur', async () => {
    renderScreen();

    await waitFor(() => expect(screen.getByTestId('preference-category-transactional')).toBeTruthy());
    expect(screen.getByTestId('preference-category-marketing')).toBeTruthy();
    expect(screen.queryByTestId('preference-category-chat')).toBeNull();
    expect(screen.queryByTestId('preference-category-system')).toBeNull();
  });

  /** Une combinaison légalement obligatoire ne doit pas être présentée comme modifiable. */
  it('verrouille les combinaisons obligatoires', async () => {
    renderScreen();

    await waitFor(() => expect(screen.getByTestId('preference-switch-email-security')).toBeTruthy());
    const forced = screen.getByTestId('preference-switch-email-security');
    expect(forced.props.disabled).toBe(true);
    expect(forced.props.value).toBe(true);
  });

  /**
   * Le défaut central : l'adresse appelée n'existait pas, et la charge n'avait pas la forme
   * attendue. Le serveur veut une LISTE plate, pas une matrice.
   */
  it("enregistre sur la bonne adresse, dans la forme attendue", async () => {
    apiMock.onPut(`${ROUTE}/bulk`).reply(200, { ok: true });

    renderScreen();
    await waitFor(() => screen.getByTestId('preference-switch-push-marketing'));

    fireEvent(screen.getByTestId('preference-switch-push-marketing'), 'valueChange', false);
    fireEvent.press(screen.getByLabelText('Enregistrer'));

    await waitFor(() => expect(bulkCalls()).toHaveLength(1));
    expect(JSON.parse(bulkCalls()[0]!.data)).toEqual({
      preferences: [{ channel: 'push', category: 'marketing', is_allowed: false }],
    });
  });

  /** Seules les modifications partent : renvoyer toute la matrice polluerait la piste d'audit. */
  it("n'envoie que ce qui a changé", async () => {
    apiMock.onPut(`${ROUTE}/bulk`).reply(200, { ok: true });

    renderScreen();
    await waitFor(() => screen.getByTestId('preference-switch-sms-transactional'));

    fireEvent(screen.getByTestId('preference-switch-sms-transactional'), 'valueChange', false);
    fireEvent.press(screen.getByLabelText('Enregistrer'));

    await waitFor(() => expect(bulkCalls()).toHaveLength(1));
    expect(JSON.parse(bulkCalls()[0]!.data).preferences).toHaveLength(1);
  });

  it('ne propose pas d’enregistrer sans modification', async () => {
    renderScreen();

    await waitFor(() => expect(screen.getByLabelText('Aucune modification')).toBeTruthy());
    fireEvent.press(screen.getByLabelText('Aucune modification'));

    expect(bulkCalls()).toHaveLength(0);
  });

  /**
   * Une panne de chargement ne doit surtout pas afficher une matrice inventée que l'utilisateur
   * croirait être la sienne — c'est exactement ce que faisait l'écran précédent.
   */
  it('annonce un échec de chargement plutôt que des valeurs par défaut', async () => {
    apiMock.reset();
    apiMock.onGet(ROUTE).reply(500);

    renderScreen();

    await waitFor(() => expect(screen.getByTestId('preferences-error')).toBeTruthy());
    expect(screen.queryByTestId('preference-switch-email-marketing')).toBeNull();
  });

  it("signale l'échec d'enregistrement au lieu d'annoncer un succès", async () => {
    apiMock.onPut(`${ROUTE}/bulk`).reply(500);

    renderScreen();
    await waitFor(() => screen.getByTestId('preference-switch-push-marketing'));

    fireEvent(screen.getByTestId('preference-switch-push-marketing'), 'valueChange', false);
    fireEvent.press(screen.getByLabelText('Enregistrer'));

    await waitFor(() => expect(mockAlert).toHaveBeenCalledWith('Erreur', expect.any(String)));
  });
});
