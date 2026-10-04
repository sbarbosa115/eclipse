import {formatDate, formatMoney} from '@/shared/lib';
import type {Tax} from '../api/taxSettingsApi';

/** "19.0000" → "19 %", "2.5000" → "2,5 %": a rate as a Colombian writes it (display only). */
export function formatRate(rate: string): string {
  const [whole = '0', decimals = ''] = rate.split('.');
  const trimmed = decimals.replace(/0+$/, '');
  return `${whole}${trimmed === '' ? '' : `,${trimmed}`} %`;
}

/** A tax's rate as the table shows it: a percentage, or a value per unit in pesos. */
export function describeRate(tax: Pick<Tax, 'calculation' | 'rate'>): string {
  return tax.calculation === 'per_unit'
    ? formatMoney(tax.rate)
    : formatRate(tax.rate);
}

export interface ValidityText {
  key: 'always' | 'from' | 'until' | 'range';
  params: Record<string, string>;
}

export function describeValidity(
  tax: Pick<Tax, 'valid_from' | 'valid_to'>,
): ValidityText {
  const from = formatDate(tax.valid_from);
  const to = formatDate(tax.valid_to);
  if (from && to) return {key: 'range', params: {from, to}};
  if (from) return {key: 'from', params: {date: from}};
  if (to) return {key: 'until', params: {date: to}};
  return {key: 'always', params: {}};
}
