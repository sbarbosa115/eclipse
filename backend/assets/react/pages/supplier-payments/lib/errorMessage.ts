import type {Translate} from '@/shared/i18n';
import {ApiError} from '@/shared/api';
import {formatMoney} from '@/shared/lib';

/** What to tell the person when a supplier payment call fails: the API's error code in words. */
export function supplierPaymentErrorMessage(
  error: unknown,
  t: Translate,
): string {
  if (!(error instanceof ApiError)) {
    return t(
      error instanceof TypeError
        ? 'common.errors.network'
        : 'supplierPayment.errors.unexpected',
    );
  }
  if (error.code === 'allocations_do_not_match_amount') {
    const detail =
      (error.body as {detail?: {allocated_total?: string; amount?: string}})
        ?.detail ?? {};
    return t('supplierPayment.errors.allocations_do_not_match_amount', {
      allocated: formatMoney(detail.allocated_total ?? '0'),
      amount: formatMoney(detail.amount ?? '0'),
    });
  }
  const key = `supplierPayment.errors.${error.code}`;
  const message = t(key);
  return message === key ? t('supplierPayment.errors.unexpected') : message;
}
