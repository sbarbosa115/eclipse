import {apiGet, type Schema} from '@/shared/api';

export type PaymentMethod = Schema<'PaymentMethodOutput'>;

/** The company's active payment methods, for the formas de pago rows. */
export async function listPaymentMethods(): Promise<PaymentMethod[]> {
  return (await apiGet<{items: PaymentMethod[]}>('/payment-methods')).items;
}
