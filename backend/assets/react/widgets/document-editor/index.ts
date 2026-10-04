// The shared document form (§4.6) and its pure model: items 8 (sales invoice), 9 (purchase invoice) and 10
// (quotation) mount DocumentEditor, keep a DocumentDraft in their state, and check it with validateDraft().
export {DocumentEditor, type DocumentEditorProps} from './ui/DocumentEditor';
export {
  addLine,
  addPayment,
  applyProduct,
  dueDateFor,
  emptyDraft,
  emptyLine,
  hasErrors,
  isBlankLine,
  moveLine,
  paymentBalance,
  removeLine,
  removePayment,
  setIssueDate,
  setLineMode,
  setTercero,
  todayIso,
  updateLine,
  updatePayment,
  validateDraft,
  type MethodKind,
  type ProductForLine,
} from './model/draft';
export {
  computeTotals,
  type LineAmounts,
  type TaxRateInfo,
  type TotalsPreview,
} from './model/totals';
export type {
  Attachment,
  CreditTerm,
  DocumentDraft,
  DocumentKind,
  DraftLine,
  DraftPayment,
  EditorErrors,
  PartyRef,
  ProductRef,
} from './model/types';
export {Decimal, trimDecimal} from './lib/decimal';
export {
  draftLineFrom,
  editorErrorsFrom,
  linesToSend,
  type SavedLine,
} from './lib/mapping';
export {DocumentEditorDemo} from './ui/DocumentEditorDemo';
