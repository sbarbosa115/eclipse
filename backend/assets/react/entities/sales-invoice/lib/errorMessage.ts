import type {Translate} from '@/shared/i18n';
import {ApiError} from '@/shared/api';
import {formatMoney} from '@/shared/lib';

/** What to tell the person when a sales invoice call fails: the API's error code in words. */
export function salesInvoiceErrorMessage(error: unknown, t: Translate): string {
  if (!(error instanceof ApiError)) {
    return t(
      error instanceof TypeError
        ? 'common.errors.network'
        : 'salesInvoice.errors.unexpected',
    );
  }
  if (error.code === 'payments_do_not_match_total') {
    const detail =
      (error.body as {detail?: {payments_total?: string; net_total?: string}})
        ?.detail ?? {};
    return t('salesInvoice.errors.payments_do_not_match_total', {
      payments: formatMoney(detail.payments_total ?? '0'),
      net: formatMoney(detail.net_total ?? '0'),
    });
  }
  const key = `salesInvoice.errors.${error.code}`;
  const message = t(key);
  return message === key ? t('salesInvoice.errors.unexpected') : message;
}
