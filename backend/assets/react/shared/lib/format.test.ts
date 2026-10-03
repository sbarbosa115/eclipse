import {formatDate, formatMoney, formatNit} from './format';

describe('format', () => {
  it('shows pesos with thousands separators and two decimals', () => {
    expect(formatMoney('1190000.00')).toBe('$ 1.190.000,00');
    expect(formatMoney(null)).toBe('');
  });

  it('shows dates as DD/MM/YYYY', () => {
    expect(formatDate('2026-10-03')).toBe('03/10/2026');
  });

  it('joins a NIT and its check digit', () => {
    expect(formatNit('900123456', '8')).toBe('900123456-8');
    expect(formatNit('1020304050')).toBe('1020304050');
  });
});
