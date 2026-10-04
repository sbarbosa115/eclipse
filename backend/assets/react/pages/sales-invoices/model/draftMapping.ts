import type {SalesInvoice, SalesInvoiceRequest} from '@/entities/sales-invoice';
import {
  draftLineFrom,
  emptyLine,
  linesToSend,
  type CreditTerm,
  type DocumentDraft,
} from '@/widgets/document-editor';

// The factura de venta between the document form (DocumentDraft) and the API (SalesInvoiceRequest/Output).

function daysBetween(from: string, to: string): number {
  const ms = Date.parse(`${to}T00:00:00Z`) - Date.parse(`${from}T00:00:00Z`);
  return Math.round(ms / 86_400_000);
}

/** The credit term a saved due date reads as: hoy, a 15, 30 o 60 días, or a date chosen by hand. */
export function termFor(issueDate: string, dueDate: string | null): CreditTerm {
  if (!dueDate) return '30';
  const days = daysBetween(issueDate, dueDate);
  if (days === 0) return 'today';
  if (days === 15 || days === 30 || days === 60) {
    return String(days) as CreditTerm;
  }
  return 'custom';
}

/** The form's draft of a saved invoice. */
export function draftFromInvoice(
  invoice: SalesInvoice,
  typeLabel: string,
): DocumentDraft {
  return {
    type_label: typeLabel,
    number: invoice.number ?? null,
    tercero: {id: invoice.tercero_id, name: invoice.tercero_name},
    contact_id: invoice.contact_id ?? null,
    issue_date: invoice.issue_date,
    lines:
      invoice.lines.length === 0
        ? [emptyLine()]
        : invoice.lines.map(draftLineFrom),
    payments: invoice.payments.map((payment) => ({
      key: `payment-${payment.id}`,
      payment_method_id: payment.payment_method_id,
      amount: payment.amount,
      term: termFor(invoice.issue_date, payment.due_date ?? null),
      due_date: payment.due_date ?? null,
    })),
    notes: invoice.notes ?? '',
    attachments: [],
  };
}

/**
 * What is sent: the draft without its blank lines. `lineIndexes[i]` is the form's index of the i-th line sent, to put
 * the API's `lines.i.…` violations back on the right row.
 */
export function requestFromDraft(
  draft: DocumentDraft,
  sellerId: string | null = null,
): {body: SalesInvoiceRequest; lineIndexes: number[]} {
  const sent = linesToSend(draft);
  const lines = sent.lines.map((line) => ({
    product_id: line.product?.id ?? null,
    description: line.description.trim(),
    quantity: line.quantity.trim(),
    unit_price: line.unit_price.trim(),
    discount: line.discount.trim(),
    charge_tax_id: line.charge_tax_id,
    withholding_tax_id: line.withholding_tax_id,
  }));
  const {lineIndexes} = sent;
  return {
    body: {
      tercero_id: draft.tercero?.id ?? '',
      contact_id: draft.contact_id,
      seller_id: sellerId,
      issue_date: draft.issue_date,
      notes: draft.notes.trim() === '' ? null : draft.notes.trim(),
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
