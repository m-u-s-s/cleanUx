/**
 * L'ÉCRAN DU TERRAIN — état des lieux et imprévus, vérifiés en appuyant.
 *
 * L'écran existait, il était atteignable depuis le détail de mission, et il ne servait à rien :
 * ses deux conditions d'affichage testaient `in_progress`, un statut qui n'existe dans AUCUN état
 * du serveur. Le partage GPS ne démarrait jamais, le bouton de clôture ne s'affichait jamais. Le
 * même défaut avait été corrigé sur l'écran de détail sans qu'on pense à celui-ci — d'où le
 * premier test ci-dessous.
 *
 * Les deux suivants prouvent que la photo PART, avec sa position, et que l'imprévu ne part pas
 * sans avoir été décrit.
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

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn() }),
}));

const mockPickImage = jest.fn();
jest.mock('@/screens/onboarding/documentPicker', () => ({
  pickImage: (...args: unknown[]) => mockPickImage(...args),
}));

jest.mock('@/tracking', () => ({
  useGpsWatcher: jest.fn(),
  usePushPosition: () => ({ mutate: jest.fn() }),
  readScanPosition: jest.fn().mockResolvedValue({
    lat: 50.8467,
    lng: 4.3525,
    accuracy_m: 9,
    mocked: false,
  }),
}));

jest.mock('@/inspection', () => ({
  useInspection: () => ({ data: null }),
  useToggleChecklistItem: () => ({ mutate: jest.fn() }),
}));

jest.mock('@/ui', () => {
  const { View, Text, TouchableOpacity, TextInput: RNTextInput } = require('react-native');
  const ReactLocal = require('react');

  return {
    Screen: ({ children }: any) => <View>{children}</View>,
    Badge: ({ label }: any) => <Text>{label}</Text>,
    Divider: () => <View />,
    ProgressBar: () => <View />,
    Button: ({ label, onPress }: any) => (
      <TouchableOpacity onPress={onPress} accessibilityLabel={label}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    TextInput: ReactLocal.forwardRef(({ label, value, onChangeText, testID }: any, ref: any) => (
      <RNTextInput
        ref={ref}
        testID={testID}
        accessibilityLabel={label}
        value={value}
        onChangeText={onChangeText}
      />
    )),
  };
});

import { apiClient } from '@/api';
import { MissionFieldScreen } from '@/screens/MissionFieldScreen';

const apiMock = new MockAdapter(apiClient);
const MISSION_ID = 42;

function rendre(status: string) {
  apiMock.onGet(`/provider/missions/${MISSION_ID}`).reply(200, {
    id: MISSION_ID,
    status,
    service_name: 'Nettoyage',
    client_name: 'Jean Martin',
    address: '5 Rue du Bois',
    city: 'Liège',
    scheduled_date: '2026-08-11',
    scheduled_time: '09:00',
  });
  apiMock.onGet(`/provider/missions/${MISSION_ID}/media`).reply(200, { data: [] });
  apiMock.onGet(`/provider/missions/${MISSION_ID}/incidents`).reply(200, { data: [] });

  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={client}>
      <MissionFieldScreen
        route={{ params: { missionId: MISSION_ID } } as never}
        navigation={{} as never}
      />
    </QueryClientProvider>,
  );
}

const envois = (suffixe: string) =>
  apiMock.history['post']!.filter((c) => c.url === `/provider/missions/${MISSION_ID}/${suffixe}`);

/**
 * Les champs d'un `FormData`, quelle qu'en soit l'implémentation.
 *
 * React Native expose ses parties par `_parts` ; l'environnement de test, lui, fournit le
 * `FormData` standard, qui n'a que `entries()`. Lire l'un des deux au hasard rendait un objet vide
 * — et un objet vide compare `undefined` à `undefined` sans rien prouver.
 */
function champs(corps: FormData): Record<string, unknown> {
  const parts = (corps as unknown as { _parts?: [string, unknown][] })._parts;

  if (Array.isArray(parts)) {
    return Object.fromEntries(parts);
  }

  return Object.fromEntries(Array.from(corps.entries()));
}

beforeEach(() => {
  apiMock.reset();
  mockPickImage.mockReset();
  mockPickImage.mockResolvedValue({
    uri: 'file:///tmp/avant.jpg',
    name: 'avant.jpg',
    mimeType: 'image/jpeg',
  });
});

describe('MissionFieldScreen — le kit sur place', () => {
  /** Le défaut d'origine : `started`, jamais `in_progress`. */
  it('propose de terminer la mission au statut started', async () => {
    rendre('started');

    await waitFor(() => expect(screen.getByLabelText('Terminer la mission')).toBeTruthy());
  });

  it('envoie la photo d’état des lieux avec sa position', async () => {
    apiMock
      .onPost(`/provider/missions/${MISSION_ID}/media`)
      .reply(201, { data: { id: 7, type: 'before_photo', label: 'Photo avant' } });

    rendre('started');
    await waitFor(() => screen.getByLabelText('Photo avant'));

    fireEvent.press(screen.getByLabelText('Photo avant'));

    await waitFor(() => expect(envois('media')).toHaveLength(1));
    expect(mockPickImage).toHaveBeenCalledWith('camera');

    const parties = champs(envois('media')[0]!.data as FormData);

    expect(parties['type']).toBe('before_photo');
    expect(parties['lat']).toBe('50.8467');
    expect(parties['accuracy_m']).toBe('9');
  });

  it('refuse de signaler un imprévu sans catégorie ni description', async () => {
    rendre('started');
    await waitFor(() => screen.getByLabelText('Sans photo'));

    fireEvent.press(screen.getByLabelText('Sans photo'));

    await waitFor(() => expect(envois('incidents')).toHaveLength(0));
  });

  it('signale un imprévu décrit, et le client en est prévenu par le serveur', async () => {
    apiMock
      .onPost(`/provider/missions/${MISSION_ID}/incidents`)
      .reply(201, { data: { id: 3, label: 'Accès impossible', notified_at: '2026-08-11T09:05:00Z' } });

    rendre('started');
    await waitFor(() => screen.getByTestId('incident-type-access_impossible'));

    fireEvent.press(screen.getByTestId('incident-type-access_impossible'));
    fireEvent.changeText(
      screen.getByTestId('incident-description'),
      'Portail fermé, personne ne répond.',
    );
    fireEvent.press(screen.getByLabelText('Sans photo'));

    await waitFor(() => expect(envois('incidents')).toHaveLength(1));

    const parties = champs(envois('incidents')[0]!.data as FormData);

    expect(parties['type']).toBe('access_impossible');
    expect(parties['description']).toBe('Portail fermé, personne ne répond.');
    // Sans photo : le sélecteur ne doit même pas s'ouvrir.
    expect(mockPickImage).not.toHaveBeenCalled();
  });
});
