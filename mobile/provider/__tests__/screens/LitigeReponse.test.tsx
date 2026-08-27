/**
 * L'APPLICATION PRESTATAIRE NE SAVAIT QUE LISTER.
 *
 * `GET /provider/disputes` rendait des dossiers, `POST /{dispute}/respond` acceptait une réponse,
 * et rien entre les deux : répondre depuis le téléphone demandait d'écrire à l'aveugle.
 *
 * Ce fichier vérifie les DEUX moitiés — l'écran affiche le fil et répond, ET une porte y mène.
 * La seconde compte autant : un écran qu'aucune navigation n'atteint est le mode d'échec dominant
 * de ce dépôt, et `tsc` n'en dit rien.
 */
import React from 'react';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MockAdapter from 'axios-mock-adapter';

const mockNavigate = jest.fn();

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
  useRoute: () => ({ params: { disputeId: 7 } }),
}));

import * as ImagePicker from 'expo-image-picker';
import { apiClient } from '@/api';
import { ProviderDisputeDetailScreen } from '@/screens/ProviderDisputeDetailScreen';

const mock = new MockAdapter(apiClient);

function enveloppe() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: Infinity }, mutations: { retry: false } },
  });

  return function Enveloppe({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client }, children);
  };
}

/** Les champs d'un `FormData`, quelle qu'en soit l'implémentation. */
function parties(corps: FormData): [string, unknown][] {
  const brutes = (corps as unknown as { _parts?: [string, unknown][] })._parts;

  return Array.isArray(brutes) ? brutes : Array.from(corps.entries());
}

const DOSSIER = {
  id: 7,
  reference: 'DSP-007',
  subject: 'Salon resté sale',
  description: 'Le client dit que le salon n’a pas été fait.',
  status: 'open',
  attachments: [{ path: 'disputes/a.jpg', original_name: 'a.jpg', url: 'https://exemple.test/api/media/appareil?path=a&signature=x' }],
  events: [
    {
      id: 1,
      type: 'message',
      body: 'Voici les photos du salon.',
      author_role: 'client',
      created_at: '2026-08-27T10:00:00',
      attachments: [{ path: 'disputes/b.jpg', original_name: 'b.jpg', url: 'https://exemple.test/api/media/appareil?path=b&signature=y' }],
    },
  ],
};

beforeEach(() => {
  mock.reset();
  jest.clearAllMocks();
  mock.onGet('/provider/disputes/7').reply(200, { data: DOSSIER });
});

describe('le prestataire lit son litige et y répond', () => {
  it('affiche le fil que le serveur a bien voulu lui donner', async () => {
    render(<ProviderDisputeDetailScreen />, { wrapper: enveloppe() });

    expect(await screen.findByText('Salon resté sale')).toBeTruthy();
    expect(screen.getByText('Voici les photos du salon.')).toBeTruthy();
    expect(screen.getByText('Client')).toBeTruthy();
  });

  /**
   * L'API sert un lien que ce téléphone peut ouvrir : la route web exige une session en plus de la
   * signature, et rendait `302 → /login` à une balise `Image`.
   */
  it('affiche les pièces reçues, par le lien d’appareil', async () => {
    render(<ProviderDisputeDetailScreen />, { wrapper: enveloppe() });

    await screen.findByText('Salon resté sale');

    expect(screen.getAllByTestId('pieces-recues').length).toBe(2);
    expect(screen.getByLabelText('a.jpg')).toBeTruthy();
    expect(screen.getByLabelText('b.jpg')).toBeTruthy();
  });

  /** LE TÉMOIN : une pièce sans lien ne rend rien, plutôt qu'un carré cassé. */
  it('temoin une pièce sans lien n’affiche rien', async () => {
    mock.onGet('/provider/disputes/7').reply(200, {
      data: { ...DOSSIER, attachments: [{ path: 'x.jpg' }], events: [] },
    });

    render(<ProviderDisputeDetailScreen />, { wrapper: enveloppe() });

    await screen.findByText('Salon resté sale');

    expect(screen.queryAllByTestId('pieces-recues').length).toBe(0);
  });

  it('envoie une réponse sans photo, en JSON', async () => {
    mock.onPost('/provider/disputes/7/respond').reply(201, { event_id: 9 });

    render(<ProviderDisputeDetailScreen />, { wrapper: enveloppe() });

    await screen.findByText('Salon resté sale');

    fireEvent.changeText(screen.getByPlaceholderText('Expliquez ce qui s’est passé'), 'Le salon a été fait.');
    fireEvent.press(screen.getByText('Envoyer la réponse'));

    await waitFor(() => {
      const envoi = mock.history.post.find(r => r.url === '/provider/disputes/7/respond');

      expect(envoi).toBeTruthy();
      expect(typeof envoi!.data).toBe('string');
      expect(JSON.parse(envoi!.data)).toMatchObject({ body: 'Le salon a été fait.' });
    });
  });

  it('envoie une réponse avec photo, en multipart', async () => {
    (ImagePicker.launchImageLibraryAsync as jest.Mock).mockResolvedValueOnce({
      canceled: false,
      assets: [{ uri: 'file:///tmp/preuve.jpg' }],
    });

    mock.onPost('/provider/disputes/7/respond').reply(201, { event_id: 10 });

    render(<ProviderDisputeDetailScreen />, { wrapper: enveloppe() });

    await screen.findByText('Salon resté sale');

    fireEvent.changeText(screen.getByPlaceholderText('Expliquez ce qui s’est passé'), 'Voici mon état des lieux.');
    fireEvent.press(screen.getByText('Ajouter une photo'));

    await waitFor(() => expect(ImagePicker.launchImageLibraryAsync).toHaveBeenCalled());

    fireEvent.press(screen.getByText('Envoyer la réponse'));

    await waitFor(() => {
      const envoi = mock.history.post.find(r => r.url === '/provider/disputes/7/respond');

      expect(envoi).toBeTruthy();
      expect(envoi!.headers?.['Content-Type']).toBe('multipart/form-data');

      const noms = parties(envoi!.data as FormData).map(([nom]) => nom);

      expect(noms).toContain('attachments[]');
      expect(noms).toContain('body');
    });
  });
});

/** L'AUTRE MOITIÉ : une porte y mène-t-elle ? */
describe('l’écran est atteignable', () => {
  it('la liste ouvre le détail, et le navigateur le monte', () => {
    const racine = join(__dirname, '..', '..', 'src');

    const liste = readFileSync(join(racine, 'screens', 'ProviderDisputesScreen.tsx'), 'utf8');
    const navigateur = readFileSync(join(racine, 'navigation', 'RootNavigator.tsx'), 'utf8');
    const types = readFileSync(join(racine, 'navigation', 'types.ts'), 'utf8');

    expect(liste).toContain("navigation.navigate('ProviderDisputeDetail'");
    expect(navigateur).toContain('name="ProviderDisputeDetail"');
    expect(navigateur).toContain('component={ProviderDisputeDetailScreen}');
    expect(types).toContain('ProviderDisputeDetail: { disputeId: number }');
  });
});
