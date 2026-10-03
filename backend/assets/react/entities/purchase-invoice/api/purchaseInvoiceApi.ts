import {
  ApiError,
  apiDelete,
  apiGet,
  apiPost,
  apiPut,
  API_BASE,
  type Schema,
} from '@/shared/api';

export type PurchaseInvoice = Schema<'PurchaseInvoiceOutput'>;
export type PurchaseInvoiceSummary = Schema<'PurchaseInvoiceSummaryOutput'>;
export type PurchaseInvoiceAttachment =
  Schema<'PurchaseInvoiceAttachmentOutput'>;

export type PurchaseInvoiceStatus =
  | 'draft'
  | 'emitted'
  | 'partially_paid'
  | 'paid'
  | 'voided';

export interface PurchaseInvoicePage {
  items: PurchaseInvoiceSummary[];
  total: number;
  page: number;
  per_page: number;
}

export interface PurchaseInvoiceFilters {
  q?: string;
  status?: string;
  from?: string;
  to?: string;
  page?: number;
  per_page?: number;
}

/** The body of POST /purchase-invoices and PUT /purchase-invoices/{id}. */
export interface PurchaseInvoiceRequest {
  tercero_id: string;
  supplier_invoice_number: string | null;
  issue_date: string;
  due_date: string | null;
  notes: string | null;
  lines: {
    product_id: string | null;
    account_id: string | null;
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

const BASE = '/purchase-invoices';

export function listPurchaseInvoices(
  filters: PurchaseInvoiceFilters,
): Promise<PurchaseInvoicePage> {
  const query = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== '') query.set(key, String(value));
  }
  const text = query.toString();
  return apiGet<PurchaseInvoicePage>(`${BASE}${text ? `?${text}` : ''}`);
}

export function getPurchaseInvoice(id: string): Promise<PurchaseInvoice> {
  return apiGet<PurchaseInvoice>(`${BASE}/${id}`);
}

export function createPurchaseInvoice(
  request: PurchaseInvoiceRequest,
): Promise<PurchaseInvoice> {
  return apiPost<PurchaseInvoice>(BASE, request);
}

export function updatePurchaseInvoice(
  id: string,
  request: PurchaseInvoiceRequest,
): Promise<PurchaseInvoice> {
  return apiPut<PurchaseInvoice>(`${BASE}/${id}`, request);
}

export function deletePurchaseInvoice(id: string): Promise<null> {
  return apiDelete(`${BASE}/${id}`);
}

export function emitPurchaseInvoice(id: string): Promise<PurchaseInvoice> {
  return apiPost<PurchaseInvoice>(`${BASE}/${id}/emit`, {});
}

export function voidPurchaseInvoice(
  id: string,
  reason: string,
): Promise<PurchaseInvoice> {
  return apiPost<PurchaseInvoice>(`${BASE}/${id}/void`, {reason});
}

export function duplicatePurchaseInvoice(
  id: string,
): Promise<PurchaseInvoice> {
  return apiPost<PurchaseInvoice>(`${BASE}/${id}/duplicate`, {});
}

/** The company's record of the purchase, as a PDF download. */
export const purchaseInvoicePdfUrl = (id: string): string =>
  `${API_BASE}${BASE}/${id}/pdf`;

export const supplierFileUrl = (id: string, attachmentId: string): string =>
  `${API_BASE}${BASE}/${id}/attachments/${attachmentId}`;

/** The shared client speaks JSON only: the supplier's file goes as multipart, with the same error shape on failure. */
export async function uploadSupplierFile(
  id: string,
  file: File,
): Promise<PurchaseInvoiceAttachment> {
  const form = new FormData();
  form.append('file', file);
  const response = await fetch(`${API_BASE}${BASE}/${id}/attachments`, {
    method: 'POST',
    headers: {Accept: 'application/json'},
    body: form,
  });
  const text = await response.text();
  const data: unknown = text === '' ? null : JSON.parse(text);
  if (!response.ok) {
    const error = (data ?? {}) as {error?: string; message?: string};
    throw new ApiError(
      response.status,
      error.error ?? 'http_error',
      error.message ?? response.statusText,
      data,
    );
  }
  return data as PurchaseInvoiceAttachment;
}

export function removeSupplierFile(
  id: string,
  attachmentId: string,
): Promise<null> {
  return apiDelete(`${BASE}/${id}/attachments/${attachmentId}`);
}
