// Item 11 (cash-receipt), written for every payment allocated to open items: recibos de caja (receivables) and, in
// item 12, recibos de pago (payables). It names neither: the page passes the open items and keeps the amounts.
export {AllocatePayment, type AllocatePaymentProps} from './ui/AllocatePayment';
export {
  allocationsOf,
  parseAmount,
  summarize,
  type AllocationSummary,
  type OpenItem,
  type RowError,
} from './model/allocation';
