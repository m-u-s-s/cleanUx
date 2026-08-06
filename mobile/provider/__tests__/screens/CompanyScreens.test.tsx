import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

import { CompanyMembersScreen } from '@/screens/company/CompanyMembersScreen';
import { CompanyFieldTeamsScreen } from '@/screens/company/CompanyFieldTeamsScreen';
import { CompanyTasksScreen } from '@/screens/company/CompanyTasksScreen';
import { CompanyDispatchScreen } from '@/screens/company/CompanyDispatchScreen';
import { CompanyChannelsScreen } from '@/screens/company/CompanyChannelsScreen';
import { apiClient } from '@/api';

/**
 * LES ÉCRANS SOCIÉTÉ, EN NATIF.
 *
 * Ils étaient servis en WebView non par choix d'interface mais par nécessité :
 * `routes/api/provider.php` couvrait le prestataire INDIVIDUEL — missions, disponibilités, badges,
 * portefeuille — et n'exposait RIEN de la société. Vérifié endpoint par endpoint avant d'écrire.
 *
 * L'API `/provider/company/*` a été créée avec ces écrans. Ces tests figent ce que chacun demande
 * au serveur et ce qu'il rend visible — un écran natif qui interroge la mauvaise route est un écran
 * vide, sans erreur.
 */

jest.mock('@/api', () => ({
  apiClient: {
    get: jest.fn(),
    post: jest.fn(),
    patch: jest.fn(),
  },
}));

const mockGet = apiClient.get as jest.Mock;
const mockPost = apiClient.post as jest.Mock;
const mockPatch = apiClient.patch as jest.Mock;

function afficher(composant: React.ReactElement) {
  // `retry: false` — sans cela, une requête échouée est rejouée et le test attend pour rien.
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(<QueryClientProvider client={client}>{composant}</QueryClientProvider>);
}

beforeEach(() => {
  jest.clearAllMocks();
});

describe('CompanyMembersScreen', () => {
  it('liste les membres renvoyés par /provider/company/members', async () => {
    mockGet.mockResolvedValue({
      data: { data: [{ id: 7, user_id: 3, name: 'Camille Dupont', email: 'c@d.fr', role: 'worker', status: 'active' }] },
    });

    const { getByText } = afficher(<CompanyMembersScreen />);

    await waitFor(() => getByText('Camille Dupont'));
    expect(mockGet).toHaveBeenCalledWith('/provider/company/members');
  });

  it('affiche un nom de repli quand le compte a été supprimé', async () => {
    mockGet.mockResolvedValue({
      data: { data: [{ id: 8, user_id: 0, name: null, email: null, role: 'worker', status: 'active' }] },
    });

    const { getByText } = afficher(<CompanyMembersScreen />);

    await waitFor(() => getByText('Utilisateur supprimé'));
  });
});

describe('CompanyFieldTeamsScreen', () => {
  it('liste les agences et en crée une', async () => {
    mockGet.mockResolvedValue({
      data: { data: [{ id: 2, name: 'Agence Nord', status: 'active', zone: null, lead: null, max_concurrent_missions: 3 }] },
    });
    mockPost.mockResolvedValue({ data: {} });

    const { getByText, getByTestId } = afficher(<CompanyFieldTeamsScreen />);

    await waitFor(() => getByText('Agence Nord'));

    fireEvent.changeText(getByTestId('champ-nom-equipe'), 'Agence Sud');
    fireEvent.press(getByText('Créer'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/field-teams', { name: 'Agence Sud' }),
    );
  });

  it('archive une agence active', async () => {
    mockGet.mockResolvedValue({
      data: { data: [{ id: 5, name: 'Agence Est', status: 'active', zone: null, lead: null, max_concurrent_missions: 2 }] },
    });
    mockPatch.mockResolvedValue({ data: {} });

    const { getByText } = afficher(<CompanyFieldTeamsScreen />);

    await waitFor(() => getByText('Agence Est'));
    fireEvent.press(getByText('Archiver'));

    await waitFor(() =>
      expect(mockPatch).toHaveBeenCalledWith('/provider/company/field-teams/5/archive'),
    );
  });
});

describe('CompanyTasksScreen', () => {
  it('propose l’étape suivante du cycle de vie, et pas au-delà', async () => {
    mockGet.mockResolvedValue({
      data: {
        data: [
          { id: 1, title: 'À planifier', description: null, status: 'todo', priority: 'medium' },
          { id: 2, title: 'Déjà close', description: null, status: 'done', priority: 'low' },
        ],
      },
    });
    mockPatch.mockResolvedValue({ data: {} });

    const { getByText, getByTestId, queryAllByText } = afficher(<CompanyTasksScreen />);

    await waitFor(() => getByText('À planifier'));

    // La tâche « todo » propose « En cours » ; la tâche « done » ne propose plus rien.
    expect(queryAllByText('Terminée').length).toBe(1); // le badge de la tâche close, pas un bouton
    fireEvent.press(getByText('En cours'));

    await waitFor(() =>
      expect(mockPatch).toHaveBeenCalledWith('/provider/company/tasks/1', { status: 'in_progress' }),
    );

    expect(getByTestId('tache-2')).toBeTruthy();
  });
});

describe('CompanyDispatchScreen', () => {
  it('liste les missions et propose de les assigner', async () => {
    mockGet.mockImplementation((url: string) => {
      if (url === '/provider/company/missions') {
        return Promise.resolve({
          data: {
            data: [
              {
                id: 11,
                status: 'planned',
                planned_start_at: null,
                site: 'Siège Lyon',
                city: 'Lyon',
                lead: null,
                lead_user_id: null,
              },
            ],
          },
        });
      }

      return Promise.resolve({ data: { data: [] } });
    });

    const { getByText } = afficher(<CompanyDispatchScreen />);

    await waitFor(() => getByText('Siège Lyon — Lyon'));
    // Sans lead, l'action proposée est « Assigner » et non « Réassigner ».
    getByText('Assigner');
    expect(mockGet).toHaveBeenCalledWith('/provider/company/missions');
  });
});

describe('CompanyChannelsScreen', () => {
  it('ouvre le premier canal et y envoie un message', async () => {
    mockGet.mockImplementation((url: string) => {
      if (url === '/provider/company/channels') {
        return Promise.resolve({
          data: { data: [{ id: 4, name: 'general', type: 'team', is_private: false }] },
        });
      }

      if (url === '/provider/company/channels/4/messages') {
        return Promise.resolve({
          data: {
            data: [
              { id: 1, content: 'Bonjour', sender: 'Camille', sender_id: 2, is_system: false, sent_at: null },
            ],
          },
        });
      }

      return Promise.resolve({ data: { data: [] } });
    });
    mockPost.mockResolvedValue({ data: {} });

    const { getByText, getByTestId } = afficher(<CompanyChannelsScreen />);

    // Le premier canal s'ouvre seul : un écran vide sans explication ferait croire à une panne.
    await waitFor(() => getByText('Bonjour'));

    fireEvent.changeText(getByTestId('champ-message'), 'Bien reçu');
    fireEvent.press(getByText('Envoyer'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/channels/4/messages', {
        content: 'Bien reçu',
      }),
    );
  });
});
