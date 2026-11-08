import {apiGet, type Schema} from '@/shared/api';

export type DocumentEntry = Schema<'JournalEntryOutput'>;

/** The documents whose entries the tab shows (the API refuses any other source_type with 400 invalid_source). */
export type EntrySourceType =
  'sales_invoice' | 'purchase_invoice' | 'cash_receipt' | 'supplier_payment';

/** A document's own entries: its posting and, once voided, the reversal, by number. */
export function fetchDocumentEntries(
  sourceType: EntrySourceType,
  sourceId: string,
) {
  const query = new URLSearchParams({
    source_type: sourceType,
    source_id: sourceId,
  });
  return apiGet<{items: DocumentEntry[]}>(`/ledger/entries?${query}`);
}
