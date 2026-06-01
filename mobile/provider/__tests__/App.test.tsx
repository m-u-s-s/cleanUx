import React from 'react';
import { render, waitFor } from '@testing-library/react-native';

// Mock push hooks that call useNavigation() unconditionally inside AppInner,
// before NavigationContainer has been rendered (showWalkthrough state is null).
jest.mock('@/push', () => ({
  setupForegroundNotifications: jest.fn(),
  useRegisterPushToken: jest.fn(),
  useNotificationRouting: jest.fn(),
}));

// Mock sentry init to avoid native module errors in test
jest.mock('@/sentry/init', () => ({}));

import App from '../App';

describe('App', () => {
  it('renders without crashing', async () => {
    const { getByTestId } = render(<App />);
    await waitFor(() => expect(getByTestId('root-navigator')).toBeTruthy());
  });
});
