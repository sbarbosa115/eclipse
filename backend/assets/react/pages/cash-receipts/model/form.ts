import type {Translate} from '@/shared/i18n';
import {
  allocationsOf,
  parseAmount,
  type OpenItem,
} from '@/features/allocate-payment';
import type {CashReceiptRequest, OpenReceivable} from '../api/cashReceiptApi';

/** The new recibo de caja as the person fills it in: text as typed, the amounts per receivable id. */
export interface ReceiptForm {
  client: {id: string; name: string} | null;
  /** YYYY-MM-DD, '' while what is typed is not a date. */
  date: string;
  methodId: string;
  amount: string;
  notes: string;
  amounts: Record<string, string>;
}

/** By the API's field names: tercero_id, receipt_date, payment_method_id, amount, notes, allocations. */
export type FieldErrors = Partial<Record<string, string>>;

export function emptyForm(today: string): ReceiptForm {
  return {
    client: null,
    date: today,
    methodId: '',
    amount: '',
    notes: '',
    amounts: {},
  };
}

/** What the person must fix before anything is sent; the allocations' sums are AllocatePayment's to show. */
export function validateForm(form: ReceiptForm, t: Translate): FieldErrors {
  const errors: FieldErrors = {};
  if (!form.client) errors.tercero_id = t('cashReceipt.form.required.client');
  if (form.date === '')
    errors.receipt_date = t('cashReceipt.form.required.date');
  if (form.methodId === '') {
    errors.payment_method_id = t('cashReceipt.form.required.method');
  }
  const amount = parseAmount(form.amount);
  if (amount === null || amount === '0.00') {
    errors.amount = t('cashReceipt.form.required.amount');
  }
  return errors;
}

export function openItemsOf(receivables: OpenReceivable[]): OpenItem[] {
  return receivables.map((r) => ({
    id: r.id,
    document: r.invoice_number,
    issueDate: r.issue_date,
    dueDate: r.due_date,
    amount: r.amount,
    balance: r.balance,
  }));
}

export function requestFrom(
  form: ReceiptForm,
  receivables: OpenReceivable[],
  send: boolean,
): CashReceiptRequest {
  const notes = form.notes.trim();
  return {
    tercero_id: form.client?.id ?? '',
    receipt_date: form.date,
    payment_method_id: form.methodId,
    amount: parseAmount(form.amount) ?? form.amount,
    notes: notes === '' ? null : notes,
    allocations: allocationsOf(openItemsOf(receivables), form.amounts).map(
      (a) => ({receivable_id: a.id, amount: a.amount}),
    ),
    send,
  };
}

const ALLOCATION = /^allocations(?:\.(\d+)|\[(\d+)\])(?:\.(\w+))?$/;

/**
 * The API's violations, on the form: a header field by its name, an allocation's on its row (by the receivable it
 * names in the request that was sent). The domain names `allocations.0.amount`, the input checks `allocations[0].amount`.
 */
export function fieldErrorsFrom(
  violations: ReadonlyArray<{field: string; message: string}>,
  request: CashReceiptRequest,
): {fields: FieldErrors; rows: Record<string, string>} {
  const fields: FieldErrors = {};
  const rows: Record<string, string> = {};
  for (const {field, message} of violations) {
    const match = ALLOCATION.exec(field);
    if (match) {
      const index = Number(match[1] ?? match[2]);
      const receivable = request.allocations[index]?.receivable_id;
      if (receivable) rows[receivable] ??= message;
      else fields.allocations ??= message;
    } else {
      fields[field] ??= message;
    }
  }
  return {fields, rows};
}
