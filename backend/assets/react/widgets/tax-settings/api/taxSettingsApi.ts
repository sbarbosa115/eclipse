import {apiDelete, apiGet, apiPost, apiPut, type Schema} from '@/shared/api';

export type Tax = Schema<'TaxSettingOutput'>;

/** What the create and edit forms send (the class and kind are sent on create only). */
export interface TaxPayload {
  name: string;
  calculation: string;
  rate: string;
  sales_account_id: string | null;
  purchase_account_id: string | null;
  valid_from: string | null;
  valid_to: string | null;
}

export interface NewTaxPayload extends TaxPayload {
  tax_class: string;
  kind: string;
}

export async function listTaxes(): Promise<Tax[]> {
  return (await apiGet<{items: Tax[]}>('/settings/taxes')).items;
}

export function createTax(data: NewTaxPayload): Promise<Tax> {
  return apiPost<Tax>('/taxes', data);
}

export function updateTax(id: string, data: TaxPayload): Promise<Tax> {
  return apiPut<Tax>(`/taxes/${id}`, data);
}

export function setTaxActive(id: string, active: boolean): Promise<Tax> {
  return apiPost<Tax>(`/taxes/${id}/${active ? 'activate' : 'deactivate'}`, {});
}

export function deleteTax(id: string): Promise<null> {
  return apiDelete(`/taxes/${id}`);
}
