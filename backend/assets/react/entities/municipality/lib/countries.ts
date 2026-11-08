/** ISO 3166-1 alpha-2 codes offered for País: Colombia first, then the countries a Colombian company deals with. */
export const COUNTRY_CODES = [
  'CO',
  'AR',
  'BO',
  'BR',
  'CL',
  'CR',
  'CU',
  'DO',
  'EC',
  'SV',
  'GT',
  'HN',
  'MX',
  'NI',
  'PA',
  'PY',
  'PE',
  'PR',
  'UY',
  'VE',
  'US',
  'CA',
  'ES',
  'PT',
  'FR',
  'DE',
  'IT',
  'NL',
  'BE',
  'CH',
  'AT',
  'GB',
  'IE',
  'SE',
  'NO',
  'DK',
  'FI',
  'PL',
  'TR',
  'CN',
  'JP',
  'KR',
  'IN',
  'AE',
  'IL',
  'SA',
  'SG',
  'HK',
  'TW',
  'TH',
  'VN',
  'ID',
  'MY',
  'PH',
  'AU',
  'NZ',
  'ZA',
  'EG',
  'MA',
  'RU',
] as const;

let names: Intl.DisplayNames | null | undefined;

/** The country's name in Spanish ("Estados Unidos"); its code when the runtime cannot say. */
export function countryName(code: string): string {
  if (names === undefined) {
    try {
      names = new Intl.DisplayNames(['es-CO'], {type: 'region'});
    } catch {
      names = null;
    }
  }
  try {
    return names?.of(code) ?? code;
  } catch {
    return code;
  }
}
