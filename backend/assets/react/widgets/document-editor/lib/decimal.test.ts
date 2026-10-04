import {Decimal} from './decimal';

describe('exact decimals for the totals preview', () => {
  it('reads the API’s decimal strings and refuses anything else', () => {
    expect(Decimal.parse('1190000.00')?.toString()).toBe('1190000.00');
    expect(Decimal.parse(' 12.5 ')?.toString()).toBe('12.5');
    expect(Decimal.parse('-3')?.toString()).toBe('-3');
    expect(Decimal.parse('.5')?.toString()).toBe('0.5');
    expect(Decimal.parse('12,5'), 'a comma is not a decimal point').toBeNull();
    expect(Decimal.parse('1e3'), 'no exponents').toBeNull();
    expect(Decimal.parse('')).toBeNull();
  });

  it('adds and multiplies without the binary errors of floats', () => {
    const sum = Decimal.of('0.1').plus(Decimal.of('0.2'));
    expect(sum.toString(), '0.1 + 0.2 is 0.3, not 0.30000000000000004').toBe(
      '0.3',
    );
    expect(Decimal.of('1.15').times(Decimal.of('100')).toString()).toBe(
      '115.00',
    );
    expect(Decimal.of('2').minus(Decimal.of('0.75')).toString()).toBe('1.25');
  });

  it('divides by 100 exactly (a percentage)', () => {
    expect(Decimal.of('19').percent().toString()).toBe('0.19');
    expect(Decimal.of('0.3333').percent().toString()).toBe('0.003333');
  });

  it('rounds half up, away from zero, like brick/math’s HalfUp', () => {
    expect(Decimal.of('0.125').roundHalfUp(2).toString()).toBe('0.13');
    expect(Decimal.of('0.124999').roundHalfUp(2).toString()).toBe('0.12');
    expect(Decimal.of('-0.125').roundHalfUp(2).toString()).toBe('-0.13');
    expect(Decimal.of('0.9999').roundHalfUp(2).toString()).toBe('1.00');
    expect(Decimal.of('7').roundHalfUp(2).toString()).toBe('7.00');
  });

  it('floors toward minus infinity', () => {
    expect(Decimal.of('0.3333').floor(2).toString()).toBe('0.33');
    expect(Decimal.of('-0.3333').floor(2).toString()).toBe('-0.34');
  });

  it('compares values of different scales', () => {
    expect(Decimal.of('1.50').compare(Decimal.of('1.5'))).toBe(0);
    expect(Decimal.of('2').compare(Decimal.of('1.999'))).toBe(1);
    expect(Decimal.of('-1').compare(Decimal.of('0'))).toBe(-1);
    expect(Decimal.of('0.00').isZero()).toBe(true);
  });

  it('counts cents for the largest-remainder split', () => {
    expect(Decimal.of('0.01').toUnits(2)).toBe(1n);
    expect(Decimal.of('1.00').minus(Decimal.of('0.99')).toUnits(2)).toBe(1n);
  });

  it('knows how many decimals were written', () => {
    expect(Decimal.of('1.23456').scale).toBe(5);
    expect(Decimal.of('1.2300').decimalsUsed()).toBe(2);
  });
});
