import {API_BASE, apiGet, apiPost, type Schema} from '@/shared/api';

export type SupplierPayment = Schema<'SupplierPaymentOutput'>;
export type SupplierPaymentSummary = Schema<'SupplierPaymentSummaryOutput'>;
export type OpenPayable = Schema<'OpenPayableOutput'>;
export type PaymentMethod = Schema<'PaymentMethodOutput'>;
export type PaymentStatus = 'emitted' | 'voided';

export const PAYMENT_STATUSES: readonly PaymentStatus[] = ['emitted', 'voided'];

export interface SupplierPaymentPage {
  items: SupplierPaymentSummary[];
  total: number;
  page: number;
  per_page: number;
}

export interface SupplierPaymentFilters {
  q?: string;
  status?: string;
  /** YYYY-MM-DD, both included. */
  from?: string;
  to?: string;
  page?: number;
  per_page?: number;
}

/** The body of POST /supplier-payments: saving emits it; `send` is *Guardar y enviar*. */
export interface SupplierPaymentRequest {
  tercero_id: string;
  receipt_date: string;
  payment_method_id: string;
  amount: string;
  notes: string | null;
  allocations: {payable_id: string; amount: string}[];
  send: boolean;
}

export function listSupplierPayments(
  filters: SupplierPaymentFilters = {},
): Promise<SupplierPaymentPage> {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== '') params.set(key, String(value));
  }
  const query = params.toString();
  return apiGet(`/supplier-payments${query ? `?${query}` : ''}`);
}

export function getSupplierPayment(id: string): Promise<SupplierPayment> {
  return apiGet(`/supplier-payments/${id}`);
}

export function createSupplierPayment(
  body: SupplierPaymentRequest,
): Promise<SupplierPayment> {
  return apiPost('/supplier-payments', body);
}

export function voidSupplierPayment(
  id: string,
  reason: string,
): Promise<SupplierPayment> {
  return apiPost(`/supplier-payments/${id}/void`, {reason});
}

export function sendSupplierPayment(id: string): Promise<null> {
  return apiPost(`/supplier-payments/${id}/send`, {});
}

/** Where the browser downloads the PDF from (same origin, the session cookie goes with it). */
export function supplierPaymentPdfUrl(id: string): string {
  return `${API_BASE}/supplier-payments/${id}/pdf`;
}

/** What the company still owes the supplier, the oldest due first (§4.11). */
export async function listOpenPayables(
  terceroId: string,
): Promise<OpenPayable[]> {
  return (
    await apiGet<{items: OpenPayable[]}>(
      `/supplier-payments/open-payables?tercero_id=${encodeURIComponent(terceroId)}`,
    )
  ).items;
}

/** *De dónde sale el dinero*: the company's active contado methods. */
export async function listCashMethods(): Promise<PaymentMethod[]> {
  return (
    await apiGet<{items: PaymentMethod[]}>('/payment-methods')
  ).items.filter((method) => method.kind === 'cash' && method.active);
}
