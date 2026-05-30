import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { InvoiceDetailScreen } from '../InvoiceDetailScreen';
import * as inv from '@/finance/useInvoices';

jest.mock('@/finance/useInvoices');

const route: any = { params: { id: 7 } };
const navigation: any = { setOptions: jest.fn() };

describe('InvoiceDetailScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    (inv.fetchInvoice as jest.Mock).mockResolvedValue({
      id: 7, number: 'F-007', amount: 120, balance_due: 40, currency: 'EUR',
      effective_status: 'partial', issued_at: '2026-05-01T00:00:00Z', due_at: '2026-05-15T00:00:00Z',
      payments: [{ id: 1, amount: 80, status: 'paid', paid_at: '2026-05-02T00:00:00Z' }],
      reminders: [{ id: 1, type: 'gentle', sent_at: '2026-05-10T00:00:00Z' }],
    });
    (inv.invoicePdfUrl as jest.Mock).mockReturnValue('/client/invoices/7/pdf');
  });

  it('renders the invoice detail', async () => {
    const { getByText } = render(<InvoiceDetailScreen route={route} navigation={navigation} />);
    await waitFor(() => getByText('F-007'));
    expect(inv.fetchInvoice).toHaveBeenCalledWith(7);
  });

  it('has a PDF action that resolves the pdf url', async () => {
    const { getByText } = render(<InvoiceDetailScreen route={route} navigation={navigation} />);
    await waitFor(() => getByText('F-007'));
    fireEvent.press(getByText(/PDF/i));
    expect(inv.invoicePdfUrl).toHaveBeenCalledWith(7);
  });
});
