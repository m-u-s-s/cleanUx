import React from 'react';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';

// React Query planifie ses notifications via un `setTimeout(0)` par défaut : cette macrotâche
// peut se déclencher après qu'un premier `waitFor` a déjà résolu, donc hors de toute portée
// `act()`, et React logue « not wrapped in act ». On force ici une notification synchrone,
// dans ce fichier de test seulement (voir __tests__/screens/ProviderMap.test.tsx pour le même
// pattern).
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
jest.mock('@react-navigation/native', () => ({ useNavigation: () => ({ navigate: mockNavigate }) }));

// Capture le `onClose` passé par DashboardActionsSheet à `@/ui`'s BottomSheet, pour qu'un test
// puisse simuler un pan-down-to-close (gorhom appelle `onClose` lui-même, sans jamais passer par
// le `close()` exposé par notre composant) — préfixe `mock` obligatoire pour survivre au hoisting
// des factories `jest.mock`.
const mockOnCloseCapture = { current: null as (() => void) | null };
// jest.fn() au niveau module (pas recréés à chaque rendu) pour pouvoir observer, après coup,
// qu'ils ont été appelés et dans quel ordre par rapport à `mockNavigate`.
const mockSheetExpand = jest.fn();
const mockSheetClose = jest.fn();

// Le sheet gorhom est remplacé par un conteneur simple : on teste le contenu et le câblage,
// pas l'animation native. La ref exposée par ce faux BottomSheet reste fonctionnelle
// (expand/close) pour pouvoir piloter DashboardActionsSheet depuis les tests, exactement comme
// Task 10 le fera en vrai.
jest.mock('@/ui', () => {
  const actual = jest.requireActual('@/ui');
  const { View } = require('react-native');
  const React = require('react');
  return {
    ...actual,
    BottomSheet: React.forwardRef(({ children, onClose }: any, ref: any) => {
      mockOnCloseCapture.current = onClose ?? null;
      React.useImperativeHandle(ref, () => ({
        expand: mockSheetExpand,
        close: mockSheetClose,
        collapse: jest.fn(),
        forceClose: jest.fn(),
        snapToIndex: jest.fn(),
        snapToPosition: jest.fn(),
      }));
      return <View>{children}</View>;
    }),
  };
});

import { apiClient } from '@/api';
import { DashboardActionsSheet } from '@/screens/components/DashboardActionsSheet';

const apiMock = new MockAdapter(apiClient);

function makeWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  );
}

beforeEach(() => {
  apiMock.reset();
  mockNavigate.mockClear();
  mockSheetExpand.mockClear();
  mockSheetClose.mockClear();
  mockOnCloseCapture.current = null;
  apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [] });
  apiMock.onGet('/provider/wallet/balance').reply(200, { available: 150, pending: 0, currency: 'EUR' });
  apiMock.onGet('/provider/presence-v2').reply(200, { data: { status: 'offline' } });
});

