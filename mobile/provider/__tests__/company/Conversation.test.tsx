/**
 * LOT 7 CÔTÉ MOBILE — UNE CONVERSATION, PAS UN FORMULAIRE.
 *
 * `CompanyChannelsScreen` mêlait la liste et le fil, SANS temps réel : il fallait tirer pour
 * rafraîchir. Le canal `channel.{id}` est pourtant autorisé et fonctionnel côté serveur depuis
 * longtemps — c'est l'application qui ne s'y abonnait pas.
 *
 * ON PRESSE, ET ON VÉRIFIE L'ABONNEMENT. Un écran monté n'est pas un écran branché : `useChannel`
 * est espionné pour prouver qu'il reçoit le bon nom de canal, comme `useLiveMissionUpdates` au
 * lot 4 — défini depuis longtemps et jamais appelé.
 */
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';

notifyManager.setScheduler((callback) => callback());

const mockAuth = { user: { id: 1 } as unknown, logout: jest.fn() };

const mockGet = jest.fn();
const mockPost = jest.fn();
const mockDelete = jest.fn();

/** Espionné pour prouver l'abonnement, et pour pouvoir simuler une émission. */
const mockUseChannel = jest.fn();

jest.mock('@/auth', () => ({
  useAuth: () => mockAuth,
  can: jest.requireActual('../../../shared/src/auth/permissions').can,
}));

jest.mock('@/api', () => ({
  apiClient: {
    get: (...args: unknown[]) => mockGet(...args),
    post: (...args: unknown[]) => mockPost(...args),
    delete: (...args: unknown[]) => mockDelete(...args),
    patch: jest.fn(),
    put: jest.fn(),
  },
}));

jest.mock('@/realtime', () => ({
  useChannel: (...args: unknown[]) => mockUseChannel(...args),
}));

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn() }),
  useRoute: () => ({ params: { channelId: 4 } }),
}));

/*
 * Le module natif d'enregistrement n'existe pas encore dans ce workspace — c'est le sujet du
 * commentaire de `voiceRecorder.ts`. On le bouchonne pour vérifier que le bouton micro APPELLE
 * l'enregistreur puis poste, sans dépendre d'un dev-client reconstruit.
 */
const mockEnregistrer = jest.fn();

jest.mock('@/company/voiceRecorder', () => ({
  enregistrerNoteVocale: (...args: unknown[]) => mockEnregistrer(...args),
}));

import { ChannelConversationScreen } from '@/screens/company/ChannelConversationScreen';

function monter() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <ChannelConversationScreen />
    </QueryClientProvider>,
  );
}

beforeEach(() => {
  mockUseChannel.mockReset();
  mockEnregistrer.mockReset();
  mockPost.mockReset().mockResolvedValue({ data: { data: {} } });
  mockDelete.mockReset().mockResolvedValue({ data: { data: {} } });

  mockGet.mockReset().mockImplementation((url: string) => {
    if (url === '/provider/company/channels/4/messages') {
      return Promise.resolve({
        data: {
          data: [
            { id: 1, content: 'Bonjour', sender: 'Camille', sender_id: 2, is_system: false, sent_at: null },
          ],
        },
      });
    }

    if (url === '/provider/company/channels/4/members') {
      return Promise.resolve({
        data: { data: [{ user_id: 2, name: 'Camille', role: 'member' }] },
      });
    }

    if (url === '/provider/company/members') {
      return Promise.resolve({
        data: {
          data: [
            { user_id: 2, name: 'Camille', status: 'active' },
            { user_id: 9, name: 'Karim', status: 'active' },
          ],
        },
      });
    }

    return Promise.resolve({ data: { data: [] } });
  });
});

describe('ChannelConversationScreen', () => {
  it('envoie un message', async () => {
    monter();

    await screen.findByText('Bonjour');

    fireEvent.changeText(screen.getByTestId('champ-message'), 'Bien reçu');
    fireEvent.press(screen.getByText('Envoyer'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/channels/4/messages', {
        content: 'Bien reçu',
      }),
    );
  });

  it('s’abonne au canal temps réel', async () => {
    monter();

    await screen.findByText('Bonjour');

    // Le nom du canal compte : `channel.{id}` est ce que le serveur autorise et émet.
    expect(mockUseChannel).toHaveBeenCalledWith('channel.4', expect.any(Object));
  });

  it('rafraîchit le fil quand un message arrive en temps réel', async () => {
    monter();
    await screen.findByText('Bonjour');

    const gestionnaires = mockUseChannel.mock.calls.at(-1)?.[1] as Record<string, () => void>;

    mockGet.mockImplementation((url: string) =>
      url === '/provider/company/channels/4/messages'
        ? Promise.resolve({
            data: {
              data: [
                { id: 1, content: 'Bonjour', sender: 'Camille', sender_id: 2, is_system: false, sent_at: null },
                { id: 2, content: 'On arrive', sender: 'Karim', sender_id: 9, is_system: false, sent_at: null },
              ],
            },
          })
        : Promise.resolve({ data: { data: [] } }),
    );

    // Émission simulée : c'est ce que fait Reverb quand quelqu'un écrit ailleurs.
    gestionnaires['message.sent']?.();

    expect(await screen.findByText('On arrive')).toBeTruthy();
  });

  it('gère les participants en deux gestes', async () => {
    monter();

    fireEvent.press(await screen.findByTestId('ouvrir-participants'));

    // Karim n'est pas encore dans le fil : il est proposé à l'ajout.
    fireEvent.press(await screen.findByText('Ajouter'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/channels/4/members', {
        user_id: 9,
      }),
    );

    fireEvent.press(screen.getByText('Retirer'));

    await waitFor(() =>
      expect(mockDelete).toHaveBeenCalledWith('/provider/company/channels/4/members/2'),
    );
  });

  it('envoie une note vocale', async () => {
    mockEnregistrer.mockResolvedValue({
      fichier: { uri: 'file://note.m4a', name: 'note.m4a', type: 'audio/m4a' },
      dureeSecondes: 12,
    });

    monter();

    fireEvent.press(await screen.findByTestId('bouton-micro'));

    await waitFor(() => expect(mockEnregistrer).toHaveBeenCalled());
    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith(
        '/provider/company/channels/4/voice',
        expect.anything(),
      ),
    );
  });

  it('ne poste rien quand le micro est refusé', async () => {
    /*
     * Refuser le micro est une réponse légitime, et le module natif peut être absent d'un
     * dev-client non reconstruit. Dans les deux cas l'enregistreur rend `null` : on ne poste pas,
     * et surtout l'écran ne tombe pas.
     */
    mockEnregistrer.mockResolvedValue(null);

    monter();

    fireEvent.press(await screen.findByTestId('bouton-micro'));

    await waitFor(() => expect(mockEnregistrer).toHaveBeenCalled());

    expect(mockPost).not.toHaveBeenCalledWith(
      '/provider/company/channels/4/voice',
      expect.anything(),
    );
  });
});
