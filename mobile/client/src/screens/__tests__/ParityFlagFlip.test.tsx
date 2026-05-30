import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { ModuleHubScreen } from '../ModuleHubScreen';
import * as parity from '@/parity';

jest.mock('@/parity');

const navigate = jest.fn();
const navigation: any = { navigate };

// Same module, two delivery modes — nothing else changes between the two cases.
const asWebview = [{ key: 'booking', title: 'Réserver', icon: 'calendar-outline', path: '/client/bookings/new', mobile: 'webview' }];
const asNative = [{ key: 'booking', title: 'Réserver', icon: 'calendar-outline', path: '/client/bookings/new', mobile: 'native' }];

describe('parity flag flip re-routes with no code change', () => {
  beforeEach(() => jest.clearAllMocks());

  it('webview flag routes to EmbeddedModule', async () => {
    (parity.fetchParityMap as jest.Mock).mockResolvedValue(asWebview);
    const { getByText } = render(<ModuleHubScreen navigation={navigation} />);
    await waitFor(() => getByText('Réserver'));
    fireEvent.press(getByText('Réserver'));
    expect(navigate).toHaveBeenCalledWith('EmbeddedModule', { path: '/client/bookings/new', title: 'Réserver' });
  });

  it('native flag routes to the native screen', async () => {
    (parity.fetchParityMap as jest.Mock).mockResolvedValue(asNative);
    const { getByText } = render(<ModuleHubScreen navigation={navigation} />);
    await waitFor(() => getByText('Réserver'));
    fireEvent.press(getByText('Réserver'));
    expect(navigate).toHaveBeenCalledWith('BookingWizard', undefined);
    expect(navigate).not.toHaveBeenCalledWith('EmbeddedModule', expect.anything());
  });
});
