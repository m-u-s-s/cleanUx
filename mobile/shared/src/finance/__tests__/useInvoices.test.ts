import { fetchInvoices, fetchInvoice, invoicePdfUrl } from '../useInvoices';
import { apiClient } from '@/api';

jest.mock('@/api', () => ({ apiClient: { get: jest.fn() }, ApiError: class extends Error {} }));

describe('invoices data layer', () => {
  beforeEach(() => jest.clearAllMocks());

  it('fetchInvoices returns the data array and omits empty filters', async () => {
    (apiClient.get as jest.Mock).mockResolvedValue({ data: { data: [{ id: 1, number: 'F-1', amount: 80, currency: 'EUR', effective_status: 'paid' }] } });
    const result = await fetchInvoices();
    expect(apiClient.get).toHaveBeenCalledWith('/client/invoices', { params: {} });
    expect(result[0].number).toBe('F-1');
  });

  it('fetchInvoices passes status/search/sort filters', async () => {
    (apiClient.get as jest.Mock).mockResolvedValue({ data: { data: [] } });
    await fetchInvoices({ status: 'overdue', search: 'F-1', sort: 'amount_desc' });
    expect(apiClient.get).toHaveBeenCalledWith('/client/invoices', { params: { status: 'overdue', search: 'F-1', sort: 'amount_desc' } });
  });

  it('fetchInvoices drops status="all"', async () => {
    (apiClient.get as jest.Mock).mockResolvedValue({ data: { data: [] } });
    await fetchInvoices({ status: 'all' });
    expect(apiClient.get).toHaveBeenCalledWith('/client/invoices', { params: {} });
  });

  it('fetchInvoice returns a single invoice', async () => {
    (apiClient.get as jest.Mock).mockResolvedValue({ data: { data: { id: 7, number: 'F-7', payments: [], reminders: [] } } });
    const inv = await fetchInvoice(7);
    expect(apiClient.get).toHaveBeenCalledWith('/client/invoices/7');
    expect(inv.id).toBe(7);
  });

  it('invoicePdfUrl builds the pdf path', () => {
    expect(invoicePdfUrl(7)).toBe('/client/invoices/7/pdf');
  });
});
