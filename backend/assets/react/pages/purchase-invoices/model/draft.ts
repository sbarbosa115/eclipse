import type {
  PurchaseInvoice,
  PurchaseInvoiceRequest,
} from '@/entities/purchase-invoice';
import {ApiError} from '@/shared/api';
import {
  isBlankLine,
  type DocumentDraft,
  type DraftLine,
  type DraftPayment,
  type EditorErrors,
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

/** "100000.0000" → "100000", "0.0000" → "0": how a person writes it. */
function trimZeros(value: string): string {
  return value.includes('.') ? value.replace(/\.?0+$/, '') : value;
}

/**
 * The draft as the API takes it. Blank lines are left out; `lineIndexes[i]` is the editor's index of the i-th line
 * sent, so the API's `lines[i]` violations land on the right row.
 */
export function toRequest(
  draft: DocumentDraft,
  header: PurchaseHeader,
): {request: PurchaseInvoiceRequest; lineIndexes: number[]} {
  const lineIndexes: number[] = [];
  const lines: PurchaseInvoiceRequest['lines'] = [];
  draft.lines.forEach((line, index) => {
    if (isBlankLine(line)) return;
    lineIndexes.push(index);
    lines.push({
      product_id: line.account ? null : (line.product?.id ?? null),
      account_id: line.account ? line.account.id : null,
      description: line.description.trim(),
      quantity: line.quantity.trim(),
      unit_price: line.unit_price.trim(),
      discount: line.discount.trim() === '' ? '0' : line.discount.trim(),
      charge_tax_id: line.charge_tax_id,
      withholding_tax_id: line.withholding_tax_id,
    });
  });
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
  const lines: DraftLine[] = invoice.lines.map((line) => ({
    key: `line-${line.id}`,
    product: line.product_id
      ? {id: line.product_id, label: line.product_label ?? line.description}
      : null,
    account: line.account_id
      ? {id: line.account_id, text: line.account_label ?? ''}
      : null,
    description: line.description,
    quantity: trimZeros(line.quantity),
    unit_price: trimZeros(line.unit_price),
    discount:
      Number(line.discount) === 0 ? '' : trimZeros(line.discount),
    charge_tax_id: line.charge_tax_id ?? null,
    withholding_tax_id: line.withholding_tax_id ?? null,
  }));
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

const LINE_FIELDS: Record<string, string> = {
  product_id: 'product',
  account_id: 'account',
};

/**
 * The API's violations (`lines[0].account_id`, `payments[1].due_date`, `tercero_id`) as the editor's field paths
 * (`lines.3.account`, `payments.1.due_date`, `tercero`).
 */
export function editorErrorsFrom(
  error: unknown,
  lineIndexes: number[],
): EditorErrors {
  const errors: EditorErrors = {};
  if (!(error instanceof ApiError)) return errors;
  const body = error.body as {
    violations?: {field: string; message: string}[];
  } | null;
  for (const {field, message} of body?.violations ?? []) {
    const line = /^lines\[(\d+)\]\.(\w+)$/.exec(field);
    const payment = /^payments\[(\d+)\]\.(\w+)$/.exec(field);
    let path = field === 'tercero_id' ? 'tercero' : field;
    if (line) {
      const index = lineIndexes[Number(line[1])] ?? Number(line[1]);
      path = `lines.${index}.${LINE_FIELDS[line[2] ?? ''] ?? line[2]}`;
    } else if (payment) {
      path = `payments.${payment[1]}.${payment[2]}`;
    }
    errors[path] ??= message;
  }
  return errors;
}
