import {todayInColombia} from '@/shared/lib';
import type {Translate} from '@/shared/i18n';
import {formatMoney} from '@/shared/lib';
import {Decimal, trimDecimal} from '../lib/decimal';
import type {
  CreditTerm,
  DocumentDraft,
  DocumentKind,
  DraftLine,
  DraftPayment,
  EditorErrors,
  PartyRef,
} from './types';

// The document form's state changes, as pure functions of the draft: the widget calls them, and the pages that mount
// it (and their tests) can too.

let counter = 0;
function newKey(prefix: string): string {
  counter += 1;
  return `${prefix}${counter}`;
}

/** Today in Colombia's calendar (the server's), as the API writes dates (YYYY-MM-DD). */
export function todayIso(now: Date = new Date()): string {
  return todayInColombia(now);
}

export function emptyLine(): DraftLine {
  return {
    key: newKey('line-'),
    product: null,
    account: null,
    description: '',
    quantity: '',
    unit_price: '',
    discount: '',
    charge_tax_id: null,
    withholding_tax_id: null,
  };
}

/** A new document: dated today, one empty line, no payments. */
export function emptyDraft({
  typeLabel,
  number = null,
  today = todayIso(),
}: {
  typeLabel: string;
  number?: string | null;
  today?: string;
}): DocumentDraft {
  return {
    type_label: typeLabel,
    number,
    tercero: null,
    contact_id: null,
    issue_date: today,
    lines: [emptyLine()],
    payments: [],
    notes: '',
    attachments: [],
  };
}

/** A line nobody has started: the pages leave it out of what they send. */
export function isBlankLine(line: DraftLine): boolean {
  return (
    line.product === null &&
    (line.account === null || line.account.text.trim() === '') &&
    line.description.trim() === '' &&
    line.quantity.trim() === '' &&
    line.unit_price.trim() === '' &&
    line.discount.trim() === ''
  );
}

/** Choosing another tercero forgets the contact: it was one of the previous tercero's. */
export function setTercero(
  draft: DocumentDraft,
  tercero: PartyRef | null,
): DocumentDraft {
  if (tercero?.id === draft.tercero?.id) return {...draft, tercero};
  return {...draft, tercero, contact_id: null};
}

function addDays(isoDate: string, days: number): string {
  const [y, m, d] = isoDate.split('-').map(Number);
  const date = new Date(Date.UTC(y ?? 1970, (m ?? 1) - 1, d ?? 1));
  date.setUTCDate(date.getUTCDate() + days);
  return date.toISOString().slice(0, 10);
}

const TERM_DAYS: Record<Exclude<CreditTerm, 'custom'>, number> = {
  today: 0,
  15: 15,
  30: 30,
  60: 60,
};

/** Fecha de vencimiento of a crédito payment: the issue date plus the term, or the date typed by hand. */
export function dueDateFor(
  term: CreditTerm,
  issueDate: string,
  custom: string | null,
): string | null {
  if (term === 'custom') return custom;
  if (!/^\d{4}-\d{2}-\d{2}$/.test(issueDate)) return null;
  return addDays(issueDate, TERM_DAYS[term]);
}

/**
 * A new issue date moves every due date computed from it; one typed by hand stays. While the issue date is not a date
 * yet (being typed), the due dates stay as they were.
 */
export function setIssueDate(
  draft: DocumentDraft,
  issueDate: string,
): DocumentDraft {
  return {
    ...draft,
    issue_date: issueDate,
    payments: draft.payments.map((p) =>
      p.due_date === null || p.term === 'custom'
        ? p
        : {...p, due_date: dueDateFor(p.term, issueDate, null) ?? p.due_date},
    ),
  };
}

export function updateLine(
  draft: DocumentDraft,
  index: number,
  change: Partial<DraftLine>,
): DocumentDraft {
  return {
    ...draft,
    lines: draft.lines.map((line, i) =>
      i === index ? {...line, ...change} : line,
    ),
  };
}

export function addLine(draft: DocumentDraft): DocumentDraft {
  return {...draft, lines: [...draft.lines, emptyLine()]};
}

