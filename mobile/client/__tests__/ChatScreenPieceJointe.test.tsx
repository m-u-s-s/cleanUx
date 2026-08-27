/**
 * L'écran de discussion appelle bien le composant de pièce jointe, et n'en invente pas.
 */
import React from 'react';
import { render } from '@testing-library/react-native';
import { ChatScreen } from '@/screens/ChatScreen';

const messages: unknown[] = [];

jest.mock('@/chat/hooks', () => ({
  __esModule: true,
  useChatMessages: () => ({ data: messages, refetch: jest.fn() }),
  useSendMessage: () => ({ mutate: jest.fn(), isPending: false }),
  useMarkThreadRead: () => ({ mutate: jest.fn() }),
  useLiveChat: jest.fn(),
  useChatThreads: () => ({ data: [] }),
}));

jest.mock('@/auth', () => ({
  __esModule: true,
  useAuth: () => ({ user: { id: 3, name: 'Cliente' } }),
}));

const LIEN = 'https://brio.test/api/v2/chat/messages/7/attachment/appareil?viewer=3&signature=abc';

function monter() {
  const route = { params: { threadId: 1 } } as never;

  return render(<ChatScreen route={route} navigation={{} as never} />);
}

describe('l’écran de discussion du client', () => {
  afterEach(() => {
    messages.length = 0;
  });

  it('affiche la pièce jointe que le serveur a jointe au message', () => {
    messages.push({
      id: 7,
      thread_id: 1,
      sender_id: 9,
      sender_name: 'Prestataire',
      body: 'Voici la photo',
      attachment: { url: LIEN, mime_type: 'image/jpeg', size_bytes: 90_000 },
      created_at: '2026-08-24T10:00:00Z',
    });

    expect(monter().getByTestId('piece-jointe-image').props.source).toEqual({ uri: LIEN });
  });

  /** LE TÉMOIN : le même écran, le même message, sans pièce — et rien n'apparaît. */
  it('n’invente pas de pièce quand le message n’en porte pas', () => {
    messages.push({
      id: 8,
      thread_id: 1,
      sender_id: 9,
      sender_name: 'Prestataire',
      body: 'Juste du texte',
      attachment: null,
      created_at: '2026-08-24T10:01:00Z',
    });

    const { queryByTestId, getByText } = monter();

    expect(getByText('Juste du texte')).toBeTruthy();
    expect(queryByTestId('piece-jointe-image')).toBeNull();
    expect(queryByTestId('piece-jointe-fichier')).toBeNull();
  });
});
