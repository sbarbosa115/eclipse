import {useSearchParams} from 'react-router-dom';
import {useTranslation} from '@/shared/i18n';
import {todayInColombia} from '@/shared/lib';
import type {Filter} from '@/shared/ui';
import {MAX_MONTHS, monthOf, monthOptions, monthsBetween} from '../lib/period';

const MONTH = /^\d{4}-(0[1-9]|1[0-2])$/;

/**
 * The period of a tax report, kept in the address (?from=YYYY-MM&to=YYYY-MM, default this month in Colombia) so a
 * reload or a link keeps it, with its two month pickers for the FilterBar and why a range cannot be asked for.
 */
export function usePeriod() {
  const {t} = useTranslation();
  const [params, setParams] = useSearchParams();
  const current = monthOf(todayInColombia());
  const read = (name: string) => {
    const value = params.get(name) ?? '';
    return MONTH.test(value) ? value : current;
  };
  const from = read('from');
  const to = read('to');
  const months = monthsBetween(from, to);
  const problem =
    months === 0
      ? t('taxReports.period.backwards')
      : months > MAX_MONTHS
        ? t('taxReports.period.tooLong', {max: MAX_MONTHS})
        : null;

  const change = (name: string, value: string) => {
    const next = new URLSearchParams(params);
    if (value) next.set(name, value);
    else next.delete(name);
    setParams(next, {replace: true});
  };

  const filters: Filter[] = [
    {
      name: 'from',
      label: t('taxReports.period.from'),
      value: from,
      onChange: (value) => change('from', value),
      options: monthOptions(t, todayInColombia(), from),
    },
    {
      name: 'to',
      label: t('taxReports.period.to'),
      value: to,
      onChange: (value) => change('to', value),
      options: monthOptions(t, todayInColombia(), to),
    },
  ];

  return {from, to, problem, filters, params, change};
}
