import {apiGet, apiPost, apiPut, type Schema} from '@/shared/api';
import {usesOf, type AccountUse} from '../lib/uses';

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
  /** Only for the first auxiliar of an account that takes movements: move what points at it (default true). */
  move_references?: boolean;
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

/**
 * What points at each account: the posting rules, the taxes and the payment methods, as the settings tabs list them.
 * For the warning before subdividing an account and the "Sin auxiliar por defecto" badge.
 */
export function fetchUses(): Promise<AccountUse[]> {
  return Promise.all([
    apiGet<{items: Schema<'PostingRuleOutput'>[]}>('/posting-rules'),
    apiGet<{items: Schema<'TaxSettingOutput'>[]}>('/settings/taxes'),
    apiGet<{items: Schema<'PaymentMethodSettingOutput'>[]}>(
      '/settings/payment-methods',
    ),
  ]).then(([rules, taxes, methods]) =>
    usesOf(rules.items, taxes.items, methods.items),
  );
}
