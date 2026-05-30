import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { InvoicesScreen } from '../InvoicesScreen';
import * as inv from '@/finance/useInvoices';

jest.mock('@/finance/useInvoices');

const navigation: any = { navigate: jest.fn() };

describe('InvoicesScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    (inv.fetchInvoices as jest.Mock).mockResolvedValue([
      { id: 1, number: 'F-001', amount: 80, currency: 'EUR', effective_status: 'paid' },
      { id: 2, number: 'F-002', amount: 120, currency: 'EUR', effective_status: 'overdue' },
    ]);
  });

  it('lists the client invoices', async () => {
    const { getByText } = render(<InvoicesScreen navigation={navigation} />);
    await waitFor(() => getByText('F-001'));
    getByText('F-002');
  });

  it('opens an invoice detail on tap', async () => {
    const { getByText } = render(<InvoicesScreen navigation={navigation} />);
    await waitFor(() => getByText('F-001'));
    fireEvent.press(getByText('F-001'));
    expect(navigation.navigate).toHaveBeenCalledWith('InvoiceDetail', { id: 1 });
  });

  it('shows an empty state when there are no invoices', async () => {
    (inv.fetchInvoices as jest.Mock).mockResolvedValue([]);
    const { getByTestId } = render(<InvoicesScreen navigation={navigation} />);
    await waitFor(() => getByTestId('invoices-empty'));
  });
});
