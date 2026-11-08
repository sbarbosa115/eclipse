import {ApiError} from '@/shared/api';
import {emptyLine, isBlankLine} from '../model/draft';
import type {DocumentDraft, DraftLine, EditorErrors} from '../model/types';
import {trimDecimal} from './decimal';

// What every document page (factura de venta, cotización, factura de compra) does between the form and its API:
// read saved lines into rows, send the rows typed, and put the API's violations back on the rows.

/** A saved line as the APIs answer it (a purchase line may go to an account instead of a product). */
export interface SavedLine {
  id?: string;
  product_id?: string | null;
  product_label?: string | null;
  /** The product's code as it was when the line was written (a snapshot). */
  product_code?: string | null;
  account_id?: string | null;
  account_label?: string | null;
  description: string;
  quantity: string;
  unit_price: string;
  discount: string;
  charge_tax_id?: string | null;
  /** Deprecated: the first retención; read only when the line has no `withholdings` list. */
  withholding_tax_id?: string | null;
  /** The line's retenciones (LineWithholdingOutput); a tax deleted since has no id. */
  withholdings?: {tax_id?: string | null; name?: string}[];
}

/** The ids of a saved line's retenciones, in order. */
function withholdingIds(line: SavedLine): string[] {
  if (line.withholdings) {
    return line.withholdings.flatMap((w) => (w.tax_id ? [w.tax_id] : []));
  }
  return line.withholding_tax_id ? [line.withholding_tax_id] : [];
}

/**
 * A line's retenciones as the APIs take them. `withholding_tax_id` (the first) is the deprecated alias the API reads
 * until the server side of §4.2 reads `withholdings`; it goes with the next contract.
 */
export function withholdingsInput(line: Pick<DraftLine, 'withholdings'>): {
  withholdings: {tax_id: string}[];
  withholding_tax_id: string | null;
} {
  return {
    withholdings: line.withholdings.map((taxId) => ({tax_id: taxId})),
    withholding_tax_id: line.withholdings[0] ?? null,
  };
}

/** A saved line as a row of the form. */
export function draftLineFrom(line: SavedLine): DraftLine {
  return {
    ...emptyLine(),
    ...(line.id ? {key: `line-${line.id}`} : {}),
    product: line.product_id
      ? {
          id: line.product_id,
          label: line.product_label ?? line.description,
          code: line.product_code ?? null,
        }
      : null,
    account: line.account_id
      ? {id: line.account_id, text: line.account_label ?? ''}
      : null,
    description: line.description,
    quantity: trimDecimal(line.quantity),
    unit_price: trimDecimal(line.unit_price),
    discount: Number(line.discount) === 0 ? '' : trimDecimal(line.discount),
    charge_tax_id: line.charge_tax_id ?? null,
    withholdings: withholdingIds(line),
  };
}

/**
 * The rows to send: the draft without its blank lines. `lineIndexes[i]` is the form's index of the i-th line sent, to
 * put the API's `lines.i.…` violations back on the right row.
 */
export function linesToSend(draft: DocumentDraft): {
  lines: DraftLine[];
  lineIndexes: number[];
} {
  const lines: DraftLine[] = [];
  const lineIndexes: number[] = [];
  draft.lines.forEach((line, index) => {
    if (isBlankLine(line)) return;
    lines.push(line);
    lineIndexes.push(index);
  });
  return {lines, lineIndexes};
}

/** Every violation on a line's retenciones goes under its Retenciones cell: `lines.N.withholdings…`. */
const LINE_FIELD_ALIASES: Record<string, string> = {
  withholdings: 'withholdings',
  withholding_tax_id: 'withholdings',
};

const FIELD_ALIASES: Record<string, string> = {
  tercero_id: 'tercero',
  product_id: 'product',
  account_id: 'account',
};

/**
 * The API's violations as the form's field paths: "lines[0].quantity" and "lines.0.quantity" → the form's row,
 * "tercero_id" → "tercero", "lines.0.product_id" → "lines.N.product", "lines.0.withholdings.1.tax_id" →
 * "lines.N.withholdings", "payments[1].due_date" → "payments.1.due_date".
 * Null when the API named no field (an error that is not a validation one and carries no violations).
 */
export function editorErrorsFrom(
  error: unknown,
  lineIndexes: number[],
): EditorErrors | null {
  if (!(error instanceof ApiError)) return null;
  const violations =
    (error.body as {violations?: {field: string; message: string}[]} | null)
      ?.violations ?? [];
  if (violations.length === 0 && error.code !== 'validation_failed') {
    return null;
  }
  const errors: EditorErrors = {};
  for (const {field, message} of violations) {
    const parts = field.replace(/\[(\d+)\]/g, '.$1').split('.');
    if (parts[0] === 'lines' && parts[1] !== undefined) {
      parts[1] = String(lineIndexes[Number(parts[1])] ?? parts[1]);
      const lineField = LINE_FIELD_ALIASES[parts[2] ?? ''];
      if (lineField) parts.splice(2, parts.length - 2, lineField);
    }
    const last = parts.length - 1;
    parts[last] = FIELD_ALIASES[parts[last] ?? ''] ?? parts[last] ?? '';
    errors[parts.join('.')] ??= message;
  }
  return errors;
}
