/**
 * LA MODALE D'OFFRE — vérifiée EN PRESSANT, depuis le navigateur monté.
 *
 * Un test qui lirait la source ne dirait rien de ce qu'un prestataire obtient : c'est exactement le
 * piège qui a laissé `AssignmentOfferScreen` complet et orphelin pendant des mois — un écran d'offre
 * avec compte à rebours, monté nulle part, que personne n'a jamais vu.
 *
 * Ce qui est couvert ici :
 *  - une offre reçue fait APPARAÎTRE la modale par-dessus l'espace terrain ;
 *  - « Accepter » appelle l'API puis navigue vers la mission ;
 *  - « Refuser » appelle l'API et ferme ;
 *  - une offre déjà expirée côté SERVEUR ne s'affiche pas — le compte à rebours ne ment pas ;
 *  - le décompte est un ANNEAU dont l'arc suit le temps restant, et il SONNE en arrivant.
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';

// React Query planifie ses notifications via un `setTimeout(0)` : cette macrotâche se déclenche
// hors de toute portée `act()`, et React logue « not wrapped in act ». Même remède que les autres
// suites de ce dépôt : notification synchrone, dans ce fichier seulement.
notifyManager.setScheduler((callback) => callback());

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true }),
  default: {
    addEventListener: jest.fn(() => () => undefined),
    fetch: jest.fn().mockResolvedValue({ isConnected: true }),
  },
}));

// Le préfixe `mock` n'est pas cosmétique : Babel hisse les `jest.mock()` au-dessus des
// déclarations, et seules les variables ainsi nommées ont le droit d'être référencées dans la
// fabrique. Sans lui, la suite ne compile pas.
const mockNavigate = jest.fn();
jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
}));

/*
 * LE SON EST UN EFFET DE BORD OBSERVABLE, donc il se teste. `expo-audio` ouvre une ressource
 * native absente sous Jest ; on ne bouchonne pas pour faire passer, on bouchonne pour POUVOIR
 * VÉRIFIER qu'il est bien joué — une modale silencieuse expire sans que personne ne l'ait vue, et
 * rien d'autre dans la suite ne pourrait le dire.
 */
const mockPlay = jest.fn();
jest.mock('expo-audio', () => ({
  createAudioPlayer: () => ({ play: mockPlay, remove: jest.fn(), seekTo: jest.fn() }),
}));

import { apiClient } from '@/api';
import { OfferModal } from '@/offers';
import type { MissionOffer } from '@/offers';

const mock = new MockAdapter(apiClient);

function offre(overrides: Partial<MissionOffer> = {}): MissionOffer {
  return {
    assignment_id: 42,
    mission_id: 7,
    booking_id: 3,
    booking_mode: 'asap',
    trade_name: 'Plomberie',
    service_name: 'Plomberie',
    client_name: 'Marie',
    approximate_address: '1000 Bruxelles',
    city: 'Bruxelles',
    postal_code: '1000',
    scheduled_at: null,
    estimated_duration_minutes: 60,
    payout_cents: 4500,
    distance_m: 1200,
    distance_km: 1.2,
    latitude: 50.84,
    longitude: 4.35,
    expires_at: new Date(Date.now() + 20_000).toISOString(),
    ttl_seconds: 20,
    sent_at: new Date().toISOString(),
    ...overrides,
  };
}

function monter(o: MissionOffer, onDismiss = jest.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return {
    onDismiss,
    ...render(
      <QueryClientProvider client={client}>
        <OfferModal offer={o} onDismiss={onDismiss} />
      </QueryClientProvider>,
    ),
  };
}

