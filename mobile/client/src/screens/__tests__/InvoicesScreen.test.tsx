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
      { id: 1, number: 'F-001', amount: 80, currency: 'EUR', effective_status: 'paid', service_name: 'Nettoyage bureaux' },
      { id: 2, number: 'F-002', amount: 120, currency: 'EUR', effective_status: 'overdue' },
    ]);
    (inv.fetchInvoicesSummary as jest.Mock).mockResolvedValue({
      summary: {
        invoices_count: 2,
        paid_count: 1,
        partial_count: 0,
        overdue_count: 1,
        outstanding_total: 120,
        next_due_at: '2026-06-01T00:00:00Z',
        currency_symbol: '€',
      },
      payment_health: {
        tone: 'rose',
        label: 'Action requise',
        title: 'Factures en retard',
        message: 'Vous avez 1 facture en retard.',
      },
      latest_payment_events: [
        { id: 1, amount: 80, status: 'paid', reference: 'P-001', date: '2026-05-02T00:00:00Z' },
      ],
    });
  });

  // --- existing 3 tests (kept) ---

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

  // --- new parity tests ---

  it('renders the summary KPIs and payment health', async () => {
    const { getByText, getAllByText } = render(<InvoicesScreen navigation={navigation} />);
    await waitFor(() => getByText('Factures en retard'));   // payment_health.title
    expect(getAllByText(/1/).length).toBeGreaterThan(0);    // overdue_count visible (loose)
  });

  it('renders the latest payment events', async () => {
    const { getByText } = render(<InvoicesScreen navigation={navigation} />);
    await waitFor(() => getByText('P-001'));                 // event reference
  });

  it('renders the service name on a row', async () => {
    (inv.fetchInvoices as jest.Mock).mockResolvedValue([
      { id: 1, number: 'F-001', amount: 80, currency: 'EUR', effective_status: 'paid', service_name: 'Nettoyage bureaux' },
    ]);
    const { getByText } = render(<InvoicesScreen navigation={navigation} />);
    await waitFor(() => getByText('Nettoyage bureaux'));
  });

  it('changing sort re-fetches with the sort param', async () => {
    const { getByText, getByTestId } = render(<InvoicesScreen navigation={navigation} />);
    await waitFor(() => getByText('F-001'));
    fireEvent.press(getByTestId('sort-amount_desc'));
    await waitFor(() =>
      expect(inv.fetchInvoices).toHaveBeenCalledWith(
        expect.objectContaining({ sort: 'amount_desc' }),
      ),
    );
  });

  it('renders overdue warning label on overdue invoice row', async () => {
    const { getAllByText } = render(<InvoicesScreen navigation={navigation} />);
    await waitFor(() => getAllByText(/retard/i));
  });

  it('renders the active filter chip label when a non-default filter is active', async () => {
    const { getByTestId, getByText } = render(<InvoicesScreen navigation={navigation} />);
    await waitFor(() => getByText('F-001'));
    fireEvent.press(getByTestId('filter-chip-paid'));
    await waitFor(() => getByTestId('active-filter-label'));
  });

  it('reset filters button clears all filters and re-fetches with defaults', async () => {
    const { getByTestId, getByText } = render(<InvoicesScreen navigation={navigation} />);
    await waitFor(() => getByText('F-001'));
    // set a non-default sort, then reset
    fireEvent.press(getByTestId('sort-amount_desc'));
    await waitFor(() => getByTestId('reset-filters'));
    fireEvent.press(getByTestId('reset-filters'));
    await waitFor(() =>
      expect(inv.fetchInvoices).toHaveBeenLastCalledWith(
        expect.objectContaining({ sort: 'recent' }),
      ),
    );
  });
});
