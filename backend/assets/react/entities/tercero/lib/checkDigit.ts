const WEIGHTS = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];

/**
 * The dígito de verificación of a NIT by the DIAN's algorithm, the same as the server's (Shared\Domain\Fiscal\
 * CheckDigit), so the form can show it while the person types. Null while the number is not a NIT (no digits, or more
 * than 15). The server computes it again when it is not sent.
 */
export function checkDigitOf(nit: string): string | null {
  const digits = nit.replace(/\D/g, '');
  if (digits === '' || digits.length > WEIGHTS.length) return null;
  let sum = 0;
  [...digits].reverse().forEach((digit, i) => {
    sum += Number(digit) * (WEIGHTS[i] ?? 0);
  });
  const remainder = sum % 11;
  return String(remainder > 1 ? 11 - remainder : remainder);
}
