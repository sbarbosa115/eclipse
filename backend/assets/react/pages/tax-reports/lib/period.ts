import type {Translate} from '@/shared/i18n';
import {formatMoney} from '@/shared/lib';
import type {SelectOption} from '@/shared/ui';

// The periods of the tax reports are whole months (YYYY-MM), as the API takes them; Colombia's returns are monthly,
// bimonthly or cuatrimestral, so a period is a range of months.


/** How far back the month pickers reach: two years of returns. */
export const MONTHS_OFFERED = 24;

/** The longest range the API answers. */
export const MAX_MONTHS = 12;

const parts = (month: string): [number, number] => {
  const [y, m] = month.split('-').map(Number);
  return [y ?? 1970, m ?? 1];
};

/** "2026-11" → "noviembre 2026". */
export function monthLabel(t: Translate, month: string): string {
  const [y, m] = parts(month);
  return t('taxReports.period.month', {month: t(`taxReports.months.${m}`), year: y});
}

/** The month of a day ("2026-11-07" → "2026-11"). */
export function monthOf(isoDate: string): string {
  return isoDate.slice(0, 7);
}

/** How many months from `from` to `to`, both included; 0 when `to` is before `from`. */
export function monthsBetween(from: string, to: string): number {
  const [fy, fm] = parts(from);
  const [ty, tm] = parts(to);
  return Math.max(0, (ty - fy) * 12 + (tm - fm) + 1);
}

/**
 * The months a picker offers, newest first: the month of `today` and the ones before it. A month already chosen (from a
 * link) that is older stays in the list, at its end.
 */
export function monthOptions(
  t: Translate,
  today: string,
  keep?: string,
): SelectOption[] {
  let [y, m] = parts(monthOf(today));
  const options: SelectOption[] = [];
  for (let i = 0; i < MONTHS_OFFERED; i++) {
    const value = `${y}-${String(m).padStart(2, '0')}`;
    options.push({value, label: monthLabel(t, value)});
    m -= 1;
    if (m === 0) {
      m = 12;
      y -= 1;
    }
  }
  if (keep && !options.some((o) => o.value === keep)) {
    options.push({value: keep, label: monthLabel(t, keep)});
  }
  return options;
}

/** "2.5000" → "2,5 %"; a per-unit tax shows its value per unit. Display only. */
export function formatRate(
  t: Translate,
  calculation: string,
  rate: string,
): string {
  if (calculation !== 'percentage') {
    return t('taxReports.rate.perUnit', {value: formatMoney(rate)});
  }
  const trimmed = rate.includes('.')
    ? rate.replace(/0+$/, '').replace(/\.$/, '')
    : rate;
  return t('taxReports.rate.percentage', {value: trimmed.replace('.', ',')});
}

/** The first and last day of a month, as the document lists filter by dates. */
export function monthDays(month: string): {from: string; to: string} {
  const [y, m] = parts(month);
  const last = new Date(Date.UTC(y, m, 0)).getUTCDate();
  return {from: `${month}-01`, to: `${month}-${String(last).padStart(2, '0')}`};
}
