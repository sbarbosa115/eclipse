import {formatDecimalInput, parseAmount, parseDecimal} from './amount';

describe('reading an amount as a Colombian types it', () => {
  it.each([
    ['595000', '595000.00'],
    ['595000.5', '595000.50'],
    ['595000,5', '595000.50'],
    ['595000.50', '595000.50'],
    ['595.000', '595000.00'],
    ['1.190.000,50', '1190000.50'],
    ['1.190.000,00', '1190000.00'],
    ['$ 1.190.000', '1190000.00'],
    [' 0012 ', '12.00'],
    ['0,01', '0.01'],
  ])('%s is %s pesos', (text, value) => {
    expect(parseAmount(text)).toBe(value);
  });

  it.each(['', 'abc', '1,234,5', '12.345.6', '-5', '1.5.5', '10,123', '1e5'])(
    '%s is not an amount',
    (text) => {
      expect(parseAmount(text)).toBeNull();
    },
  );
});

describe('a decimal with a given number of places (unit prices, rates)', () => {
  it.each([
    ['42016,8067', 4, '42016.8067'],
    ['42.016,8067', 4, '42016.8067'],
    ['2,5', 4, '2.5'],
    ['2.5', 4, '2.5'],
    ['595000,5', 2, '595000.5'],
    ['1.190.000', 2, '1190000'],
    ['0019', 4, '19'],
    ['0.966', 4, '0.966'],
    ['0,966', 4, '0.966'],
  ])(
    '%s with %i places is %s, as typed with a point',
    (text, places, value) => {
      expect(parseDecimal(text, places)).toBe(value);
    },
  );

  it('refuses more places than allowed', () => {
    expect(parseDecimal('1,23456', 4)).toBeNull();
    expect(parseDecimal('1,234', 2)).toBeNull();
  });
});

describe('showing a decimal the way it is typed', () => {
  it.each([
    ['1190000.50', '1.190.000,50'],
    ['1190000.00', '1.190.000'],
    ['49999.9000', '49.999,90'],
    ['42016.8067', '42.016,8067'],
    ['19.0000', '19'],
    ['2.5', '2,50'],
    ['595', '595'],
    ['', ''],
  ])('%s shows as %s', (decimal, shown) => {
    expect(formatDecimalInput(decimal)).toBe(shown);
  });

  it('leaves what is not a decimal as it is', () => {
    expect(formatDecimalInput('abc')).toBe('abc');
  });
});
