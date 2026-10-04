import type {Translate} from '@/shared/i18n';
import {ApiError} from '@/shared/api';
import {formatMoney} from '@/shared/lib';

/** What to tell the person when a cash receipt call fails: the API's error code in words. */
export function cashReceiptErrorMessage(error: unknown, t: Translate): string {
  if (!(error instanceof ApiError)) {
    return t(
      error instanceof TypeError
        ? 'common.errors.network'
        : 'cashReceipt.errors.unexpected',
    );
  }
  if (error.code === 'allocations_do_not_match_amount') {
    const detail =
      (error.body as {detail?: {allocated_total?: string; amount?: string}})
        ?.detail ?? {};
    return t('cashReceipt.errors.allocations_do_not_match_amount', {
      allocated: formatMoney(detail.allocated_total ?? '0'),
      amount: formatMoney(detail.amount ?? '0'),
    });
  }
  const key = `cashReceipt.errors.${error.code}`;
  const message = t(key);
  return message === key ? t('cashReceipt.errors.unexpected') : message;
}
