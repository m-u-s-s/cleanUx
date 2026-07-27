/**
 * Ce fichier complète DashboardActionsSheet.test.tsx sur un point que ce dernier laisse
 * volontairement dans l'angle mort : il mocke `@/ui`'s `BottomSheet` en simple `<View>`, donc
 * rien n'y prouve que la ref passée à `DashboardActionsSheet` atteint réellement l'instance
 * gorhom (expand()/close()). Une régression qui casserait le `forwardRef` — par ex. un wrapper
 * supplémentaire sans `forwardRef`, ou un oubli de propager `ref` vers `@/ui`'s `BottomSheet` —
 * passerait inaperçue là-bas.
 *
 * Ici, `@/ui` n'est PAS mocké : on utilise le vrai `BottomSheet` de `@/ui`, pour vérifier le
 * transport de la ref à travers ses deux niveaux (DashboardActionsSheet -> @/ui's BottomSheet ->
 * GorhomBottomSheet).
 *
 * En revanche `@gorhom/bottom-sheet` EST re-mocké ici, localement, au lieu de garder le mock
 * officiel `@gorhom/bottom-sheet/mock` que `moduleNameMapper` (jest.config.ts) applique au reste
 * de la suite. Diagnostic : ce mock officiel exporte `module.exports = { default: BottomSheet,
 * ... }` sans `__esModule: true`. L'interop CJS/ESM de Babel traite alors l'objet exporté ENTIER
 * comme le default (faute du marqueur), si bien qu'un `import GorhomBottomSheet from
 * '@gorhom/bottom-sheet'` — exactement ce que fait @/ui's BottomSheet.tsx — se retrouve à
 * pointer vers l'objet de module complet plutôt que vers la classe `BottomSheet`, et React
 * lève "Element type is invalid ... but got: object" au montage. Reproduit isolément (script
 * jetable important le mock officiel et loggant `typeof` du default import : "object", avec les
 * clés de tout le module) avant d'écrire ce contournement — voir task-9-report.md. C'est un
 * défaut d'outillage Jest pré-existant (le mock officiel du package, pas notre code, pas le
 * bundle Metro d'un vrai build), qui empêche aujourd'hui de monter le vrai @/ui's BottomSheet
 * dans n'importe quel test sans ce correctif local — ce qui explique et valide le pattern déjà
 * établi ailleurs dans la suite ("mocker @/ui's BottomSheet en conteneur simple").
 */
import React from 'react';
import { act, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';
import type GorhomBottomSheet from '@gorhom/bottom-sheet';

// Même raison qu'en DashboardActionsSheet.test.tsx : rendre la notification de React Query
// synchrone pour ne pas la laisser se déclencher hors d'un act() après le waitFor.
notifyManager.setScheduler((callback) => callback());

// Depuis le correctif de gating (voir DashboardActionsSheet.tsx), la ref exposée au parent n'est
// plus un pass-through direct vers l'instance gorhom : c'est un objet synthétique construit par
// useImperativeHandle, dont expand()/close() retiennent au passage `isOpen` avant de relayer
// l'appel à l'instance interne. On ne peut donc plus observer les jest.fn() directement sur
// `sheetRef.current` (ce ne sont plus eux). On les place à la place au niveau module, référencés
// par les méthodes de l'instance gorhom simulée ci-dessous, pour vérifier que la chaîne complète
// (ref exposée -> useImperativeHandle -> innerRef -> instance gorhom) transmet bien les appels.
// Préfixe `mock` obligatoire : les factories `jest.mock` sont hissées avant les `const` du fichier.
const mockGorhomExpand = jest.fn();
const mockGorhomClose = jest.fn();

// Contournement du bug d'interop décrit ci-dessus : mock local correctement marqué
// `__esModule: true`, dont le `default` est bien une classe/fonction — pas le module entier.
jest.mock('@gorhom/bottom-sheet', () => {
  const RN = require('react');
  class FakeGorhomBottomSheet extends RN.Component {
    expand = (...args: unknown[]) => mockGorhomExpand(...args);
    close = (...args: unknown[]) => mockGorhomClose(...args);
    snapToIndex = jest.fn();
    snapToPosition = jest.fn();
    collapse = jest.fn();
    forceClose = jest.fn();
    render() {
      return this.props.children;
    }
  }
  return {
    __esModule: true,
    default: FakeGorhomBottomSheet,
    BottomSheetView: ({ children }: any) => children,
    BottomSheetBackdrop: () => null,
  };
});

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
  mockGorhomExpand.mockClear();
  mockGorhomClose.mockClear();
  apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [] });
  apiMock.onGet('/provider/wallet/balance').reply(200, { available: 150, pending: 0, currency: 'EUR' });
  apiMock.onGet('/provider/presence-v2').reply(200, { data: { status: 'offline' } });
});

describe('DashboardActionsSheet — câblage de la ref gorhom', () => {
  it("transmet la ref jusqu'à l'instance BottomSheet : expand()/close() atteignent bien gorhom", async () => {
    const sheetRef = React.createRef<GorhomBottomSheet>();
    render(<DashboardActionsSheet ref={sheetRef} />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByText('Disponibilités')).toBeTruthy());

    // Si le forwardRef était cassé (perdu au niveau de DashboardActionsSheet ou de
    // @/ui's BottomSheet), sheetRef.current resterait null ici.
    expect(sheetRef.current).not.toBeNull();

    // sheetRef.current.expand()/.close() sont désormais l'objet synthétique de
    // useImperativeHandle (voir commentaire plus haut), pas directement les jest.fn() du mock
    // gorhom. Les appeler ici et vérifier que mockGorhomExpand/mockGorhomClose ont bien été
    // invoqués prouve que la chaîne complète — ref exposée -> useImperativeHandle -> innerRef ->
    // instance gorhom — transmet réellement les appels, pas seulement qu'un objet quelconque
    // expose ces deux noms de méthode.
    act(() => {
      sheetRef.current?.expand();
    });
    // expand() active isOpen -> useMissionInbox/useWalletBalance passent enabled:true et
    // partent chercher leurs données. Attendre que cette requête se résolve avant de continuer,
    // sinon sa résolution retombe après la fin du test, hors de tout act().
    await waitFor(() => {
      const urls = (apiMock.history.get ?? []).map(c => c.url);
      expect(urls).toContain('/provider/assignments/inbox');
    });
    act(() => {
      sheetRef.current?.close();
    });
    expect(mockGorhomExpand).toHaveBeenCalledTimes(1);
    expect(mockGorhomClose).toHaveBeenCalledTimes(1);
  });
});
