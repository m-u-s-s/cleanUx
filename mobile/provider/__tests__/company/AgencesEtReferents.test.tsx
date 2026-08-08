/**
 * LOT 6 CÔTÉ MOBILE — DÉSIGNER UN HABITUÉ, ET DÉCLARER SES IMPLANTATIONS.
 *
 * L'écran Sites était en LECTURE, au motif que la désignation d'un référent se pose au bureau. Mais
 * c'est SUR PLACE qu'on apprend qui connaît le bâtiment — celui qui a le code de la porte, l'étage à
 * ne pas déranger avant 10 h. Le noter en rentrant, c'est ne jamais le noter.
 *
 * LES AGENCES SONT UNE AUTRE NOTION QUE LES SITES, et l'écran le dit explicitement :
 * `organization_sites` désigne les locaux du CLIENT, `provider_agencies` les implantations de la
 * SOCIÉTÉ. Les confondre donnerait à une société un droit sur les locaux de ses clients.
 */
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
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
    put: jest.fn(),
  },
}));

import { CompanySitesScreen } from '@/screens/company/CompanySitesScreen';
import { CompanyAgenciesScreen } from '@/screens/company/CompanyAgenciesScreen';

const SITE = {
  id: 3,
  name: 'Résidence Les Tilleuls',
  city: 'Bruxelles',
  postal_code: '1000',
  address: 'Rue Haute 1',
  referents: [{ id: 7, name: 'Nadia', role: 'lead' }],
};

const AGENCE = {
  id: 4,
  name: 'Dépôt Nord',
  city: 'Anvers',
  address: null,
  status: 'active',
  service_zone_id: null,
};

function monter(ecran: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(<QueryClientProvider client={client}>{ecran}</QueryClientProvider>);
}

beforeEach(() => {
  mockPost.mockReset().mockResolvedValue({ data: { data: {} } });
  mockPatch.mockReset().mockResolvedValue({ data: { data: {} } });
  mockDelete.mockReset().mockResolvedValue({ data: { data: {} } });

  mockGet.mockReset().mockImplementation((url: string) => {
    if (url === '/provider/company/sites') {
      return Promise.resolve({ data: { data: [SITE] } });
    }

    if (url === '/provider/company/agencies') {
      return Promise.resolve({ data: { data: [AGENCE] } });
    }

    if (url === '/provider/company/members') {
      return Promise.resolve({
        data: { data: [{ user_id: 9, name: 'Karim', status: 'active' }] },
      });
    }

    return Promise.resolve({ data: { data: [] } });
  });
});

describe('CompanySitesScreen — les référents', () => {
  it('désigne un habitué depuis le terrain', async () => {
    mockAuth.user = { organization_permissions: ['sites.view_all', 'sites.assign_members'] };

    monter(<CompanySitesScreen />);

    fireEvent.press(await screen.findByTestId('site-3'));
    fireEvent.press(await screen.findByText('Désigner'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/sites/3/referents', {
        user_id: 9,
        role: 'lead',
      }),
    );
  });

  it('retire un référent', async () => {
    mockAuth.user = { organization_permissions: ['sites.view_all', 'sites.assign_members'] };

    monter(<CompanySitesScreen />);

    fireEvent.press(await screen.findByTestId('site-3'));
    fireEvent.press(await screen.findByText('Retirer'));

    await waitFor(() =>
      expect(mockDelete).toHaveBeenCalledWith('/provider/company/sites/3/referents/7'),
    );
  });

  it('reste en lecture pour qui ne désigne pas', async () => {
    // `sites.view_all` ouvre la liste — se souvenir de qui connaît le bâtiment est utile à tous —
    // sans donner le droit de redessiner l'affectation durable.
    mockAuth.user = { organization_permissions: ['sites.view_all'] };

    monter(<CompanySitesScreen />);

    fireEvent.press(await screen.findByTestId('site-3'));

    expect(screen.queryByTestId('referents-3')).toBeNull();
    expect(screen.queryByText('Désigner')).toBeNull();
  });
});

describe('CompanyAgenciesScreen — les implantations', () => {
  it('crée une implantation', async () => {
    mockAuth.user = { organization_permissions: ['agencies.view', 'agencies.manage'] };

    monter(<CompanyAgenciesScreen />);

    fireEvent.changeText(await screen.findByTestId('champ-nom-agence'), 'Dépôt Sud');
    fireEvent.changeText(screen.getByTestId('champ-ville-agence'), 'Charleroi');
    fireEvent.press(screen.getByText('Créer'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/agencies', {
        name: 'Dépôt Sud',
        city: 'Charleroi',
      }),
    );
  });

  it('laisse le répartiteur consulter sans créer', async () => {
    // Lecture seule : il FILTRE par implantation, il ne redessine pas l'organigramme.
    mockAuth.user = { organization_permissions: ['agencies.view'] };

    monter(<CompanyAgenciesScreen />);

    expect(await screen.findByText('Dépôt Nord')).toBeTruthy();
    expect(screen.queryByTestId('champ-nom-agence')).toBeNull();
    expect(screen.queryByText('Archiver')).toBeNull();
  });
});
