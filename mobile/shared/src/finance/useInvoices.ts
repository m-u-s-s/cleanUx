import { apiClient } from '@/api';

export interface InvoicePayment { id: number; amount: number; status?: string; paid_at?: string | null }
export interface InvoiceReminder { id: number; type?: string; sent_at?: string | null }

export interface Invoice {
  id: number;
  number: string;
  amount: number;
  balance_due?: number;
  currency: string;
  status?: string;
  effective_status: string;
  issued_at?: string | null;
  due_at?: string | null;
  payments?: InvoicePayment[];
  reminders?: InvoiceReminder[];
}

export interface InvoiceFilters { status?: string; search?: string; sort?: string }

export async function fetchInvoices(filters: InvoiceFilters = {}): Promise<Invoice[]> {
  const params: Record<string, string> = {};
  if (filters.status && filters.status !== 'all') params.status = filters.status;
  if (filters.search) params.search = filters.search;
  if (filters.sort) params.sort = filters.sort;
  const res = await apiClient.get('/client/invoices', { params });
  return res.data.data as Invoice[];
}

export async function fetchInvoice(id: number): Promise<Invoice> {
  const res = await apiClient.get(`/client/invoices/${id}`);
  return res.data.data as Invoice;
}

/** Relative API path for the invoice PDF (download handled by the screen via apiClient baseURL + auth). */
export function invoicePdfUrl(id: number): string {
  return `/client/invoices/${id}/pdf`;
}
