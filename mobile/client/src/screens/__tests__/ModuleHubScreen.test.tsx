import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { ModuleHubScreen } from '../ModuleHubScreen';
import * as parity from '@/parity';

jest.mock('@/parity');

const navigate = jest.fn();
const navigation: any = { navigate };

const MODULES = [
  // `tracking` plutôt que `booking` : ce dernier n'a plus d'écran natif depuis que la réservation
  // passe par le moteur de commande en vue embarquée.
  { key: 'tracking', title: 'Suivi', icon: 'navigate-outline', path: '/dashboard/client/rendez-vous', mobile: 'native' },
  { key: 'accounting', title: 'Comptabilité', icon: 'document-text-outline', path: '/admin/accounting', mobile: 'webview' },
];

describe('ModuleHubScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    (parity.fetchParityMap as jest.Mock).mockResolvedValue(MODULES);
  });

  it('routes a webview module to the EmbeddedModule screen with its path', async () => {
    const { getByText } = render(<ModuleHubScreen navigation={navigation} />);
    await waitFor(() => getByText('Comptabilité'));

    fireEvent.press(getByText('Comptabilité'));

    expect(navigate).toHaveBeenCalledWith('EmbeddedModule', {
      path: '/admin/accounting',
      title: 'Comptabilité',
    });
  });

  it('routes a native module to its native screen, not the WebView', async () => {
    const { getByText } = render(<ModuleHubScreen navigation={navigation} />);
    await waitFor(() => getByText('Suivi'));

    fireEvent.press(getByText('Suivi'));

    expect(navigate).not.toHaveBeenCalledWith('EmbeddedModule', expect.anything());
    expect(navigate).toHaveBeenCalledWith('MissionTracking', undefined);
  });
});
