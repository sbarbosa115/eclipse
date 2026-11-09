import {apiGet, type Schema} from '@/shared/api';

export type PaymentMethod = Schema<'PaymentMethodOutput'>;

/** The company's active payment methods, for the formas de pago rows; with `all` the inactive ones too. */
export async function listPaymentMethods(
  all = false,
): Promise<PaymentMethod[]> {
  return (
    await apiGet<{items: PaymentMethod[]}>(
      all ? '/payment-methods?all=1' : '/payment-methods',
    )
  ).items;
}