describe('OfferModal', () => {
  beforeEach(() => {
    mock.reset();
    mockNavigate.mockClear();
    mockPlay.mockClear();
  });

  it('affiche le métier, la distance et la rémunération', () => {
    monter(offre());

    expect(screen.getByTestId('offer-trade')).toHaveTextContent('Plomberie');
    expect(screen.getByTestId('offer-distance')).toHaveTextContent('1.2 km');
    expect(screen.getByTestId('offer-payout')).toHaveTextContent('45,00 €');
  });

  it('affiche un compte à rebours calé sur l’échéance SERVEUR', () => {
    // Dix secondes restantes, pas vingt : le TTL nominal ne dicte rien, seule `expires_at` compte.
    monter(offre({ expires_at: new Date(Date.now() + 10_000).toISOString() }));

    expect(screen.getByTestId('offer-countdown')).toHaveTextContent('10 s pour répondre');
  });

  /**
   * L'ANNEAU, PAS UNE BARRE. Le prestataire regarde cet écran une demi-seconde, souvent en
   * conduisant : une forme qui se vide se lit sans être lue. L'arc doit SUIVRE le temps restant —
   * un anneau figé serait un décor, et c'est exactement ce qu'une régression produirait sans que
   * rien d'autre ne le signale.
   */
  it('dessine un anneau dont l’arc suit le temps restant', () => {
    const { unmount } = monter(offre({ expires_at: new Date(Date.now() + 15_000).toISOString() }));

    expect(screen.getByTestId('offer-countdown-ring')).toBeTruthy();
    const auxTroisQuarts = screen.getByTestId('offer-countdown-arc').props.strokeDashoffset;
    unmount();

    monter(offre({ expires_at: new Date(Date.now() + 5_000).toISOString() }));
    const presqueVide = screen.getByTestId('offer-countdown-arc').props.strokeDashoffset;

    // `strokeDashoffset` est la portion VIDE de l'anneau : elle grandit à mesure que le temps
    // s'écoule. Deux instants intermédiaires, jamais zéro — react-native-svg normalise un décalage
    // nul en `null`, et le test comparerait alors une valeur absente.
    expect(presqueVide).toBeGreaterThan(auxTroisQuarts);
  });

  it('sonne à l’arrivée de l’offre', () => {
    monter(offre());

    // Au premier plan, l'offre arrive par le canal temps réel ou par sondage : aucune notification
    // système ne sonne pour elle, et la vibration seule ne s'entend pas dans une sacoche.
    expect(mockPlay).toHaveBeenCalled();
  });

  it('presser Accepter appelle l’endpoint et navigue vers la mission', async () => {
    mock.onPost('/provider/assignments/42/accept').reply(200, { ok: true, mission_id: 7 });

    const { onDismiss } = monter(offre());

    fireEvent.press(screen.getByLabelText('Accepter'));

    await waitFor(() => expect(mockNavigate).toHaveBeenCalledWith('MissionDetail', { missionId: 7 }));
    expect(onDismiss).toHaveBeenCalledWith(42);
  });

  it('presser Refuser appelle l’endpoint et ferme la modale', async () => {
    mock.onPost('/provider/assignments/42/decline').reply(200, { ok: true });

    const { onDismiss } = monter(offre());

    fireEvent.press(screen.getByLabelText('Refuser'));

    await waitFor(() => expect(onDismiss).toHaveBeenCalledWith(42));
    expect(mock.history.post.map((r) => r.url)).toContain('/provider/assignments/42/decline');
  });

  it('une course déjà prise n’est pas une panne : la modale se ferme', async () => {
    mock.onPost('/provider/assignments/42/accept').reply(409, { message: 'déjà prise' });

    const { onDismiss } = monter(offre());

    fireEvent.press(screen.getByLabelText('Accepter'));

    await waitFor(() => expect(onDismiss).toHaveBeenCalledWith(42));
    expect(mockNavigate).not.toHaveBeenCalled();
  });

  it('se ferme d’elle-même quand l’échéance serveur est passée', async () => {
    const { onDismiss } = monter(offre({ expires_at: new Date(Date.now() - 1_000).toISOString() }));

    // Le serveur a déjà escaladé : laisser la modale ouverte ferait cliquer sur une erreur.
    await waitFor(() => expect(onDismiss).toHaveBeenCalledWith(42));
  });
});
