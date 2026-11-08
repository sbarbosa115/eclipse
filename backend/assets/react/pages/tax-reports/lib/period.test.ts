import {translator} from '@/shared/i18n';
import {
  formatRate,
  monthDays,
  monthLabel,
  monthOptions,
  monthsBetween,
} from './period';

const t = translator();

describe('tax report periods', () => {
  it('offers this month and the 23 before it, newest first', () => {
    const options = monthOptions(t, '2026-11-07');
    expect(options).toHaveLength(24);
    expect(options[0]).toEqual({value: '2026-11', label: 'noviembre 2026'});
    expect(options[10]?.value).toBe('2026-01');
    expect(options[11]).toEqual({value: '2025-12', label: 'diciembre 2025'});
    expect(options[23]?.value).toBe('2024-12');
  });

  it('keeps a month chosen by link in the list even when older', () => {
    const options = monthOptions(t, '2026-11-07', '2023-05');
    expect(options.at(-1)).toEqual({value: '2023-05', label: 'mayo 2023'});
  });

  it('names a month in Spanish', () => {
    expect(monthLabel(t, '2026-01')).toBe('enero 2026');
    expect(monthLabel(t, '2026-09')).toBe('septiembre 2026');
  });

  it('counts the months of a range, both ends included', () => {
    expect(monthsBetween('2026-11', '2026-11')).toBe(1);
    expect(monthsBetween('2026-09', '2026-10')).toBe(2);
    expect(monthsBetween('2025-12', '2026-11')).toBe(12);
    expect(monthsBetween('2026-11', '2026-10')).toBe(0);
  });

  it('prints a rate as the accountant reads it', () => {
    expect(formatRate(t, 'percentage', '2.5000')).toBe('2,5 %');
    expect(formatRate(t, 'percentage', '0.9660')).toBe('0,966 %');
    expect(formatRate(t, 'percentage', '19.0000')).toBe('19 %');
    expect(formatRate(t, 'percentage', '0.0000')).toBe('0 %');
    expect(formatRate(t, 'per_unit', '50.0000')).toBe('$ 50,00 por unidad');
  });
});

describe('monthDays', () => {
  it('spans a month from its first to its last day', () => {
    expect(monthDays('2026-02')).toEqual({
      from: '2026-02-01',
      to: '2026-02-28',
    });
    expect(monthDays('2024-02')).toEqual({
      from: '2024-02-01',
      to: '2024-02-29',
    });
    expect(monthDays('2026-11')).toEqual({
      from: '2026-11-01',
      to: '2026-11-30',
    });
  });
});
