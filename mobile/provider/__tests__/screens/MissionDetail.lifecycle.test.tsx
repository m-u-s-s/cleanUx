/**
 * Actions du cycle de vie sur l'écran de détail de mission.
 *
 * Deux défauts corrigés ici, tous deux invisibles aux tests jusqu'à présent :
 *
 *  - l'écran conditionnait ses actions finales sur `in_progress`, statut qui n'existe dans AUCUN
 *    état du backend (MissionStatus écrit `started`). Une mission démarrée n'affichait donc
 *    aucune action : le prestataire ne pouvait ni ouvrir la mission terrain ni la clôturer.
 *
 *  - depuis `arrived`, le bouton « Démarrer mission » postait vers /start, qui appelle setEnRoute
 *    côté serveur. La transition arrived → en_route étant invalide, il recevait un 422. Le vrai
 *    démarrage passe par /begin et le code communiqué au client par SMS.
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
}));

const mockNavigate = jest.fn();
jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
}));

jest.mock('@/ui', () => {
  const { View, Text, TouchableOpacity, TextInput: RNTextInput } = require('react-native');
  const ReactLocal = require('react');
  return {
    Screen: ({ children }: any) => <View>{children}</View>,
    Badge: ({ label }: any) => <Text>{label}</Text>,
    Divider: () => <View />,
    DetailRow: ({ label, value }: any) => <Text>{`${label}: ${value}`}</Text>,
    Button: ({ label, onPress }: any) => (
      <TouchableOpacity onPress={onPress} accessibilityLabel={label}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    TextInput: ReactLocal.forwardRef(({ label, value, onChangeText }: any, ref: any) => (
      <RNTextInput ref={ref} accessibilityLabel={label} value={value} onChangeText={onChangeText} />
    )),
  };
});

jest.mock('@/theme', () => ({
  colors: {
    brand: { 500: '#6366f1', 600: '#4f46e5' },
    success: { 600: '#059669' },
    surface: { 200: '#e5e5e5', 400: '#a3a3a3', 500: '#737373', 600: '#525252', 700: '#404040', 900: '#171717' },
    danger: { 500: '#ef4444', 600: '#dc2626' },
    mode: { tool: { ink: '#0f172a', muted: '#64748b' } },
  },
  radius: { md: 14, lg: 22 },
  shadows: { xs: {}, md: {} },
  spacing: { xs: 4, sm: 8, md: 16, lg: 24, xl: 32 },
  typography: {
    fontSize: { xs: 12, sm: 14, base: 16, lg: 18, xl: 20 },
    fontWeight: { medium: '500', semibold: '600', bold: '700' },
  },
  // Le thème réel plutôt qu'un objet partiel écrit à la main : il n'a aucun effet de bord,
  // et un stub partiel périme à chaque jeton ajouté — c'est ce qui a cassé ces tests quand
  // les teintes sont apparues.
  useThemeColors: jest.requireActual('@/theme/useThemeColors').useThemeColors,
}));

import { apiClient } from '@/api';
import { MissionDetailScreen } from '@/screens/MissionDetailScreen';

const apiMock = new MockAdapter(apiClient);
const MISSION_ID = 42;

function renderWithStatus(status: string) {
  apiMock.onGet(`/provider/missions/${MISSION_ID}`).reply(200, {
    id: MISSION_ID,
    status,
    service_name: 'Nettoyage',
    client_name: 'Jean Martin',
    address: '5 Rue du Bois',
    city: 'Liege',
    scheduled_date: '2026-07-30',
    scheduled_time: '09:00',
  });

  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={client}>
      <MissionDetailScreen route={{ params: { missionId: MISSION_ID } } as never} navigation={{} as never} />
    </QueryClientProvider>,
  );
}

function lifecycleCalls(action: string) {
  return apiMock.history['post']!.filter(c => c.url === `/provider/missions/${MISSION_ID}/${action}`);
}

beforeEach(() => {
  apiMock.reset();
  mockNavigate.mockClear();
});

describe('MissionDetailScreen — cycle de vie', () => {
  /** Le défaut principal : une mission démarrée n'offrait plus aucune action. */
  it('propose de clôturer une mission au statut started', async () => {
    renderWithStatus('started');

    await waitFor(() => expect(screen.getByLabelText('Mission terminée')).toBeTruthy());
    expect(screen.getByLabelText('Mission terrain')).toBeTruthy();
  });

  it('demande le code de début quand le prestataire est arrivé', async () => {
    renderWithStatus('arrived');

    await waitFor(() =>
      expect(screen.getByLabelText('Code de début (donné au client par SMS)')).toBeTruthy(),
    );
  });

  /**
   * Le point du correctif : /begin, et non /start qui met en route et rendait 422 depuis arrived.
   */
  it('démarre via begin en transmettant le code', async () => {
    apiMock.onPost(`/provider/missions/${MISSION_ID}/begin`).reply(200, { ok: true, status: 'started' });

    renderWithStatus('arrived');
    await waitFor(() => screen.getByLabelText('Code de début (donné au client par SMS)'));

    fireEvent.changeText(screen.getByLabelText('Code de début (donné au client par SMS)'), '482915');
    fireEvent.press(screen.getByLabelText('Démarrer mission'));

    await waitFor(() => expect(lifecycleCalls('begin')).toHaveLength(1));
    expect(JSON.parse(lifecycleCalls('begin')[0]!.data)).toEqual({ start_code: '482915' });
    // L'ancien comportement, qui recevait un 422.
    expect(lifecycleCalls('start')).toHaveLength(0);
  });

  it("n'appelle pas le serveur avec un code incomplet", async () => {
    renderWithStatus('arrived');
    await waitFor(() => screen.getByLabelText('Code de début (donné au client par SMS)'));

    fireEvent.changeText(screen.getByLabelText('Code de début (donné au client par SMS)'), '482');
    fireEvent.press(screen.getByLabelText('Démarrer mission'));

    await waitFor(() => expect(lifecycleCalls('begin')).toHaveLength(0));
  });

  /** Les états antérieurs ne doivent pas régresser. */
  it('propose la mise en route depuis assigned', async () => {
    renderWithStatus('assigned');

    await waitFor(() => expect(screen.getByLabelText('En route')).toBeTruthy());
  });
});
