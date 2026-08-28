import React from 'react';
import { render, waitFor } from '@testing-library/react-native';

// Mock push hooks that call useNavigation() unconditionally inside AppInner,
// before NavigationContainer has been rendered (showOnboarding state is null).
jest.mock('@/push', () => ({
  setupForegroundNotifications: jest.fn(),
  useRegisterPushToken: jest.fn(),
  useNotificationRouting: jest.fn(),
}));

// Mock sentry init to avoid native module errors in test
jest.mock('@/sentry/init', () => ({}));

import App from '../App';

// La presentation de premiere ouverture est declaree DEJA VUE : ces tests portent sur la
// navigation d'un utilisateur qui revient, pas sur le carrousel d'accueil.
jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue('true'),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

describe('App', () => {
  it('renders without crashing', async () => {
    const { getByTestId } = render(<App />);
    await waitFor(() => expect(getByTestId('root-navigator')).toBeTruthy());
  });
});