/** Removes a line; the only line left is emptied instead, so the grid always has a row to type in. */
export function removeLine(draft: DocumentDraft, index: number): DocumentDraft {
  const lines = draft.lines.filter((_, i) => i !== index);
  return {...draft, lines: lines.length > 0 ? lines : [emptyLine()]};
}

/** Moves a line to another position; past either end it changes nothing. */
export function moveLine(
  draft: DocumentDraft,
  from: number,
  to: number,
): DocumentDraft {
  if (to < 0 || to >= draft.lines.length || from === to) return draft;
  const lines = [...draft.lines];
  const [moved] = lines.splice(from, 1);
  if (moved) lines.splice(to, 0, moved);
  return {...draft, lines};
}

/** What a line needs of the product chosen (ProductOutput). */
export interface ProductForLine {
  id: string;
  code: string;
  name: string;
  unit_price_net_of_tax: string;
  charge_tax_id?: string | null;
  withholding_tax_id?: string | null;
}

/**
 * Fills a line from the product chosen: its name, its taxes and, on a sale or a quotation, its price net of IVA
 * (§4.3: with an IVA-included price the line total is the list price). A purchase keeps the price typed: the
 * product's is what it sells for, not what it costs.
 */
export function applyProduct(
  kind: DocumentKind,
  line: DraftLine,
  product: ProductForLine,
): DraftLine {
  return {
    ...line,
    product: {id: product.id, label: `${product.code} · ${product.name}`},
    account: null,
    description: product.name,
    quantity: line.quantity.trim() === '' ? '1' : line.quantity,
    unit_price:
      kind === 'purchase_invoice'
        ? line.unit_price
        : trimDecimal(product.unit_price_net_of_tax),
    charge_tax_id: product.charge_tax_id ?? null,
    withholding_tax_id: product.withholding_tax_id ?? null,
  };
}

/** A purchase line is by product or by expense account (§4.10). */
export function setLineMode(
  line: DraftLine,
  mode: 'product' | 'account',
): DraftLine {
  if (mode === 'account') {
    return line.account
      ? line
      : {...line, product: null, account: {id: null, text: ''}};
  }
  return {...line, account: null};
}

/** What the payment rows need of a payment method. */
export interface MethodKind {
  id: string;
  kind: string;
}

function isCredit(
  methods: ReadonlyArray<MethodKind>,
  id: string | null,
): boolean {
  return methods.find((m) => m.id === id)?.kind === 'credit';
}

function money(text: string): Decimal {
  return (Decimal.parse(text) ?? Decimal.ZERO).roundHalfUp(2);
}

/** A payment row offering what is left of Total neto (empty when nothing is). */
export function addPayment(draft: DocumentDraft, net: string): DocumentDraft {
  const left = money(net).minus(
    Decimal.sum(draft.payments.map((p) => money(p.amount))),
  );
  const payment: DraftPayment = {
    key: newKey('payment-'),
    payment_method_id: null,
    amount: left.compare(Decimal.ZERO) > 0 ? left.toString() : '',
    term: '30',
    due_date: null,
  };
  return {...draft, payments: [...draft.payments, payment]};
}

/** Changes a payment row; its due date follows its method (crédito only) and its term. */
export function updatePayment(
  draft: DocumentDraft,
  index: number,
  change: Partial<DraftPayment>,
  methods: ReadonlyArray<MethodKind>,
): DocumentDraft {
  return {
    ...draft,
    payments: draft.payments.map((payment, i) => {
      if (i !== index) return payment;
      const next = {...payment, ...change};
      if (!isCredit(methods, next.payment_method_id)) {
        return {...next, due_date: null};
      }
      if (next.term === 'custom') {
        return {
          ...next,
          due_date:
            change.due_date !== undefined ? change.due_date : next.due_date,
        };
      }
      return {...next, due_date: dueDateFor(next.term, draft.issue_date, null)};
    }),
  };
}

export function removePayment(
  draft: DocumentDraft,
  index: number,
): DocumentDraft {
  return {...draft, payments: draft.payments.filter((_, i) => i !== index)};
}

