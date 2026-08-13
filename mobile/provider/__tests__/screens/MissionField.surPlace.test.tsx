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
import { Switch as RNSwitch } from 'react-native';
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

/**
 * La fiche d'accès que le serveur rendra pour ce statut.
 *
 * Verrouillée AVANT l'arrivée : c'est la règle que F5 protège — un code d'alarme et l'emplacement
 * d'une boîte à clés sont les clés du domicile de quelqu'un.
 */
function ficheDAccesPour(status: string) {
  return status === 'arrived' || status === 'started'
    ? {
        available: true,
        address: '5 Rue du Bois',
        floor: '3e étage',
        access_instructions: 'Digicode 45A12.',
        alarm_code_required: true,
        access_window: null,
        notes: null,
      }
    : {
        available: false,
        address: null,
        floor: null,
        access_instructions: null,
        alarm_code_required: false,
        access_window: null,
        notes: null,
        message: 'Les informations d’accès s’affichent une fois votre arrivée confirmée sur place.',
      };
}

/**
 * La checklist qui CONDITIONNE la clôture — distincte de l'inspection qualité.
 *
 * `required_pending` reproduit exactement la condition du serveur : c'est ce qui permet à l'écran
 * de dire ce qui bloque au lieu d'opposer un refus muet au moment de terminer.
 */
function checklistAvec(obligatoiresOuvertes: number) {
  return {
    checklists: [
      {
        id: 8,
        name: 'Checklist standard',
        status: 'draft',
        completion_rate: 0,
        items: [
          {
            id: 43,
            label: 'Vérifier accès client',
            guidance: 'Sonner, puis appeler si personne',
            is_required: true,
            requires_photo: false,
            status: obligatoiresOuvertes > 0 ? 'pending' : 'done',
            done: obligatoiresOuvertes === 0,
          },
        ],
      },
    ],
    required_pending: obligatoiresOuvertes,
    blocks_completion: obligatoiresOuvertes > 0,
  };
}

function rendre(status: string, obligatoiresOuvertes = 1) {
  apiMock
    .onGet(`/provider/missions/${MISSION_ID}/checklist`)
    .reply(200, { data: checklistAvec(obligatoiresOuvertes) });
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
  apiMock.onGet(`/provider/missions/${MISSION_ID}/extras`).reply(200, { data: [] });
  apiMock.onPost(`/provider/missions/${MISSION_ID}/extras`).reply(201, { data: {} });
  apiMock
    .onGet(`/provider/missions/${MISSION_ID}/access-sheet`)
    .reply(200, { data: ficheDAccesPour(status) });

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

  /**
   * LES TÂCHES QUI BLOQUENT LA CLÔTURE N'ÉTAIENT VISIBLES NULLE PART.
   *
   * L'écran n'affichait que la checklist du module Inspection — une autre table. Le prestataire
   * pouvait tout y cocher sans jamais débloquer la clôture, et le refus du serveur ne lui
   * indiquait aucun remède atteignable depuis son téléphone.
   */
  describe('checklist de clôture', () => {
    it('affiche les tâches obligatoires et dit ce qui bloque', async () => {
      rendre('started', 1);

      await waitFor(() => expect(screen.getByText('Vérifier accès client *')).toBeTruthy());
      expect(screen.getByTestId('checklist-blocage')).toBeTruthy();
      // La consigne fait la différence entre une case et une instruction.
      expect(screen.getByText('Sonner, puis appeler si personne')).toBeTruthy();
    });

    it('coche une tâche et transmet le statut au serveur', async () => {
      rendre('started', 1);
      apiMock
        .onPost(`/provider/missions/${MISSION_ID}/checklist/43`)
        .reply(200, { data: checklistAvec(0) });

      await waitFor(() => screen.getByText('Vérifier accès client *'));

      fireEvent(screen.UNSAFE_getAllByType(RNSwitch)[0]!, 'valueChange', true);

      await waitFor(() => expect(envois('checklist/43')).toHaveLength(1));
      expect(JSON.parse(envois('checklist/43')[0]!.data)).toEqual({ status: 'done' });
    });

    it('annonce que la clôture est possible quand plus rien ne bloque', async () => {
      rendre('started', 0);

      await waitFor(() =>
        expect(screen.getByText(/la clôture est possible/i)).toBeTruthy(),
      );
      expect(screen.queryByTestId('checklist-blocage')).toBeNull();
    });
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

  /*
   * F5 — LA FICHE D'ACCÈS. Ce dépôt a un historique d'écrans complets que personne ne pouvait
   * atteindre : on vérifie donc ce qui S'AFFICHE, pas ce qui est monté.
   */
  it('garde les codes d’accès fermés tant que l’arrivée n’est pas confirmée', async () => {
    rendre('assigned');

    expect(await screen.findByTestId('fiche-acces-verrouillee')).toBeTruthy();
    // Le digicode ne doit apparaître nulle part sur l'écran avant l'arrivée.
    expect(screen.queryByText(/45A12/)).toBeNull();
  });

  it('ouvre la fiche une fois l’arrivée confirmée', async () => {
    rendre('started');

    expect(await screen.findByTestId('fiche-acces-consignes')).toBeTruthy();
    // L'alarme demande une manœuvre chronométrée : elle doit se lire avant d'ouvrir la porte.
    expect(screen.getByText('Alarme à désarmer')).toBeTruthy();
  });

  /* F3 — proposer un supplément constaté sur place. */
  it('propose un supplément avec le prix converti en centimes', async () => {
    rendre('started');

    fireEvent.changeText(await screen.findByTestId('extra-label'), 'Nettoyage des vitres');
    fireEvent.changeText(screen.getByTestId('extra-prix'), '25,50');
    fireEvent.press(screen.getByText('Proposer au client'));

    await waitFor(() => expect(envois('extras')).toHaveLength(1));

    // Le montant voyage en CENTIMES : un flottant produirait des écarts d'un centime que personne
    // ne sait expliquer une fois en base.
    expect(JSON.parse(envois('extras')[0]!.data).price_cents).toBe(2550);
  });
});
