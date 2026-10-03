import {checkDigitOf} from './checkDigit';

describe('the dígito de verificación of a NIT', () => {
  it.each([
    ['800197268', '4'],
    ['890903938', '8'],
    ['899999068', '1'],
    ['900000009', '0'],
    ['900000002', '1'],
    ['800.197.268', '4'],
  ])('NIT %s has DV %s, as on the server', (nit, dv) => {
    expect(checkDigitOf(nit)).toBe(dv);
  });

  it('has none while there is nothing to compute it from', () => {
    expect(checkDigitOf('')).toBeNull();
    expect(checkDigitOf('abc')).toBeNull();
    expect(checkDigitOf('1234567890123456')).toBeNull();
  });
});
