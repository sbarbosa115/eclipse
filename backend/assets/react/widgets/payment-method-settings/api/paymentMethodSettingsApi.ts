import {apiDelete, apiGet, apiPost, apiPut, type Schema} from '@/shared/api';

export type PaymentMethod = Schema<'PaymentMethodSettingOutput'>;

export interface PaymentMethodPayload {
  name: string;
  account_id: string | null;
}

export interface NewPaymentMethodPayload extends PaymentMethodPayload {
  kind: string;
}

export async function listPaymentMethods(): Promise<PaymentMethod[]> {
  return (await apiGet<{items: PaymentMethod[]}>('/settings/payment-methods'))
    .items;
}

export function createPaymentMethod(
  data: NewPaymentMethodPayload,
): Promise<PaymentMethod> {
  return apiPost<PaymentMethod>('/payment-methods', data);
}

export function updatePaymentMethod(
  id: string,
  data: PaymentMethodPayload,
): Promise<PaymentMethod> {
  return apiPut<PaymentMethod>(`/payment-methods/${id}`, data);
}

export function setPaymentMethodActive(
  id: string,
  active: boolean,
): Promise<PaymentMethod> {
  return apiPost<PaymentMethod>(
    `/payment-methods/${id}/${active ? 'activate' : 'deactivate'}`,
    {},
  );
}

export function deletePaymentMethod(id: string): Promise<null> {
  return apiDelete(`/payment-methods/${id}`);
}
