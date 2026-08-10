/**
 * LE LIEN « RENVOYER LE SMS » — pressé, sur l'écran que le prestataire ATTEINT.
 *
 * Première version de ce fichier : le lien avait été posé dans `MissionExecutionScreen`, un écran
 * qui n'est enregistré dans AUCUN navigateur — ni même déclaré dans les types de route. Le test
 * passait, `tsc` passait, et le lien n'existait pour personne. C'est le piège des écrans orphelins,
 * et monter le composant directement dans un test ne le révèle pas : il faut viser l'écran que le
 * parcours ouvre réellement, ici `MissionDetailScreen` au statut `arrived` puis `started`.
 *
 * Un SMS se perd : réseau du client, numéro mal saisi, message noyé, plafond d'envoi atteint. Sans
 * ce lien, l'intervention s'arrêtait là — le prestataire devant la porte, le client sans ses six
 * chiffres, et pour seul recours l'annulation de la mission.
 *
 * LE RETOUR EST VÉRIFIÉ AUTANT QUE L'APPEL. « Envoyé » sans destinataire laisse le doute sur le bon
 * client, et c'est ce doute qui fait appuyer trois fois — ce qui épuise le plafond SMS et prive le
 * client des codes suivants. Un refus pour attente doit se lire comme un garde-fou, pas comme une
 * panne.
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import { Alert } from 'react-native';
import MockAdapter from 'axios-mock-adapter';

notifyManager.setScheduler((callback) => callback());

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true }),
}));

const mockNavigate = jest.fn();
jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate, goBack: jest.fn() }),
}));

jest.mock('@/tracking', () => ({
  useArriveOnSite: () => ({ mutate: jest.fn(), isPending: false }),
}));

import { apiClient } from '@/api';
import { MissionDetailScreen } from '@/screens/MissionDetailScreen';

const mock = new MockAdapter(apiClient);

const MISSION = {
  id: 12,
  status: 'started',
  service_name: 'Nettoyage',
  client_name: 'Marie',
  address: '1 rue Test',
  city: 'Bruxelles',
  postal_code: '1000',
  scheduled_date: '2026-06-01',
  scheduled_time: '10:00',
  booking_id: 4,
  checklists: [],
};

function monter() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <MissionDetailScreen route={{ params: { missionId: 12 } } as any} navigation={{} as any} />
    </QueryClientProvider>,
  );
}

describe('Renvoi du code par SMS', () => {
  beforeEach(() => {
    mock.reset();
    mock.onGet('/provider/missions/12').reply(200, { data: MISSION });
    jest.spyOn(Alert, 'alert').mockImplementation(() => undefined);
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  it('le lien appelle le point d’entrée de renvoi', async () => {
    mock.onPost('/provider/missions/12/codes/resend').reply(200, {
      ok: true,
      type: 'end',
      sent_to: '+3247******11',
    });

    monter();

    await waitFor(() => expect(screen.getByTestId('resend-end-code')).toBeTruthy());

    fireEvent.press(screen.getByTestId('resend-end-code'));

    await waitFor(() =>
      expect(mock.history.post.map((r) => r.url)).toContain('/provider/missions/12/codes/resend'),
    );

    // Le type voyage : le serveur distingue le code de début de celui de fin, et se tromper de
    // bout ferait renvoyer un code que le client ne peut pas utiliser.
    expect(JSON.parse(mock.history.post[0]!.data).type).toBe('end');
  });

  it('le numéro masqué est montré au prestataire', async () => {
    mock.onPost('/provider/missions/12/codes/resend').reply(200, {
      ok: true,
      type: 'end',
      sent_to: '+3247******11',
    });

    monter();

    await waitFor(() => expect(screen.getByTestId('resend-end-code')).toBeTruthy());
    fireEvent.press(screen.getByTestId('resend-end-code'));

    await waitFor(() => expect(Alert.alert).toHaveBeenCalled());

    const [titre, corps] = (Alert.alert as jest.Mock).mock.calls.at(-1)!;

    expect(titre).toBe('Code renvoyé');
    // Il confirme qu'on a écrit au bon client sans livrer le téléphone de quelqu'un chez qui le
    // prestataire n'ira peut-être jamais.
    expect(corps).toContain('+3247******11');
    expect(corps).toContain('n’est plus valide');
  });

  it('un refus pour attente est dit, pas avalé', async () => {
    mock.onPost('/provider/missions/12/codes/resend').reply(409, {
      ok: false,
      message: 'Patientez avant de renvoyer un nouveau code.',
    });

    monter();

    await waitFor(() => expect(screen.getByTestId('resend-end-code')).toBeTruthy());
    fireEvent.press(screen.getByTestId('resend-end-code'));

    await waitFor(() => expect(Alert.alert).toHaveBeenCalled());

    // Sans ce retour, le prestataire réappuie — et le plafond SMS du client saute pour de bon.
    expect((Alert.alert as jest.Mock).mock.calls.at(-1)![0]).toBe('Impossible');
  });
});
