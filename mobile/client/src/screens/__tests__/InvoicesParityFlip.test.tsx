import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { ModuleHubScreen } from '../ModuleHubScreen';
import * as parity from '@/parity';

jest.mock('@/parity');
const navigate = jest.fn();
const navigation: any = { navigate };
const invoices = (mobile: string) => ([{ key: 'invoices', title: 'Factures', icon: 'receipt-outline', path: '/dashboard/client/finance', mobile }]);

describe('invoices parity flip', () => {
  beforeEach(() => jest.clearAllMocks());

  it('native flag routes to the native Invoices screen', async () => {
    (parity.fetchParityMap as jest.Mock).mockResolvedValue(invoices('native'));
    const { getByText } = render(<ModuleHubScreen navigation={navigation} />);
    await waitFor(() => getByText('Factures'));
    fireEvent.press(getByText('Factures'));
    expect(navigate).toHaveBeenCalledWith('Invoices', undefined);
    expect(navigate).not.toHaveBeenCalledWith('EmbeddedModule', expect.anything());
  });

  it('rollback: webview flag re-routes to EmbeddedModule', async () => {
    (parity.fetchParityMap as jest.Mock).mockResolvedValue(invoices('webview'));
    const { getByText } = render(<ModuleHubScreen navigation={navigation} />);
    await waitFor(() => getByText('Factures'));
    fireEvent.press(getByText('Factures'));
    expect(navigate).toHaveBeenCalledWith('EmbeddedModule', { path: '/dashboard/client/finance', title: 'Factures' });
  });
});
