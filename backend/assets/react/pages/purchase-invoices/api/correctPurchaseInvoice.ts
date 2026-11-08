import {apiPost} from '@/shared/api';
import type {PurchaseInvoice} from '@/entities/purchase-invoice';

/**
 * Corregir (observations §4.6): voids the emitted invoice and answers the editable copy (the new draft), in one
 * transaction on the server.
 */
export function correctPurchaseInvoice(
  id: string,
  reason: string,
): Promise<PurchaseInvoice> {
  return apiPost<PurchaseInvoice>(`/purchase-invoices/${id}/correct`, {
    reason,
  });
}
