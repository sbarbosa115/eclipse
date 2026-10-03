import {apiGet, apiPost, apiPut, type Schema} from '@/shared/api';

export type Account = Schema<'AccountOutput'>;

export interface AccountPage {
  items: Account[];
  total: number;
  page: number;
  per_page: number;
}

export interface ChartQuery {
  q: string;
  accountClass: string;
  page: number;
}

export function fetchChart({q, accountClass, page}: ChartQuery) {
  const params = new URLSearchParams({page: String(page), per_page: '50'});
  if (q) params.set('q', q);
  if (accountClass) params.set('class', accountClass);
  return apiGet<AccountPage>(`/accounts?${params.toString()}`);
}

export interface NewAccount {
  parent_code: string;
  code: string;
  name: string;
  usable_on_purchases: boolean;
}

export function addAccount(data: NewAccount) {
  return apiPost<Account>('/accounts', data);
}

export interface AccountChanges {
  name: string;
  active: boolean;
  usable_on_purchases: boolean;
}

export function updateAccount(id: string, data: AccountChanges) {
  return apiPut<Account>(`/accounts/${id}`, data);
}
