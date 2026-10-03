// Exact decimal arithmetic for the totals preview: an integer (BigInt) and a scale, so 0.1 + 0.2 is 0.3. Money is
// never a float (CLAUDE.md); this mirrors what brick/math does on the server, for the few operations the preview
// needs.
const PATTERN = /^(-)?(\d*)(?:\.(\d+))?$/;

function pow10(n: number): bigint {
  return 10n ** BigInt(n);
}

export class Decimal {
  private constructor(
    /** The value times 10^scale. */
    readonly units: bigint,
    readonly scale: number,
  ) {}

  static readonly ZERO = new Decimal(0n, 0);

  /** "1190000.00" → the decimal; null for anything that is not plain digits with an optional point. */
  static parse(text: string | null | undefined): Decimal | null {
    const match = PATTERN.exec((text ?? '').trim());
    if (!match) return null;
    const [, sign, whole = '', fraction = ''] = match;
    if (whole === '' && fraction === '') return null;
    const units = BigInt(`${whole || '0'}${fraction}`);
    return new Decimal(sign ? -units : units, fraction.length);
  }

  /** Like parse, for values known to be valid (constants, API decimals): throws otherwise. */
  static of(text: string): Decimal {
    const value = Decimal.parse(text);
    if (!value) throw new Error(`"${text}" is not a decimal.`);
    return value;
  }

  /** The sum of many; zero for none. */
  static sum(values: ReadonlyArray<Decimal>): Decimal {
    return values.reduce((a, b) => a.plus(b), Decimal.ZERO);
  }

  private at(scale: number): bigint {
    return this.units * pow10(scale - this.scale);
  }

  plus(other: Decimal): Decimal {
    const scale = Math.max(this.scale, other.scale);
    return new Decimal(this.at(scale) + other.at(scale), scale);
  }

  minus(other: Decimal): Decimal {
    const scale = Math.max(this.scale, other.scale);
    return new Decimal(this.at(scale) - other.at(scale), scale);
  }

  times(other: Decimal): Decimal {
    return new Decimal(this.units * other.units, this.scale + other.scale);
  }

  /** The value divided by 100, exact: 19 → 0.19. */
  percent(): Decimal {
    return new Decimal(this.units, this.scale + 2);
  }

  /** Rounded to `scale` places, a half away from zero (brick/math's RoundingMode::HalfUp). */
  roundHalfUp(scale: number): Decimal {
    if (scale >= this.scale) return new Decimal(this.at(scale), scale);
    const divisor = pow10(this.scale - scale);
    const negative = this.units < 0n;
    const magnitude = negative ? -this.units : this.units;
    let quotient = magnitude / divisor;
    if ((magnitude % divisor) * 2n >= divisor) quotient += 1n;
    return new Decimal(negative ? -quotient : quotient, scale);
  }

  /** Rounded to `scale` places toward minus infinity (RoundingMode::Floor). */
  floor(scale: number): Decimal {
    if (scale >= this.scale) return new Decimal(this.at(scale), scale);
    const divisor = pow10(this.scale - scale);
    let quotient = this.units / divisor;
    if (this.units < 0n && this.units % divisor !== 0n) quotient -= 1n;
    return new Decimal(quotient, scale);
  }

  /** -1, 0 or 1. */
  compare(other: Decimal): number {
    const scale = Math.max(this.scale, other.scale);
    const a = this.at(scale);
    const b = other.at(scale);
    return a === b ? 0 : a < b ? -1 : 1;
  }

  isZero(): boolean {
    return this.units === 0n;
  }

  isNegative(): boolean {
    return this.units < 0n;
  }

  /** The value in units of 10^-scale (cents for 2), when it has no more places than that. */
  toUnits(scale: number): bigint {
    return this.floor(scale).units;
  }

  /** How many decimals the value really has: 1.2300 has two. */
  decimalsUsed(): number {
    let {units, scale} = this as {units: bigint; scale: number};
    while (scale > 0 && units % 10n === 0n) {
      units /= 10n;
      scale -= 1;
    }
    return scale;
  }

  /** The decimal string the API reads, with exactly `scale` places. */
  toString(): string {
    const negative = this.units < 0n;
    const digits = (negative ? -this.units : this.units)
      .toString()
      .padStart(this.scale + 1, '0');
    const whole = digits.slice(0, digits.length - this.scale);
    const fraction = digits.slice(digits.length - this.scale);
    return `${negative ? '-' : ''}${whole}${this.scale > 0 ? `.${fraction}` : ''}`;
  }
}
