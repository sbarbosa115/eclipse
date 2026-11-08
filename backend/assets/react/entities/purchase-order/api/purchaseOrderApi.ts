import {API_BASE, apiGet, apiPost, apiPut, type Schema} from '@/shared/api';

export type PurchaseOrder = Schema<'PurchaseOrderOutput'>;
export type PurchaseOrderSummary = Schema<'PurchaseOrderSummaryOutput'>;
export type PurchaseOrderLine = Schema<'PurchaseInvoiceLineOutput'>;

/** On the wire (observations PRD §4.8): `converted` instead of a quotation's `accepted`. */
export type PurchaseOrderStatus = 'draft' | 'emitted' | 'converted' | 'voided';

export const PURCHASE_ORDER_STATUSES: readonly PurchaseOrderStatus[] = [
  'draft',
  'emitted',
  'converted',
  'voided',
];

export interface PurchaseOrderPage {
  items: PurchaseOrderSummary[];
  total: number;
  page: number;
  per_page: number;
}

export interface PurchaseOrderFilters {
  q?: string;
  status?: string;
  /** YYYY-MM-DD, both included. */
  from?: string;
  to?: string;
  page?: number;
  per_page?: number;
}

/** The body of POST /purchase-orders and PUT /purchase-orders/{id}: the whole draft. */
export interface PurchaseOrderRequest {
  tercero_id: string;
  issue_date: string;
  /** Fecha esperada de entrega. */
  expected_date: string | null;
  notes: string | null;
  lines: {
    product_id: string | null;
    account_id: string | null;
    description: string;
    quantity: string;
    unit_price: string;
    discount: string;
    charge_tax_id: string | null;
    /** The line's retenciones, at most one per kind. */
    withholdings: {tax_id: string}[];
    /** Deprecated alias of the first retención, still sent by the shared form mapping. */
    withholding_tax_id?: string | null;
  }[];
}

export function listPurchaseOrders(
  filters: PurchaseOrderFilters = {},
): Promise<PurchaseOrderPage> {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== '') params.set(key, String(value));
  }
  const query = params.toString();
  return apiGet(`/purchase-orders${query ? `?${query}` : ''}`);
}

export function getPurchaseOrder(id: string): Promise<PurchaseOrder> {
  return apiGet(`/purchase-orders/${id}`);
}

export function createPurchaseOrder(
  body: PurchaseOrderRequest,
): Promise<PurchaseOrder> {
  return apiPost('/purchase-orders', body);
}

export function updatePurchaseOrder(
  id: string,
  body: PurchaseOrderRequest,
): Promise<PurchaseOrder> {
  return apiPut(`/purchase-orders/${id}`, body);
}

/** Emitir, or Emitir y enviar (the PDF by e-mail to the supplier). */
export function emitPurchaseOrder(
  id: string,
  send = false,
): Promise<PurchaseOrder> {
  return apiPost(
    `/purchase-orders/${id}/${send ? 'emit-and-send' : 'emit'}`,
    {},
  );
}

export function sendPurchaseOrder(id: string): Promise<null> {
  return apiPost(`/purchase-orders/${id}/send`, {});
}

export function voidPurchaseOrder(
  id: string,
  reason: string,
): Promise<PurchaseOrder> {
  return apiPost(`/purchase-orders/${id}/void`, {reason});
}

/** Makes the draft purchase invoice (once): the answer is the order, converted, with `converted_invoice_id`. */
export function convertPurchaseOrder(id: string): Promise<PurchaseOrder> {
  return apiPost(`/purchase-orders/${id}/convert`, {});
}

export function duplicatePurchaseOrder(id: string): Promise<PurchaseOrder> {
  return apiPost(`/purchase-orders/${id}/duplicate`, {});
}

/** Where the browser downloads the PDF from (same origin, the session cookie goes with it). */
export function purchaseOrderPdfUrl(id: string): string {
  return `${API_BASE}/purchase-orders/${id}/pdf`;
}
