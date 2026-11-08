import {useSearchParams} from 'react-router-dom';
import {useTranslation} from '@/shared/i18n';
import {todayInColombia} from '@/shared/lib';
import type {Filter} from '@/shared/ui';
import {EXOGENA_FORMATS, type ExogenaFormat} from '../api/exogenaApi';

/** How many taxable years the picker offers, besides the current one. */
export const YEARS_OFFERED = 5;

const AMOUNT = /^\d{1,15}(\.\d{1,2})?$/;

/**
 * The exógena's format, taxable year and cuantías menores threshold, kept in the address (?format=1001&year=2025
 * &threshold=100000) so a reload or a link keeps them. The year defaults to the one that ended: the return is filed for
 * it.
 */
export function useExogenaFilters() {
  const {t} = useTranslation();
  const [params, setParams] = useSearchParams();
  const current = Number(todayInColombia().slice(0, 4));
  const years = Array.from({length: YEARS_OFFERED + 1}, (_, i) =>
    String(current - i),
  );

  const asked = params.get('format') ?? '';
  const format: ExogenaFormat = EXOGENA_FORMATS.includes(asked as ExogenaFormat)
    ? (asked as ExogenaFormat)
    : '1001';
  const askedYear = params.get('year') ?? '';
  const year = years.includes(askedYear) ? askedYear : String(current - 1);
  const askedThreshold = params.get('threshold') ?? '';
  const threshold = AMOUNT.test(askedThreshold) ? askedThreshold : '';

  const change = (name: string, value: string) => {
    const next = new URLSearchParams(params);
    if (value) next.set(name, value);
    else next.delete(name);
    setParams(next, {replace: true});
  };

  const filters: Filter[] = [
    {
      name: 'year',
      label: t('taxReports.exogena.year'),
      value: year,
      onChange: (value) => change('year', value),
      options: years.map((value) => ({value, label: value})),
    },
  ];

  return {
    format,
    year,
    threshold,
    filters,
    setFormat: (value: ExogenaFormat) => change('format', value),
    /** A decimal string, '' to report everyone; anything else is refused (false). */
    setThreshold: (value: string): boolean => {
      if (value !== '' && !AMOUNT.test(value)) return false;
      change('threshold', /^0*(\.0*)?$/.test(value) ? '' : value);
      return true;
    },
  };
}
