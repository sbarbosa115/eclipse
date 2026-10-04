import type {
  PurchaseInvoice,
  PurchaseInvoiceRequest,
} from '@/entities/purchase-invoice';
import {
  draftLineFrom,
  linesToSend,
  type DocumentDraft,
  type DraftPayment,
} from '@/widgets/document-editor';

/** The purchase invoice's own header fields, next to the editor's draft. */
export interface PurchaseHeader {
  supplierInvoiceNumber: string;
  /** YYYY-MM-DD or '' for none. */
  dueDate: string;
}

const orNull = (text: string | null | undefined): string | null => {
  const trimmed = (text ?? '').trim();
  return trimmed === '' ? null : trimmed;
};

/**
 * The draft as the API takes it. Blank lines are left out; `lineIndexes[i]` is the editor's index of the i-th line
 * sent, so the API's `lines[i]` violations land on the right row.
 */
export function toRequest(
  draft: DocumentDraft,
  header: PurchaseHeader,
): {request: PurchaseInvoiceRequest; lineIndexes: number[]} {
  const sent = linesToSend(draft);
  const {lineIndexes} = sent;
  const lines: PurchaseInvoiceRequest['lines'] = sent.lines.map((line) => ({
    product_id: line.account ? null : (line.product?.id ?? null),
    account_id: line.account ? line.account.id : null,
    description: line.description.trim(),
    quantity: line.quantity.trim(),
    unit_price: line.unit_price.trim(),
    discount: line.discount.trim() === '' ? '0' : line.discount.trim(),
    charge_tax_id: line.charge_tax_id,
    withholding_tax_id: line.withholding_tax_id,
  }));
  return {
    request: {
      tercero_id: draft.tercero?.id ?? '',
      supplier_invoice_number: orNull(header.supplierInvoiceNumber),
      issue_date: draft.issue_date,
      due_date: orNull(header.dueDate),
      notes: orNull(draft.notes),
      lines,
      payments: draft.payments.map((payment) => ({
        payment_method_id: payment.payment_method_id ?? '',
        amount: payment.amount.trim(),
        due_date: payment.due_date,
      })),
    },
    lineIndexes,
  };
}

/** A saved invoice as the editor holds it: lines, payments (a crédito keeps its date) and the supplier's files. */
export function draftFromInvoice(
  invoice: PurchaseInvoice,
  typeLabel: string,
): {draft: DocumentDraft} & PurchaseHeader {
  const lines = invoice.lines.map(draftLineFrom);
  const payments: DraftPayment[] = invoice.payments.map((payment) => ({
    key: `payment-${payment.id}`,
    payment_method_id: payment.payment_method_id,
    amount: payment.amount,
    term: 'custom',
    due_date: payment.due_date ?? null,
  }));
  return {
    draft: {
      type_label: typeLabel,
      number: invoice.number ?? null,
      tercero: {id: invoice.tercero_id, name: invoice.tercero_name},
      contact_id: null,
      issue_date: invoice.issue_date,
      lines,
      payments,
      notes: invoice.notes ?? '',
      attachments: invoice.attachments.map((file) => ({
        id: file.id,
        name: file.file_name,
        size: file.size,
      })),
    },
    supplierInvoiceNumber: invoice.supplier_invoice_number ?? '',
    dueDate: invoice.due_date ?? '',
  };
}
