import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { ModuleHubScreen } from '../ModuleHubScreen';
import * as parity from '@/parity';

jest.mock('@/parity');

const navigate = jest.fn();
const navigation: any = { navigate };

// Same module, two delivery modes — nothing else changes between the two cases.
//
// L'exemple était `booking`, qui n'a plus d'écran natif : la réservation passe désormais par le
// moteur de commande en vue embarquée. On prend `tracking`, qui reste natif — sinon ce test
// prouverait seulement qu'un module sans correspondance retombe sur la vue embarquée, ce qui est
// l'inverse de ce qu'il doit démontrer.
const asWebview = [{ key: 'tracking', title: 'Suivi', icon: 'navigate-outline', path: '/dashboard/client/rendez-vous', mobile: 'webview' }];
const asNative = [{ key: 'tracking', title: 'Suivi', icon: 'navigate-outline', path: '/dashboard/client/rendez-vous', mobile: 'native' }];

describe('parity flag flip re-routes with no code change', () => {
  beforeEach(() => jest.clearAllMocks());

  it('webview flag routes to EmbeddedModule', async () => {
    (parity.fetchParityMap as jest.Mock).mockResolvedValue(asWebview);
    const { getByText } = render(<ModuleHubScreen navigation={navigation} />);
    await waitFor(() => getByText('Suivi'));
    fireEvent.press(getByText('Suivi'));
    expect(navigate).toHaveBeenCalledWith('EmbeddedModule', { path: '/dashboard/client/rendez-vous', title: 'Suivi' });
  });

  it('native flag routes to the native screen', async () => {
    (parity.fetchParityMap as jest.Mock).mockResolvedValue(asNative);
    const { getByText } = render(<ModuleHubScreen navigation={navigation} />);
    await waitFor(() => getByText('Suivi'));
    fireEvent.press(getByText('Suivi'));
    expect(navigate).toHaveBeenCalledWith('MissionTracking', undefined);
    expect(navigate).not.toHaveBeenCalledWith('EmbeddedModule', expect.anything());
  });
});
