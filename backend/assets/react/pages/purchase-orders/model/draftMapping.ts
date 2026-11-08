import type {
  PurchaseOrder,
  PurchaseOrderRequest,
} from '@/entities/purchase-order';
import {
  draftLineFrom,
  emptyLine,
  linesToSend,
  withholdingsInput,
  type DocumentDraft,
  type EditorErrors,
} from '@/widgets/document-editor';

// The purchase order between the document form (DocumentDraft + its own field) and the API.

/** The field an order has beyond the shared form (observations PRD §4.8). */
export interface PurchaseOrderExtras {
  /** Fecha esperada de entrega, YYYY-MM-DD, or '' for none. */
  expected_date: string;
}

export function emptyExtras(): PurchaseOrderExtras {
  return {expected_date: ''};
}

/** The form's draft of a saved order: no formas de pago, no attachments. */
export function draftFromOrder(
  order: PurchaseOrder,
  typeLabel: string,
): DocumentDraft {
  return {
    type_label: typeLabel,
    number: order.number ?? null,
    tercero: {id: order.tercero_id, name: order.tercero_name},
    contact_id: null,
    issue_date: order.issue_date,
    lines:
      order.lines.length === 0 ? [emptyLine()] : order.lines.map(draftLineFrom),
    payments: [],
    notes: order.notes ?? '',
    attachments: [],
  };
}

export function extrasFromOrder(order: PurchaseOrder): PurchaseOrderExtras {
  return {expected_date: order.expected_date ?? ''};
}

const orNull = (text: string) => (text.trim() === '' ? null : text.trim());

/**
 * What is sent: the draft without its blank lines. `lineIndexes[i]` is the form's index of the i-th line sent, to put
 * the API's `lines[i]` violations back on the right row.
 */
export function requestFromDraft(
  draft: DocumentDraft,
  extras: PurchaseOrderExtras,
): {body: PurchaseOrderRequest; lineIndexes: number[]} {
  const sent = linesToSend(draft);
  const lines = sent.lines.map((line) => ({
    product_id: line.account ? null : (line.product?.id ?? null),
    account_id: line.account ? line.account.id : null,
    description: line.description.trim(),
    quantity: line.quantity.trim(),
    unit_price: line.unit_price.trim(),
    discount: line.discount.trim() === '' ? '0' : line.discount.trim(),
    charge_tax_id: line.charge_tax_id,
    ...withholdingsInput(line),
  }));
  return {
    body: {
      tercero_id: draft.tercero?.id ?? '',
      issue_date: draft.issue_date,
      expected_date: orNull(extras.expected_date),
      notes: orNull(draft.notes),
      lines,
    },
    lineIndexes: sent.lineIndexes,
  };
}

/** The extra field's own checks, by field path (the shared form checks the rest). */
export function validateExtras(
  draft: DocumentDraft,
  extras: PurchaseOrderExtras,
  message: (key: 'expected' | 'expectedBeforeIssue') => string,
): EditorErrors {
  const errors: EditorErrors = {};
  const day = /^\d{4}-\d{2}-\d{2}$/;
  if (extras.expected_date !== '') {
    if (!day.test(extras.expected_date)) {
      errors['expected_date'] = message('expected');
    } else if (
      day.test(draft.issue_date) &&
      extras.expected_date < draft.issue_date
    ) {
      errors['expected_date'] = message('expectedBeforeIssue');
    }
  }
  return errors;
}
