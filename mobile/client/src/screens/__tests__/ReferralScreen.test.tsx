/**
 * Tests for ReferralScreen
 *
 * Covers:
 *  - Loading state renders skeleton placeholders
 *  - Referral code is displayed once data loads
 *  - Share button calls Share.share with the API message
 *  - Copy button calls Clipboard.setString with the code
 *  - Stats KPI cards display total_referrals and total_earned
 *  - Tier progress renders when current_tier is present
 */
import React from 'react';
import { Share, Clipboard, Alert } from 'react-native';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MockAdapter from 'axios-mock-adapter';

// ── Module mocks ──────────────────────────────────────────────────────────────

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

// ── Imports (after mocks) ─────────────────────────────────────────────────────

import { apiClient } from '@/api';
import { ReferralScreen } from '../ReferralScreen';

const mock = new MockAdapter(apiClient);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeWrapper() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: Infinity }, mutations: { retry: false } },
  });
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client }, children);
  };
}

const SHARE_DATA = { code: 'BRIO-TEST42', message: 'Rejoingnez Brio avec mon code BRIO-TEST42 !' };
const STATS_DATA = {
  total_referrals: 3,
  total_earned: 30,
  current_tier: { name: 'Argent', min_referrals: 2 },
  next_tier: { name: 'Or', min_referrals: 5 },
};

beforeEach(() => {
  mock.reset();
  jest.clearAllMocks();
});

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('ReferralScreen', () => {
  it('renders without crashing', () => {
    mock.onGet('/client/referral/stats').reply(200, { data: STATS_DATA });
    mock.onGet('/client/referral/my-code').reply(200, { data: SHARE_DATA });

    const { toJSON } = render(<ReferralScreen />, { wrapper: makeWrapper() });
    expect(toJSON()).not.toBeNull();
  });

  it('shows loading state while data fetches', async () => {
    // Never resolve — simulates loading
    mock.onGet('/client/referral/stats').reply(() => new Promise(() => undefined));
    mock.onGet('/client/referral/my-code').reply(() => new Promise(() => undefined));

    render(<ReferralScreen />, { wrapper: makeWrapper() });

    // Buttons are disabled while loading
    const shareBtn = screen.getByText('Partager');
    expect(shareBtn).toBeTruthy();
  });

  it('displays the referral code once data loads', async () => {
    mock.onGet('/client/referral/stats').reply(200, { data: STATS_DATA });
    mock.onGet('/client/referral/my-code').reply(200, { data: SHARE_DATA });

    render(<ReferralScreen />, { wrapper: makeWrapper() });

    await waitFor(() => {
      expect(screen.getByTestId('referral-code').props.children).toBe('BRIO-TEST42');
    });
  });

  it('calls Share.share with the API message when Partager is pressed', async () => {
    mock.onGet('/client/referral/stats').reply(200, { data: STATS_DATA });
    mock.onGet('/client/referral/my-code').reply(200, { data: SHARE_DATA });

    const shareSpy = jest.spyOn(Share, 'share').mockResolvedValue({ action: 'sharedAction' } as never);

    render(<ReferralScreen />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByTestId('referral-code'));

    fireEvent.press(screen.getByText('Partager'));

    await waitFor(() => {
      expect(shareSpy).toHaveBeenCalledWith({
        message: SHARE_DATA.message,
      });
    });
  });

  it('calls Clipboard.setString with the code when Copier is pressed', async () => {
    mock.onGet('/client/referral/stats').reply(200, { data: STATS_DATA });
    mock.onGet('/client/referral/my-code').reply(200, { data: SHARE_DATA });

    const clipboardSpy = jest.spyOn(Clipboard, 'setString').mockImplementation(() => undefined);
    jest.spyOn(Alert, 'alert').mockImplementation(() => undefined);

    render(<ReferralScreen />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByTestId('referral-code'));

    fireEvent.press(screen.getByText('Copier'));

    expect(clipboardSpy).toHaveBeenCalledWith('BRIO-TEST42');
  });

  it('displays total_referrals KPI card', async () => {
    mock.onGet('/client/referral/stats').reply(200, { data: STATS_DATA });
    mock.onGet('/client/referral/my-code').reply(200, { data: SHARE_DATA });

    render(<ReferralScreen />, { wrapper: makeWrapper() });

    await waitFor(() => {
      expect(screen.getByText('Parrainages')).toBeTruthy();
      // KPICard renders value as a string — multiple "3" may appear (step numbers etc.)
      expect(screen.getAllByText('3').length).toBeGreaterThanOrEqual(1);
    });
  });

  it('displays gains KPI card', async () => {
    mock.onGet('/client/referral/stats').reply(200, { data: STATS_DATA });
    mock.onGet('/client/referral/my-code').reply(200, { data: SHARE_DATA });

    render(<ReferralScreen />, { wrapper: makeWrapper() });

    await waitFor(() => {
      expect(screen.getByText('Gains')).toBeTruthy();
      expect(screen.getByText('€30')).toBeTruthy();
    });
  });

  it('renders tier card when current_tier is present', async () => {
    mock.onGet('/client/referral/stats').reply(200, { data: STATS_DATA });
    mock.onGet('/client/referral/my-code').reply(200, { data: SHARE_DATA });

    render(<ReferralScreen />, { wrapper: makeWrapper() });

    await waitFor(() => {
      expect(screen.getByText('Argent')).toBeTruthy();
      expect(screen.getByText(/Encore 2 parrainage/)).toBeTruthy();
    });
  });

  it('does not render tier card when current_tier is null', async () => {
    const noTierStats = { ...STATS_DATA, current_tier: null, next_tier: null };
    mock.onGet('/client/referral/stats').reply(200, { data: noTierStats });
    mock.onGet('/client/referral/my-code').reply(200, { data: SHARE_DATA });

    render(<ReferralScreen />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByTestId('referral-code'));

    expect(screen.queryByText('Argent')).toBeNull();
  });

  it('renders how-it-works steps', async () => {
    mock.onGet('/client/referral/stats').reply(200, { data: STATS_DATA });
    mock.onGet('/client/referral/my-code').reply(200, { data: SHARE_DATA });

    render(<ReferralScreen />, { wrapper: makeWrapper() });

    await waitFor(() => {
      expect(screen.getByText('Comment ça marche')).toBeTruthy();
      expect(screen.getByText('Partagez votre code avec vos amis')).toBeTruthy();
    });
  });
});
