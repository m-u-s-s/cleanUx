/**
 * LOT 3 CÔTÉ MOBILE — COMPOSER UNE ÉQUIPE, PUIS LUI CONFIER UNE MISSION.
 *
 * Deux gestes qui n'existaient sur AUCUNE surface :
 *
 *   - peupler une équipe. `field_team_members` n'était manipulable que depuis l'administration de
 *     la plateforme : une société qui créait son « Équipe Nord » ici ne pouvait pas y mettre
 *     quelqu'un, et une équipe vide ne peut recevoir aucune mission. L'écran affichait des coquilles.
 *   - confier la mission à l'équipe entière. Il fallait assigner un responsable puis N renforts, un
 *     par un, sans que rien n'enregistre QUELLE équipe intervenait.
 *
 * ON PRESSE. Un écran monté n'est pas un écran atteignable, et un bouton rendu n'est pas un bouton
 * qui appelle quelque chose : on ouvre le panneau par un appui réel, on presse le geste, on vérifie
 * l'appel HTTP.
 */
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { Alert } from 'react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';

notifyManager.setScheduler((callback) => callback());

const mockAuth = { user: null as unknown, logout: jest.fn() };

const mockGet = jest.fn();
const mockPost = jest.fn();
const mockPatch = jest.fn();
const mockDelete = jest.fn();

jest.mock('@/auth', () => ({
  useAuth: () => mockAuth,
  can: jest.requireActual('../../../shared/src/auth/permissions').can,
}));

jest.mock('@/api', () => ({
  apiClient: {
    get: (...args: unknown[]) => mockGet(...args),
    post: (...args: unknown[]) => mockPost(...args),
    patch: (...args: unknown[]) => mockPatch(...args),
    delete: (...args: unknown[]) => mockDelete(...args),
  },
}));

import { CompanyFieldTeamsScreen } from '@/screens/company/CompanyFieldTeamsScreen';
import { CompanyDispatchScreen } from '@/screens/company/CompanyDispatchScreen';

const EQUIPE = { id: 5, name: 'Équipe Nord', status: 'active', zone: null, lead: null, max_concurrent_missions: 3 };
const MISSION = {
  id: 12,
  status: 'planned',
  planned_start_at: null,
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
  mockPost.mockReset().mockResolvedValue({ data: { data: {} } });
  mockPatch.mockReset().mockResolvedValue({ data: { data: {} } });
  mockDelete.mockReset().mockResolvedValue({ data: { data: {} } });
});

describe('CompanyFieldTeamsScreen — composer une équipe', () => {
  beforeEach(() => {
    mockGet.mockReset().mockImplementation((url: string) => {
      if (url === '/provider/company/field-teams') {
        return Promise.resolve({ data: { data: [EQUIPE] } });
      }

      if (url === '/provider/company/field-teams/5/members') {
        return Promise.resolve({
          data: { data: { team: EQUIPE, members: [{ id: 1, user_id: 7, name: 'Nadia', email: null, is_team_lead: true }] } },
        });
      }

      if (url === '/provider/company/members') {
        return Promise.resolve({
          data: {
            data: [
              { id: 1, user_id: 7, name: 'Nadia', role: 'worker', status: 'active' },
              { id: 2, user_id: 9, name: 'Karim', role: 'worker', status: 'active' },
            ],
          },
        });
      }

      return Promise.resolve({ data: { data: [] } });
    });
  });

  it('déplie la composition et recrute un collègue', async () => {
    mockAuth.user = { organization_permissions: ['team.view', 'team.manage'] };

    monter(<CompanyFieldTeamsScreen />);

    fireEvent.press(await screen.findByTestId('equipe-5'));

    // Le membre en place est là, et le collègue non membre est proposé au recrutement.
    expect(await screen.findByText(/Nadia/)).toBeTruthy();
    fireEvent.press(await screen.findByText('Ajouter'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/field-teams/5/members', {
        user_id: 9,
      }),
    );
  });

  it('retire un membre de l’équipe', async () => {
    mockAuth.user = { organization_permissions: ['team.view', 'team.manage'] };

    monter(<CompanyFieldTeamsScreen />);

    fireEvent.press(await screen.findByTestId('equipe-5'));
    fireEvent.press(await screen.findByText('Retirer'));

    await waitFor(() =>
      expect(mockDelete).toHaveBeenCalledWith('/provider/company/field-teams/5/members/7'),
    );
  });

  it('laisse consulter sans composer à qui n’a que la lecture', async () => {
    // `team.view` ouvre la liste — un chef d'équipe consulte légitimement sa composition — sans
    // donner le moindre droit d'écriture.
    mockAuth.user = { organization_permissions: ['team.view'] };

    monter(<CompanyFieldTeamsScreen />);

    fireEvent.press(await screen.findByTestId('equipe-5'));

    expect(await screen.findByText(/Nadia/)).toBeTruthy();
    expect(screen.queryByText('Retirer')).toBeNull();
    expect(screen.queryByText('Ajouter')).toBeNull();
  });
});

describe('CompanyDispatchScreen — confier à une équipe', () => {
  beforeEach(() => {
    mockGet.mockReset().mockImplementation((url: string) => {
      if (url === '/provider/company/missions') {
        return Promise.resolve({ data: { data: [MISSION] } });
      }

      if (url === '/provider/company/field-teams') {
        return Promise.resolve({ data: { data: [EQUIPE] } });
      }

      return Promise.resolve({ data: { data: [] } });
    });
  });

  it('confie la mission à l’équipe choisie', async () => {
    mockAuth.user = { organization_permissions: ['missions.dispatch', 'missions.assign'] };

    /*
     * Le choix passe par un `Alert.alert` à boutons — on l'intercepte pour presser l'équipe, plutôt
     * que de déclarer l'écran testé « affiché donc fonctionnel ». Sans cela, on vérifierait qu'un
     * bouton existe, pas qu'il mène quelque part.
     */
    const espionAlerte = jest.spyOn(Alert, 'alert').mockImplementation((_titre, _msg, boutons) => {
      const equipe = (boutons ?? []).find((b) => b.text === 'Équipe Nord');
      equipe?.onPress?.();
    });

    monter(<CompanyDispatchScreen />);

    fireEvent.press(await screen.findByText('Équipe'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/missions/12/assign-team', {
        field_team_id: 5,
      }),
    );

    espionAlerte.mockRestore();
  });

  it('dit qu’il faut d’abord créer une équipe, plutôt que d’échouer en silence', async () => {
    mockAuth.user = { organization_permissions: ['missions.dispatch'] };

    mockGet.mockImplementation((url: string) =>
      url === '/provider/company/missions'
        ? Promise.resolve({ data: { data: [MISSION] } })
        : Promise.resolve({ data: { data: [] } }),
    );

    const espionAlerte = jest.spyOn(Alert, 'alert').mockImplementation(() => {});

    monter(<CompanyDispatchScreen />);
    fireEvent.press(await screen.findByText('Équipe'));

    expect(espionAlerte).toHaveBeenCalledWith(
      'Aucune équipe',
      expect.stringContaining('Créez'),
    );
    expect(mockPost).not.toHaveBeenCalled();

    espionAlerte.mockRestore();
  });
});
