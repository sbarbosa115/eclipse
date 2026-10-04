import type {Quotation, QuotationRequest} from '@/entities/quotation';
import {ApiError} from '@/shared/api';
import {addDays} from '@/shared/lib';
import {
  emptyLine,
  isBlankLine,
  type DocumentDraft,
  type EditorErrors,
} from '@/widgets/document-editor';

// The quotation between the document form (DocumentDraft + its own fields) and the API (QuotationRequest/Output).

/** The fields a quotation has beyond the shared form (§4.7). */
export interface QuotationExtras {
  /** Responsable de la cotización: a tercero with role empleado. */
  responsible: {id: string; name: string} | null;
  /** Fecha de vencimiento of the offer, YYYY-MM-DD. */
  expiry_date: string;
  /** True once the person chose it: from then on a new issue date no longer moves it. */
  expiry_touched: boolean;
  /** Encabezado, plain text. */
  header: string;
  /** Condiciones comerciales, plain text. */
  terms: string;
}

/** §9 Q17: an offer is valid for 30 days unless said otherwise. */
export const VALIDITY_DAYS = 30;

export function emptyExtras(issueDate: string): QuotationExtras {
  return {
    responsible: null,
    expiry_date: addDays(issueDate, VALIDITY_DAYS),
    expiry_touched: false,
    header: '',
    terms: '',
  };
}

/** "1000000.0000" → "1000000", "2.5000" → "2.5": how a person writes it. */
export function trimDecimal(value: string): string {
  return value.includes('.') ? value.replace(/\.?0+$/, '') : value;
}

/** The form's draft of a saved quotation. */
export function draftFromQuotation(
  quotation: Quotation,
  typeLabel: string,
): DocumentDraft {
  return {
    type_label: typeLabel,
    number: quotation.number ?? null,
    tercero: {id: quotation.tercero_id, name: quotation.tercero_name},
    contact_id: quotation.contact_id ?? null,
    issue_date: quotation.issue_date,
    lines:
      quotation.lines.length === 0
        ? [emptyLine()]
        : quotation.lines.map((line) => ({
            ...emptyLine(),
            product: line.product_id
              ? {
                  id: line.product_id,
                  label: line.product_label ?? line.description,
                }
              : null,
            description: line.description,
            quantity: trimDecimal(line.quantity),
            unit_price: trimDecimal(line.unit_price),
            discount:
              Number(line.discount) === 0 ? '' : trimDecimal(line.discount),
            charge_tax_id: line.charge_tax_id ?? null,
            withholding_tax_id: line.withholding_tax_id ?? null,
          })),
    payments: [],
    notes: quotation.notes ?? '',
    attachments: [],
  };
}

export function extrasFromQuotation(quotation: Quotation): QuotationExtras {
  return {
    responsible: quotation.responsible_id
      ? {
          id: quotation.responsible_id,
          name: quotation.responsible_name ?? '',
        }
      : null,
    expiry_date: quotation.expiry_date,
    expiry_touched: true,
    header: quotation.header ?? '',
    terms: quotation.terms ?? '',
  };
}

const orNull = (text: string) => (text.trim() === '' ? null : text.trim());

/**
 * What is sent: the draft without its blank lines. `lineIndexes[i]` is the form's index of the i-th line sent, to put
 * the API's `lines.i.…` violations back on the right row.
 */
export function requestFromDraft(
  draft: DocumentDraft,
  extras: QuotationExtras,
): {body: QuotationRequest; lineIndexes: number[]} {
  const lineIndexes: number[] = [];
  const lines = draft.lines.flatMap((line, index) => {
    if (isBlankLine(line)) return [];
    lineIndexes.push(index);
    return [
      {
        product_id: line.product?.id ?? null,
        description: line.description.trim(),
        quantity: line.quantity.trim(),
        unit_price: line.unit_price.trim(),
        discount: line.discount.trim(),
        charge_tax_id: line.charge_tax_id,
        withholding_tax_id: line.withholding_tax_id,
      },
    ];
  });
  return {
    body: {
      tercero_id: draft.tercero?.id ?? '',
      contact_id: draft.contact_id,
      responsible_id: extras.responsible?.id ?? null,
      issue_date: draft.issue_date,
      expiry_date: extras.expiry_date === '' ? null : extras.expiry_date,
      header: orNull(extras.header),
      terms: orNull(extras.terms),
      notes: orNull(draft.notes),
      lines,
    },
    lineIndexes,
  };
}

const FIELD_ALIASES: Record<string, string> = {
  tercero_id: 'tercero',
  product_id: 'product',
};

/**
 * The API's violations as the form's field paths: "lines[0].quantity" and "lines.0.quantity" → the form's row,
 * "tercero_id" → "tercero", "lines.0.product_id" → "lines.N.product". Null when the error is not a validation one.
 */
export function editorErrorsFrom(
  error: unknown,
  lineIndexes: number[],
): EditorErrors | null {
  if (!(error instanceof ApiError) || error.code !== 'validation_failed') {
    return null;
  }
  const violations =
    (error.body as {violations?: {field: string; message: string}[]})
      ?.violations ?? [];
  const errors: EditorErrors = {};
  for (const {field, message} of violations) {
    const parts = field.replace(/\[(\d+)\]/g, '.$1').split('.');
    if (parts[0] === 'lines' && parts[1] !== undefined) {
      parts[1] = String(lineIndexes[Number(parts[1])] ?? parts[1]);
    }
    const last = parts.length - 1;
    parts[last] = FIELD_ALIASES[parts[last] ?? ''] ?? parts[last] ?? '';
    const path = parts.join('.');
    errors[path] ??= message;
  }
  return errors;
}

/** The extras' own checks, by field path (the shared form checks the rest). */
export function validateExtras(
  draft: DocumentDraft,
  extras: QuotationExtras,
  message: (key: 'expiry' | 'expiryBeforeIssue') => string,
): EditorErrors {
  const errors: EditorErrors = {};
  if (!/^\d{4}-\d{2}-\d{2}$/.test(extras.expiry_date)) {
    errors['expiry_date'] = message('expiry');
  } else if (
    /^\d{4}-\d{2}-\d{2}$/.test(draft.issue_date) &&
    extras.expiry_date < draft.issue_date
  ) {
    errors['expiry_date'] = message('expiryBeforeIssue');
  }
  return errors;
}
