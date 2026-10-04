import {API_BASE, apiGet, apiPost, apiPut, type Schema} from '@/shared/api';

export type SalesInvoice = Schema<'SalesInvoiceOutput'>;
export type SalesInvoiceSummary = Schema<'SalesInvoiceSummaryOutput'>;
export type SalesInvoiceLine = Schema<'SalesInvoiceLineOutput'>;
export type SalesInvoicePayment = Schema<'SalesInvoicePaymentOutput'>;
export type InvoiceStatus =
  'draft' | 'emitted' | 'partially_paid' | 'paid' | 'voided';

export const INVOICE_STATUSES: readonly InvoiceStatus[] = [
  'draft',
  'emitted',
  'partially_paid',
  'paid',
  'voided',
];

export interface SalesInvoicePage {
  items: SalesInvoiceSummary[];
  total: number;
  page: number;
  per_page: number;
}

export interface SalesInvoiceFilters {
  q?: string;
  status?: string;
  /** YYYY-MM-DD, both included. */
  from?: string;
  to?: string;
  page?: number;
  per_page?: number;
}

/** The body of POST /sales-invoices and PUT /sales-invoices/{id}: the whole draft. */
export interface SalesInvoiceRequest {
  tercero_id: string;
  contact_id: string | null;
  seller_id: string | null;
  issue_date: string;
  notes: string | null;
  lines: {
    product_id: string | null;
    description: string;
    quantity: string;
    unit_price: string;
    discount: string;
    charge_tax_id: string | null;
    withholding_tax_id: string | null;
  }[];
  payments: {
    payment_method_id: string;
    amount: string;
    due_date: string | null;
  }[];
}

/** The invoicing resolution and its state today (§4.1): the prefix for Tipo, the warning on a new invoice. */
export type ResolutionSettings = Schema<'ResolutionSettingsOutput'>;

export function listSalesInvoices(
  filters: SalesInvoiceFilters = {},
): Promise<SalesInvoicePage> {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== '') params.set(key, String(value));
  }
  const query = params.toString();
  return apiGet(`/sales-invoices${query ? `?${query}` : ''}`);
}

export function getSalesInvoice(id: string): Promise<SalesInvoice> {
  return apiGet(`/sales-invoices/${id}`);
}

export function createSalesInvoice(
  body: SalesInvoiceRequest,
): Promise<SalesInvoice> {
  return apiPost('/sales-invoices', body);
}

export function updateSalesInvoice(
  id: string,
  body: SalesInvoiceRequest,
): Promise<SalesInvoice> {
  return apiPut(`/sales-invoices/${id}`, body);
}

/** Emitir, or Emitir y enviar (the PDF by e-mail to the client). */
export function emitSalesInvoice(
  id: string,
  send = false,
): Promise<SalesInvoice> {
  return apiPost(
    `/sales-invoices/${id}/${send ? 'emit-and-send' : 'emit'}`,
    {},
  );
}

export function voidSalesInvoice(
  id: string,
  reason: string,
): Promise<SalesInvoice> {
  return apiPost(`/sales-invoices/${id}/void`, {reason});
}

export function duplicateSalesInvoice(id: string): Promise<SalesInvoice> {
  return apiPost(`/sales-invoices/${id}/duplicate`, {});
}

export function sendSalesInvoice(id: string): Promise<null> {
  return apiPost(`/sales-invoices/${id}/send`, {});
}

/** Where the browser downloads the PDF from (same origin, the session cookie goes with it). */
export function salesInvoicePdfUrl(id: string): string {
  return `${API_BASE}/sales-invoices/${id}/pdf`;
}

export function getResolutionSettings(): Promise<ResolutionSettings> {
  return apiGet('/company/resolution');
}
