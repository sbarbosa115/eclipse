// Item 10 (quotation): the cotización's API calls, types and status rules.
export {
  acceptQuotation,
  convertQuotation,
  createQuotation,
  duplicateQuotation,
  emitQuotation,
  getQuotation,
  listQuotations,
  QUOTATION_STATUSES,
  quotationPdfUrl,
  rejectQuotation,
  sendQuotation,
  updateQuotation,
  voidQuotation,
  type Quotation,
  type QuotationFilters,
  type QuotationLine,
  type QuotationPage,
  type QuotationRequest,
  type QuotationStatus,
  type QuotationSummary,
} from './api/quotationApi';
export {
  canConvert,
  canDecide,
  canSend,
  canVoid,
  statusTone,
} from './model/status';
export {
  quotationErrorMessage,
  violationsOf,
  type Violation,
} from './lib/errorMessage';
