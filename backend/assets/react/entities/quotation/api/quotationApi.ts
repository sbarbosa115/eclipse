import {API_BASE, apiGet, apiPost, apiPut, type Schema} from '@/shared/api';

export type Quotation = Schema<'QuotationOutput'>;
export type QuotationSummary = Schema<'QuotationSummaryOutput'>;
export type QuotationLine = Schema<'SalesInvoiceLineOutput'>;

/** On the wire (§4.7): an emitted quotation past its fecha de vencimiento reads as `expired`. */
export type QuotationStatus =
  'draft' | 'emitted' | 'accepted' | 'rejected' | 'expired' | 'voided';

export const QUOTATION_STATUSES: readonly QuotationStatus[] = [
  'draft',
  'emitted',
  'accepted',
  'rejected',
  'expired',
  'voided',
];

export interface QuotationPage {
  items: QuotationSummary[];
  total: number;
  page: number;
  per_page: number;
}

export interface QuotationFilters {
  q?: string;
  status?: string;
  /** YYYY-MM-DD, both included. */
  from?: string;
  to?: string;
  page?: number;
  per_page?: number;
}

/** The body of POST /quotations and PUT /quotations/{id}: the whole draft. */
export interface QuotationRequest {
  tercero_id: string;
  contact_id: string | null;
  responsible_id: string | null;
  issue_date: string;
  /** Fecha de vencimiento of the offer; null: 30 days after the issue date. */
  expiry_date: string | null;
  header: string | null;
  terms: string | null;
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
}

export function listQuotations(
  filters: QuotationFilters = {},
): Promise<QuotationPage> {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== '') params.set(key, String(value));
  }
  const query = params.toString();
  return apiGet(`/quotations${query ? `?${query}` : ''}`);
}

export function getQuotation(id: string): Promise<Quotation> {
  return apiGet(`/quotations/${id}`);
}

export function createQuotation(body: QuotationRequest): Promise<Quotation> {
  return apiPost('/quotations', body);
}

export function updateQuotation(
  id: string,
  body: QuotationRequest,
): Promise<Quotation> {
  return apiPut(`/quotations/${id}`, body);
}

/** Emitir, or Emitir y enviar (the PDF by e-mail to the client). */
export function emitQuotation(id: string, send = false): Promise<Quotation> {
  return apiPost(`/quotations/${id}/${send ? 'emit-and-send' : 'emit'}`, {});
}

export function sendQuotation(id: string): Promise<null> {
  return apiPost(`/quotations/${id}/send`, {});
}

export function acceptQuotation(id: string): Promise<Quotation> {
  return apiPost(`/quotations/${id}/accept`, {});
}

export function rejectQuotation(id: string): Promise<Quotation> {
  return apiPost(`/quotations/${id}/reject`, {});
}

export function voidQuotation(id: string, reason: string): Promise<Quotation> {
  return apiPost(`/quotations/${id}/void`, {reason});
}

/** Makes the draft sales invoice (once): the answer is the quotation, accepted, with `converted_invoice_id`. */
export function convertQuotation(id: string): Promise<Quotation> {
  return apiPost(`/quotations/${id}/convert`, {});
}

export function duplicateQuotation(id: string): Promise<Quotation> {
  return apiPost(`/quotations/${id}/duplicate`, {});
}

/** Where the browser downloads the PDF from (same origin, the session cookie goes with it). */
export function quotationPdfUrl(id: string): string {
  return `${API_BASE}/quotations/${id}/pdf`;
}
