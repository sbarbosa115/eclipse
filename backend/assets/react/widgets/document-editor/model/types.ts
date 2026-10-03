import type {AccountChoice} from '@/features/pick-account';

/** The three documents that share the form (§4.6). A quotation has no formas de pago. */
export type DocumentKind = 'quotation' | 'sales_invoice' | 'purchase_invoice';

/** A tercero as the header holds it: its id and the name to show. */
export interface PartyRef {
  id: string;
  name: string;
}

/** A product or service on a line: its id and "código · nombre". */
export interface ProductRef {
  id: string;
  label: string;
}

/**
 * One line, as typed: numbers stay decimal strings (the API's, "1190000.00"), never floats. `key` is the line's
 * identity in the form only (React keys, focus); it is not sent.
 */
export interface DraftLine {
  key: string;
  product: ProductRef | null;
  /** Purchases only: a direct expense account instead of a product. Null while the line is by product. */
  account: AccountChoice | null;
  description: string;
  quantity: string;
  unit_price: string;
  /** % Descuento, 0 to 100; empty is none. */
  discount: string;
  charge_tax_id: string | null;
  withholding_tax_id: string | null;
}

/** Hoy, a 15, 30 o 60 días, or a date typed by hand (§4.5). */
export type CreditTerm = 'today' | '15' | '30' | '60' | 'custom';

export interface DraftPayment {
  key: string;
  payment_method_id: string | null;
  amount: string;
  /** Used only when the method is crédito. */
  term: CreditTerm;
  /** Fecha de vencimiento: only on a crédito payment, null otherwise. */
  due_date: string | null;
}

/** A file the parent page uploaded and attached to the document. */
export interface Attachment {
  id: string;
  name: string;
  size?: number | null;
}

/** What the form edits: the header, the lines, the formas de pago and the footer. */
export interface DocumentDraft {
  /** Tipo (numbering series), shown as given. */
  type_label: string;
  /** Número, shown as given; null while it is a draft (it is assigned at emission). */
  number: string | null;
  tercero: PartyRef | null;
  contact_id: string | null;
  /** Fecha de elaboración, YYYY-MM-DD. */
  issue_date: string;
  lines: DraftLine[];
  payments: DraftPayment[];
  notes: string;
  attachments: Attachment[];
}

/**
 * Messages by field path: `tercero`, `contact_id`, `issue_date`, `lines`, `lines.0.quantity`, `payments`,
 * `payments.1.due_date`, `notes`… The same paths validateDraft() produces; a page maps the API's violations onto them.
 */
export type EditorErrors = Partial<Record<string, string>>;
