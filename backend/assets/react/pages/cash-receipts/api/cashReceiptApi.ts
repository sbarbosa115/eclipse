import {API_BASE, apiGet, apiPost, type Schema} from '@/shared/api';

export type CashReceipt = Schema<'CashReceiptOutput'>;
export type CashReceiptSummary = Schema<'CashReceiptSummaryOutput'>;
export type OpenReceivable = Schema<'OpenReceivableOutput'>;
export type PaymentMethod = Schema<'PaymentMethodOutput'>;
export type ReceiptStatus = 'emitted' | 'voided';

export const RECEIPT_STATUSES: readonly ReceiptStatus[] = ['emitted', 'voided'];

export interface CashReceiptPage {
  items: CashReceiptSummary[];
  total: number;
  page: number;
  per_page: number;
}

export interface CashReceiptFilters {
  q?: string;
  status?: string;
  /** YYYY-MM-DD, both included. */
  from?: string;
  to?: string;
  page?: number;
  per_page?: number;
}

/** The body of POST /cash-receipts: saving emits it; `send` is *Guardar y enviar por mail*. */
export interface CashReceiptRequest {
  tercero_id: string;
  receipt_date: string;
  payment_method_id: string;
  amount: string;
  notes: string | null;
  allocations: {receivable_id: string; amount: string}[];
  send: boolean;
}

export function listCashReceipts(
  filters: CashReceiptFilters = {},
): Promise<CashReceiptPage> {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== '') params.set(key, String(value));
  }
  const query = params.toString();
  return apiGet(`/cash-receipts${query ? `?${query}` : ''}`);
}

export function getCashReceipt(id: string): Promise<CashReceipt> {
  return apiGet(`/cash-receipts/${id}`);
}

export function createCashReceipt(
  body: CashReceiptRequest,
): Promise<CashReceipt> {
  return apiPost('/cash-receipts', body);
}

export function voidCashReceipt(
  id: string,
  reason: string,
): Promise<CashReceipt> {
  return apiPost(`/cash-receipts/${id}/void`, {reason});
}

export function sendCashReceipt(id: string): Promise<null> {
  return apiPost(`/cash-receipts/${id}/send`, {});
}

/** Where the browser downloads the PDF from (same origin, the session cookie goes with it). */
export function cashReceiptPdfUrl(id: string): string {
  return `${API_BASE}/cash-receipts/${id}/pdf`;
}

/** What the client still owes, the oldest due first (§4.9). */
export async function listOpenReceivables(
  terceroId: string,
): Promise<OpenReceivable[]> {
  return (
    await apiGet<{items: OpenReceivable[]}>(
      `/cash-receipts/open-receivables?tercero_id=${encodeURIComponent(terceroId)}`,
    )
  ).items;
}

/** *Dónde ingresa el dinero*: the company's active contado methods. */
export async function listCashMethods(): Promise<PaymentMethod[]> {
  return (
    await apiGet<{items: PaymentMethod[]}>('/payment-methods')
  ).items.filter((method) => method.kind === 'cash' && method.active);
}