describe('DashboardActionsSheet', () => {
  it('contient les quatre accès rapides et les boutons de présence', async () => {
    render(<DashboardActionsSheet />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByText('Disponibilités')).toBeTruthy());
    expect(screen.getByText('Badges')).toBeTruthy();
    expect(screen.getByText('Revenus')).toBeTruthy();
    expect(screen.getByText('Messagerie')).toBeTruthy();
    expect(screen.getByText('Occupé')).toBeTruthy();
  });

  it('navigue vers l onglet Revenus', async () => {
    render(<DashboardActionsSheet />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByText('Revenus'));
    fireEvent.press(screen.getByText('Revenus'));

    expect(mockNavigate).toHaveBeenCalledWith('MainTabs', { screen: 'Earnings' });
  });

  it('affiche les KPIs', async () => {
    render(<DashboardActionsSheet />, { wrapper: makeWrapper() });
    await waitFor(() => expect(screen.getByText('Missions en attente')).toBeTruthy());
    expect(screen.getByText('Solde disponible')).toBeTruthy();
  });

  describe('requêtes gatées sur l ouverture du sheet', () => {
    // `@/ui`'s BottomSheet ne démonte jamais ses enfants (gorhom les repositionne juste hors
    // écran à index={-1}) et Task 10 monte DashboardActionsSheet en permanence pour le piloter
    // par ref : sans ce gating, l'inbox (polling 15s) et le solde partiraient en boucle pour un
    // sheet que personne ne regarde.
    it('ne déclenche aucune requête inbox/wallet tant que le sheet est fermé', async () => {
      render(<DashboardActionsSheet />, { wrapper: makeWrapper() });

      await waitFor(() => expect(screen.getByText('Disponibilités')).toBeTruthy());

      const urls = (apiMock.history.get ?? []).map(c => c.url);
      expect(urls).not.toContain('/provider/assignments/inbox');
      expect(urls).not.toContain('/provider/wallet/balance');
    });

    it("déclenche les requêtes inbox et wallet à l'ouverture (expand)", async () => {
      const sheetRef = React.createRef<any>();
      render(<DashboardActionsSheet ref={sheetRef} />, { wrapper: makeWrapper() });

      await waitFor(() => screen.getByText('Disponibilités'));

      act(() => {
        sheetRef.current?.expand();
      });

      await waitFor(() => {
        const urls = (apiMock.history.get ?? []).map(c => c.url);
        expect(urls).toContain('/provider/assignments/inbox');
        expect(urls).toContain('/provider/wallet/balance');
      });
    });

    it("une fermeture via onClose (pan-down) repasse le sheet à l'état fermé : une réouverture ultérieure re-déclenche une requête", async () => {
      const sheetRef = React.createRef<any>();
      render(<DashboardActionsSheet ref={sheetRef} />, { wrapper: makeWrapper() });

      await waitFor(() => screen.getByText('Disponibilités'));

      act(() => {
        sheetRef.current?.expand();
      });
      await waitFor(() => {
        const urls = (apiMock.history.get ?? []).map(c => c.url);
        expect(urls.filter(u => u === '/provider/assignments/inbox')).toHaveLength(1);
      });

      // Simule un pan-down-to-close : c'est gorhom qui ferme et appelle `onClose` lui-même — ce
      // chemin ne passe jamais par le `close()` exposé par la ref, contrairement à un appel
      // programmatique. C'est le chemin que les utilisateurs emprunteront le plus souvent.
      expect(mockOnCloseCapture.current).not.toBeNull();
      act(() => {
        mockOnCloseCapture.current?.();
      });

      // staleTime par défaut (0) : react-query refetch automatiquement une query qui redevient
      // `enabled` si sa donnée est stale. Une deuxième requête ici ne peut donc se produire que
      // si `onClose` a bien repassé `isOpen` à `false` entre-temps — sinon la query serait
      // restée `enabled` en continu et cette deuxième ouverture n'aurait rien de plus à faire.
      act(() => {
        sheetRef.current?.expand();
      });
      await waitFor(() => {
        const urls = (apiMock.history.get ?? []).map(c => c.url);
        expect(urls.filter(u => u === '/provider/assignments/inbox')).toHaveLength(2);
      });
    });
  });

  describe('fermeture avant navigation', () => {
    it('ferme le sheet avant de naviguer (action rapide « Badges »)', async () => {
      render(<DashboardActionsSheet />, { wrapper: makeWrapper() });

      await waitFor(() => screen.getByText('Badges'));
      fireEvent.press(screen.getByText('Badges'));

      expect(mockSheetClose).toHaveBeenCalled();
      expect(mockNavigate).toHaveBeenCalledWith('Badges');
      // L'ordre compte : fermer après avoir navigué laisserait le sheet ouvert par-dessus
      // l'écran de destination (ou encore déplié au retour sur le dashboard). Les `expect`
      // ci-dessus garantissent déjà que l'index 0 existe des deux côtés (noUncheckedIndexedAccess).
      const closeOrder = mockSheetClose.mock.invocationCallOrder[0] as number;
      const navigateOrder = mockNavigate.mock.invocationCallOrder[0] as number;
      expect(closeOrder).toBeLessThan(navigateOrder);
    });

    it('ferme le sheet avant de naviguer (action rapide « Disponibilités »)', async () => {
      render(<DashboardActionsSheet />, { wrapper: makeWrapper() });

      await waitFor(() => screen.getByText('Disponibilités'));
      fireEvent.press(screen.getByText('Disponibilités'));

      expect(mockSheetClose).toHaveBeenCalled();
      expect(mockNavigate).toHaveBeenCalledWith('Availability');
      const closeOrder = mockSheetClose.mock.invocationCallOrder[0] as number;
      const navigateOrder = mockNavigate.mock.invocationCallOrder[0] as number;
      expect(closeOrder).toBeLessThan(navigateOrder);
    });

    it('ferme le sheet avant de naviguer (action rapide « Messagerie »)', async () => {
      render(<DashboardActionsSheet />, { wrapper: makeWrapper() });

      await waitFor(() => screen.getByText('Messagerie'));
      fireEvent.press(screen.getByText('Messagerie'));

      expect(mockSheetClose).toHaveBeenCalled();
      expect(mockNavigate).toHaveBeenCalledWith('ProviderChatList');
      const closeOrder = mockSheetClose.mock.invocationCallOrder[0] as number;
      const navigateOrder = mockNavigate.mock.invocationCallOrder[0] as number;
      expect(closeOrder).toBeLessThan(navigateOrder);
    });

    it('ferme le sheet avant de naviguer (« Voir toutes les missions »)', async () => {
      render(<DashboardActionsSheet />, { wrapper: makeWrapper() });

      await waitFor(() => screen.getByText('Voir toutes les missions'));
      fireEvent.press(screen.getByText('Voir toutes les missions'));

      expect(mockSheetClose).toHaveBeenCalled();
      expect(mockNavigate).toHaveBeenCalledWith('MainTabs', { screen: 'Missions' });
      const closeOrder = mockSheetClose.mock.invocationCallOrder[0] as number;
      const navigateOrder = mockNavigate.mock.invocationCallOrder[0] as number;
      expect(closeOrder).toBeLessThan(navigateOrder);
    });
  });
});
