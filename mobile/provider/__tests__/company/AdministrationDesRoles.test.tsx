/**
 * GÉRER SON ÉQUIPE DEPUIS LE TÉLÉPHONE — EN PRESSANT, PAS EN LISANT LA SOURCE.
 *
 * `CompanyMembersScreen` était en LECTURE SEULE : l'exigence 6 dit « y compris depuis le mobile »,
 * et changer un sous-rôle supposait un poste de travail.
 *
 * CE FICHIER PRESSE. Un écran monté n'est pas un écran atteignable, et un bouton rendu n'est pas un
 * bouton qui appelle quelque chose — deux défauts déjà vus dans ce dépôt. On ouvre donc le panneau
 * d'actions par un appui réel, puis on presse le geste, et on vérifie l'appel HTTP qui en résulte.
 *
 * ON NE VÉRIFIE PAS ICI QUE LE SERVEUR REFUSE. C'est le rôle de `ApiAdministrationMembresTest` :
 * masquer un bouton n'a jamais protégé une donnée. Ce qui se joue ici est l'inverse — ne pas
 * PROMETTRE un geste que l'API refusera.
 */
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';

notifyManager.setScheduler((callback) => callback());

const mockAuth = { user: null as unknown, logout: jest.fn() };

const mockGet = jest.fn();
const mockPatch = jest.fn();
const mockPost = jest.fn();
const mockDelete = jest.fn();

jest.mock('@/auth', () => ({
  useAuth: () => mockAuth,
  can: jest.requireActual('../../../shared/src/auth/permissions').can,
}));

jest.mock('@/api', () => ({
  apiClient: {
    get: (...args: unknown[]) => mockGet(...args),
    patch: (...args: unknown[]) => mockPatch(...args),
    post: (...args: unknown[]) => mockPost(...args),
    delete: (...args: unknown[]) => mockDelete(...args),
  },
}));

import { CompanyMembersScreen } from '@/screens/company/CompanyMembersScreen';

const MEMBRE = {
  id: 42,
  user_id: 7,
  name: 'Nadia Berger',
  email: 'nadia@example.test',
  role: 'worker',
  status: 'active',
};

function monter() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <CompanyMembersScreen />
    </QueryClientProvider>,
  );
}

beforeEach(() => {
  mockGet.mockReset().mockResolvedValue({ data: { data: [MEMBRE] } });
  mockPatch.mockReset().mockResolvedValue({ data: { data: {} } });
  mockPost.mockReset().mockResolvedValue({ data: { data: {} } });
  mockDelete.mockReset().mockResolvedValue({ data: { data: {} } });
});

describe('CompanyMembersScreen — administration depuis le téléphone', () => {
  it('laisse le propriétaire changer un sous-rôle', async () => {
    mockAuth.user = {
      organization_type: 'provider_company',
      organization_permissions: ['team.view', 'members.edit_role'],
    };

    monter();

    // 1. La ligne du membre est là, et elle s'ouvre.
    fireEvent.press(await screen.findByTestId('membre-42'));

    // 2. Le geste est proposé, et il appelle vraiment l'API.
    fireEvent.press(screen.getByText('Répartiteur'));

    await waitFor(() =>
      expect(mockPatch).toHaveBeenCalledWith('/provider/company/members/42/role', {
        role: 'dispatcher',
      }),
    );
  });

  it('laisse suspendre un membre', async () => {
    mockAuth.user = {
      organization_type: 'provider_company',
      organization_permissions: ['team.view', 'members.suspend'],
    };

    monter();

    fireEvent.press(await screen.findByTestId('membre-42'));
    fireEvent.press(screen.getByText('Suspendre'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/members/42/suspend'),
    );
  });

  it('ne propose AUCUN geste à qui n’a que la lecture', async () => {
    /*
     * Le cas qui compte. `team.view` ouvre la liste — un chef d'équipe consulte légitimement son
     * effectif — sans donner le moindre droit d'écriture. La ligne ne doit alors même pas s'ouvrir :
     * un panneau vide se lirait comme une panne.
     */
    mockAuth.user = {
      organization_type: 'provider_company',
      organization_permissions: ['team.view'],
    };

    monter();

    fireEvent.press(await screen.findByTestId('membre-42'));

    expect(screen.queryByTestId('actions-membre-42')).toBeNull();
    expect(screen.queryByText('Suspendre')).toBeNull();
    expect(screen.queryByText('Retirer de la société')).toBeNull();
  });

  it('n’expose que les gestes dont la clé est accordée', async () => {
    // Suspendre et retirer sont deux clés distinctes : `members.suspend` ne doit pas ouvrir le
    // retrait, qui libère les missions à venir et coupe les canaux.
    mockAuth.user = {
      organization_type: 'provider_company',
      organization_permissions: ['team.view', 'members.suspend'],
    };

    monter();

    fireEvent.press(await screen.findByTestId('membre-42'));

    screen.getByText('Suspendre');
    expect(screen.queryByText('Retirer de la société')).toBeNull();
    expect(screen.queryByText('Répartiteur')).toBeNull();
  });

  it('applique le défaut-refus quand le serveur ne déclare aucune clé', async () => {
    mockAuth.user = { organization_type: 'provider_company' };

    monter();

    fireEvent.press(await screen.findByTestId('membre-42'));

    expect(screen.queryByTestId('actions-membre-42')).toBeNull();
  });
});