/** Total formas de pago against Total neto: the check mark shows when they match. `difference` is total − net. */
export function paymentBalance(
  payments: ReadonlyArray<DraftPayment>,
  net: string,
): {total: string; matches: boolean; difference: string} {
  const total = Decimal.sum(payments.map((p) => money(p.amount)));
  const difference = total.minus(money(net));
  return {
    total: total.roundHalfUp(2).toString(),
    matches: payments.length > 0 && difference.isZero(),
    difference: difference.roundHalfUp(2).toString(),
  };
}

const FOUR_DECIMALS = 4;

function decimalWithin(text: string, places: number): Decimal | null {
  const value = Decimal.parse(text);
  if (!value || value.decimalsUsed() > places) return null;
  return value;
}

/**
 * Checks the draft before it is saved, and names each problem by its field path (EditorErrors). Blank lines are
 * ignored; a draft may be saved with formas de pago that do not add up to Total neto, but not emitted (§4.6).
 */
export function validateDraft(
  kind: DocumentKind,
  draft: DocumentDraft,
  {
    net,
    methods,
    forEmission,
    t,
  }: {
    net: string;
    methods: ReadonlyArray<MethodKind>;
    forEmission: boolean;
    t: Translate;
  },
): EditorErrors {
  const errors: EditorErrors = {};
  const message = (key: string, params?: Record<string, string>) =>
    t(`documentEditor.validation.${key}`, params);

  if (!draft.tercero) errors['tercero'] = message('tercero');
  if (!/^\d{4}-\d{2}-\d{2}$/.test(draft.issue_date)) {
    errors['issue_date'] = message('issueDate');
  }

  const lines = draft.lines
    .map((line, index) => ({line, index}))
    .filter(({line}) => !isBlankLine(line));
  if (lines.length === 0) errors['lines'] = message('lines');
  for (const {line, index} of lines) {
    const at = `lines.${index}`;
    if (line.account) {
      if (!line.account.id) errors[`${at}.account`] = message('account');
    } else if (!line.product) {
      errors[`${at}.product`] = message(
        kind === 'purchase_invoice' ? 'productOrAccount' : 'product',
      );
    }
    const quantity = decimalWithin(line.quantity, FOUR_DECIMALS);
    if (!quantity) {
      errors[`${at}.quantity`] = message('quantityFormat');
    } else if (quantity.compare(Decimal.ZERO) <= 0) {
      errors[`${at}.quantity`] = message('quantityPositive');
    }
    const price = decimalWithin(line.unit_price, FOUR_DECIMALS);
    if (!price || price.isNegative()) {
      errors[`${at}.unit_price`] = message('unitPrice');
    }
    if (line.discount.trim() !== '') {
      const discount = decimalWithin(line.discount, FOUR_DECIMALS);
      if (
        !discount ||
        discount.isNegative() ||
        discount.compare(Decimal.of('100')) > 0
      ) {
        errors[`${at}.discount`] = message('discount');
      }
    }
  }

  if (kind === 'quotation') return errors;

  draft.payments.forEach((payment, index) => {
    const at = `payments.${index}`;
    if (!payment.payment_method_id) {
      errors[`${at}.payment_method_id`] = message('method');
    }
    const amount = decimalWithin(payment.amount, 2);
    if (!amount || amount.compare(Decimal.ZERO) <= 0) {
      errors[`${at}.amount`] = message('amount');
    }
    if (isCredit(methods, payment.payment_method_id)) {
      if (!payment.due_date) {
        errors[`${at}.due_date`] = message('dueDate');
      } else if (payment.due_date < draft.issue_date) {
        errors[`${at}.due_date`] = message('dueBeforeIssue');
      }
    }
  });

  if (forEmission) {
    const balance = paymentBalance(draft.payments, net);
    if (!balance.matches) {
      errors['payments'] = message('paymentsMismatch', {
        total: formatMoney(balance.total),
        net: formatMoney(net),
      });
    }
  }
  return errors;
}

export function hasErrors(errors: EditorErrors): boolean {
  return Object.values(errors).some(Boolean);
}
