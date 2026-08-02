import React from 'react';
import { apiClient } from '@/api';
import { useAsapOffers, useAcceptAsapOffer, useDeclineAsapOffer } from '@/asap';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor, act } from '@testing-library/react-native';
import MockAdapter from 'axios-mock-adapter';

jest.mock('@/storage/secureStore');
jest.mock('@/realtime', () => ({ useChannel: () => {} }));

/**
 * L'autre bout de la course immédiate.
 *
 * Les points d'API existaient depuis la livraison du moteur de commande, et l'application ne les
 * appelait nulle part : un client pouvait demander une intervention dans l'heure depuis son
 * téléphone, et AUCUN prestataire utilisant l'application ne pouvait l'accepter. Le mode le plus
 * ambitieux du produit n'avait pas de boucle fermée.
 */
const wrapper = ({ children }: { children: React.ReactNode }) => {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
};

const OFFER = {
  id: 7,
  asap_dispatch_request_id: 42,
  trade: 'Plomberie',
  distance_m: 2300,
  distance_km: 2.3,
  estimate_min_cents: 8500,
  estimate_max_cents: 12000,
  notified_at: '2026-08-02T10:00:00+00:00',
  waiting_seconds: 45,
};

describe('Offres de course immédiate', () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(apiClient);
  });

  afterEach(() => mock.restore());

  it('liste ce qui est proposé au prestataire', async () => {
    mock.onGet('/provider/asap-offers').reply(200, { data: [OFFER] });

    const { result } = renderHook(() => useAsapOffers(), { wrapper });

    await waitFor(() => expect(result.current.offers).toHaveLength(1));
    expect(result.current.offers[0]!.trade).toBe('Plomberie');
    expect(result.current.offers[0]!.distance_km).toBe(2.3);
  });

  /**
   * Le rafraîchissement est COURT.
   *
   * Une course immédiate se joue en secondes : une liste rafraîchie toutes les minutes proposerait
   * des courses déjà prises, et le prestataire apprendrait à ne plus la croire.
   */
  it('se rafraîchit à un rythme compatible avec l’urgence', () => {
    const { result } = renderHook(() => useAsapOffers(), { wrapper });

    expect(result.current.refetchIntervalMs).toBeLessThanOrEqual(10_000);
  });

  it('accepte une course', async () => {
    let called = false;
    mock.onPost('/provider/asap-offers/42/accept').reply(() => {
      called = true;
      return [200, { data: { asap_dispatch_request_id: 42, status: 'accepted', booking_id: 99 } }];
    });

    const { result } = renderHook(() => useAcceptAsapOffer(), { wrapper });
    await act(async () => {
      await result.current.mutateAsync(42);
    });

    expect(called).toBe(true);
  });

  /**
   * Une course déjà prise n'est PAS une erreur technique.
   *
   * Le serveur répond 409 — premier arrivé, premier servi. Un prestataire qui voit « une erreur est
   * survenue » croit à un bug de l'application ; il doit lire que la course vient de partir.
   */
  it('distingue une course déjà prise d’une panne', async () => {
    mock.onPost('/provider/asap-offers/42/accept').reply(409, {
      message: 'Cette course vient d’être prise par un autre professionnel.',
    });

    const { result } = renderHook(() => useAcceptAsapOffer(), { wrapper });

    let status: number | undefined;
    await act(async () => {
      try {
        await result.current.mutateAsync(42);
      } catch (e: any) {
        status = e.status;
      }
    });

    expect(status).toBe(409);
  });

  it('passe son tour, et le refus part au serveur', async () => {
    let body: any = null;
    mock.onPost('/provider/asap-offers/42/decline').reply((config) => {
      body = JSON.parse(config.data ?? '{}');
      return [200, { ok: true }];
    });

    const { result } = renderHook(() => useDeclineAsapOffer(), { wrapper });
    await act(async () => {
      await result.current.mutateAsync({ requestId: 42, reason: 'trop loin' });
    });

    expect(body.reason).toBe('trop loin');
  });
});
