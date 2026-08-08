/**
 * LOT 4 CÔTÉ MOBILE — RÉPARTIR EN SACHANT QUI EST LIBRE.
 *
 * Ce qui existait tenait dans un `Alert.alert` plafonné à dix boutons et SANS indicateur de
 * disponibilité : au-delà de dix personnes, les suivantes n'étaient pas proposables, et le
 * répartiteur choisissait à l'aveugle depuis son téléphone — là où l'écran web le renseignait déjà.
 *
 * DEUX GESTES D'AUTO-ASSIGNATION, ET IL FAUT LES DISTINGUER. Le BOUTON traite l'arriéré une fois,
 * maintenant. Le MODE CONTINU est un réglage de société : il agit sur des missions créées quand
 * personne n'est devant l'application. Les confondre en un seul interrupteur laisserait croire
 * qu'appuyer suffit, ou au contraire qu'activer traite le passé.
 */
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { Alert } from 'react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';

notifyManager.setScheduler((callback) => callback());

const mockAuth = { user: null as unknown, logout: jest.fn() };
const mockNavigate = jest.fn();

const mockGet = jest.fn();
const mockPost = jest.fn();
const mockPut = jest.fn();

jest.mock('@/auth', () => ({
  useAuth: () => mockAuth,
  can: jest.requireActual('../../../shared/src/auth/permissions').can,
}));

jest.mock('@/api', () => ({
  apiClient: {
    get: (...args: unknown[]) => mockGet(...args),
    post: (...args: unknown[]) => mockPost(...args),
    put: (...args: unknown[]) => mockPut(...args),
    patch: jest.fn(),
    delete: jest.fn(),
  },
}));

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
  useRoute: () => ({ params: { missionId: 12 } }),
}));

/*
 * `useLiveMissionUpdates` ouvre un canal Reverb. On le remplace par un espion : ce qu'on vérifie
 * ici est qu'il est APPELÉ avec la bonne mission — il était défini depuis longtemps et n'avait
 * jamais eu d'appelant.
 */
const mockLive = jest.fn();

jest.mock('@/missions', () => ({
  useLiveMissionUpdates: (...args: unknown[]) => mockLive(...args),
}));

import { CompanyDispatchScreen } from '@/screens/company/CompanyDispatchScreen';
import { CompanyMissionDetailScreen } from '@/screens/company/CompanyMissionDetailScreen';

const MISSION = {
  id: 12,
  status: 'planned',
  planned_start_at: '2026-08-10T09:00:00+00:00',
  site: 'Résidence Les Tilleuls',
  city: 'Bruxelles',
  lead: null,
  lead_user_id: null,
};

function monter(ecran: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(<QueryClientProvider client={client}>{ecran}</QueryClientProvider>);
}

beforeEach(() => {
  mockNavigate.mockReset();
  mockLive.mockReset();
  mockPost.mockReset().mockResolvedValue({ data: { data: {} } });
  mockPut.mockReset().mockResolvedValue({ data: { data: { auto_assign_enabled: true } } });

  mockGet.mockReset().mockImplementation((url: string) => {
    if (url === '/provider/company/missions') {
      return Promise.resolve({ data: { data: [MISSION] } });
    }

    if (url === '/provider/company/auto-assign/settings') {
      return Promise.resolve({ data: { data: { auto_assign_enabled: false } } });
    }

    if (url.startsWith('/provider/company/availability')) {
      return Promise.resolve({
        data: {
          data: {
            mission_id: 12,
            workers: [
              { user_id: 7, name: 'Nadia', is_free: true },
              { user_id: 9, name: 'Karim', is_free: false },
            ],
          },
        },
      });
    }

    return Promise.resolve({ data: { data: [] } });
  });
});

describe('CompanyDispatchScreen — auto-assignation', () => {
  it('lance la répartition de l’arriéré', async () => {
    mockAuth.user = { organization_permissions: ['missions.dispatch'] };

    const espionAlerte = jest.spyOn(Alert, 'alert').mockImplementation(() => {});

    monter(<CompanyDispatchScreen />);

    fireEvent.press(await screen.findByText('Assigner les missions sans personne'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/missions/auto-assign'),
    );

    espionAlerte.mockRestore();
  });

  it('bascule le mode continu, qui est un réglage distinct du bouton', async () => {
    mockAuth.user = { organization_permissions: ['missions.dispatch'] };

    monter(<CompanyDispatchScreen />);

    fireEvent.press(
      await screen.findByText(/Assigner automatiquement chaque nouvelle mission/),
    );

    await waitFor(() =>
      expect(mockPut).toHaveBeenCalledWith('/provider/company/auto-assign/settings', {
        auto_assign_enabled: true,
      }),
    );
  });

  it('ne montre aucune de ces commandes à qui ne répartit pas', async () => {
    // Un chef d'équipe consulte le tableau sans piloter l'auto-assignation de la société.
    mockAuth.user = { organization_permissions: ['missions.view_all'] };

    monter(<CompanyDispatchScreen />);

    await screen.findByText(/Résidence Les Tilleuls/);

    expect(screen.queryByText('Assigner les missions sans personne')).toBeNull();
    expect(screen.queryByText(/Assigner automatiquement/)).toBeNull();
  });

  it('ouvre le détail de la mission au lieu d’une alerte à dix noms', async () => {
    mockAuth.user = { organization_permissions: ['missions.dispatch'] };

    monter(<CompanyDispatchScreen />);

    fireEvent.press(await screen.findByText('Assigner'));

    expect(mockNavigate).toHaveBeenCalledWith('CompanyMissionDetail', { missionId: 12 });
  });
});

describe('CompanyMissionDetailScreen — choisir en sachant qui est libre', () => {
  it('affiche la disponibilité de chacun et assigne', async () => {
    mockAuth.user = { organization_permissions: ['missions.dispatch', 'missions.assign'] };

    monter(<CompanyMissionDetailScreen />);

    // La disponibilité vient du SERVEUR : elle repose sur le chevauchement des missions de toute la
    // société, une donnée que le téléphone n'a pas et ne doit pas avoir.
    expect(await screen.findByText('libre')).toBeTruthy();
    expect(screen.getByText('déjà pris')).toBeTruthy();

    fireEvent.press(screen.getAllByText('Assigner')[0]!);

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/missions/12/assign', {
        user_id: 7,
      }),
    );
  });

  it('ajoute un renfort', async () => {
    mockAuth.user = { organization_permissions: ['missions.assign'] };

    monter(<CompanyMissionDetailScreen />);

    fireEvent.press((await screen.findAllByText('+ renfort'))[0]!);

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/missions/12/helpers', {
        user_id: 7,
        remove: false,
      }),
    );
  });

  it('branche enfin le temps réel sur la mission', async () => {
    /*
     * `useLiveMissionUpdates` était défini depuis longtemps et JAMAIS APPELÉ : deux répartiteurs sur
     * la même mission s'écrasaient sans le voir.
     */
    mockAuth.user = { organization_permissions: ['missions.dispatch'] };

    monter(<CompanyMissionDetailScreen />);

    await screen.findByText(/Résidence Les Tilleuls/);

    expect(mockLive).toHaveBeenCalledWith(12, expect.any(Function));
  });

  it('laisse consulter sans proposer d’assigner à qui n’en a pas le droit', async () => {
    mockAuth.user = { organization_permissions: ['missions.view_all'] };

    monter(<CompanyMissionDetailScreen />);

    await screen.findByText(/Résidence Les Tilleuls/);

    expect(screen.queryByText('libre')).toBeNull();
    expect(screen.queryByText('+ renfort')).toBeNull();
  });
});
