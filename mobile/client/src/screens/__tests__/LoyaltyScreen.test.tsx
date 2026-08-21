/**
 * Tests for LoyaltyScreen — redemption action (was a stub: onPress={() => {}}).
 */
import React from 'react';
import { Alert } from 'react-native';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MockAdapter from 'axios-mock-adapter';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true, isInternetReachable: true }),
  default: {
    addEventListener: jest.fn(() => () => undefined),
    fetch: jest.fn().mockResolvedValue({ isConnected: true, isInternetReachable: true }),
  },
}));

import { apiClient } from '@/api';
import { LoyaltyScreen } from '../LoyaltyScreen';

const mock = new MockAdapter(apiClient);

function makeWrapper() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: Infinity }, mutations: { retry: false } },
  });
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client }, children);
  };
}

/*
 * LA FORME DU SERVEUR, et non celle qu'on aurait aimée.
 *
 * Cette fixture donnait `tier: 'gold'`, une chaîne. L'API rend un objet. Le test passait donc au
 * vert sur un contrat que personne ne tenait, pendant que l'écran tombait à l'ouverture.
 */
const ACCOUNT = {
  tier: { slug: 'gold', name: 'Or' },
  total_points: 800,
  redeemable_points: 500,
  period_points: 120,
};
const REWARDS = [{ id: 7, name: 'Bon 10€', type: 'discount_code', points_cost: 100 }];

beforeEach(() => {
  mock.reset();
  jest.spyOn(Alert, 'alert').mockImplementation(() => undefined);
});

test('redeeming a reward posts to the redeem endpoint', async () => {
  mock.onGet('/client/loyalty/me').reply(200, ACCOUNT);
  mock.onGet('/client/loyalty/rewards').reply(200, { data: REWARDS });
  mock.onPost('/client/loyalty/rewards/redeem').reply(201, {
    data: { id: 1, code: 'R-1', status: 'pending', voucher_code: 'V-1', points_spent: 100 },
  });

  render(<LoyaltyScreen />, { wrapper: makeWrapper() });

  const button = await screen.findByText('Échanger');
  fireEvent.press(button);

  // Une confirmation Alert s'ouvre — on déclenche son bouton "Échanger".
  const confirm = (Alert.alert as jest.Mock).mock.calls.at(-1)?.[2]?.find(
    (b: { text: string }) => b.text === 'Échanger',
  );
  expect(confirm).toBeTruthy();
  confirm.onPress();

  await waitFor(() => {
    const posted = mock.history.post.find(r => r.url === '/client/loyalty/rewards/redeem');
    expect(posted).toBeTruthy();
    expect(JSON.parse(posted!.data)).toMatchObject({ reward_id: 7 });
  });
});
