import type {Company, CompanyPayload} from '../api/companyApi';

export const IDENTIFICATION_TYPES = [
  'nit',
  'cc',
  'ce',
  'te',
  'ti',
  'rc',
  'pasaporte',
  'die',
  'pep',
  'nit_extranjero',
  'nuip',
  'ppt',
] as const;
export const VAT_REGIMES = ['responsable', 'no_responsable', 'simple'] as const;
export const RESPONSIBILITIES = [
  'O-13',
  'O-15',
  'O-23',
  'O-47',
  'R-99-PN',
] as const;

export interface CompanyForm {
  legal_name: string;
  trade_name: string;
  identification_type: string;
  identification_number: string;
  check_digit: string;
  address: string;
  city: string;
  phone: string;
  email: string;
  vat_regime: string;
  fiscal_responsibilities: string[];
  default_charge_tax_id: string;
  default_withholding_tax_id: string;
}

export function toForm(company: Company): CompanyForm {
  return {
    legal_name: company.legal_name,
    trade_name: company.trade_name ?? '',
    identification_type: company.identification_type,
    identification_number: company.identification_number,
    check_digit: company.check_digit ?? '',
    address: company.address ?? '',
    city: company.city ?? '',
    phone: company.phone ?? '',
    email: company.email ?? '',
    vat_regime: company.vat_regime,
    fiscal_responsibilities: company.fiscal_responsibilities,
    default_charge_tax_id: company.default_charge_tax_id ?? '',
    default_withholding_tax_id: company.default_withholding_tax_id ?? '',
  };
}

const blank = (value: string): string | null =>
  value.trim() === '' ? null : value.trim();

/** An empty optional field is null; the check digit is left empty for the server to compute it. */
export function toPayload(form: CompanyForm): CompanyPayload {
  return {
    legal_name: form.legal_name.trim(),
    trade_name: blank(form.trade_name),
    identification_type: form.identification_type,
    identification_number: form.identification_number.trim(),
    check_digit:
      form.identification_type === 'nit' ? blank(form.check_digit) : null,
    address: blank(form.address),
    city: blank(form.city),
    phone: blank(form.phone),
    email: blank(form.email),
    vat_regime: form.vat_regime,
    fiscal_responsibilities: form.fiscal_responsibilities,
    default_charge_tax_id: blank(form.default_charge_tax_id),
    default_withholding_tax_id: blank(form.default_withholding_tax_id),
  };
}

export const LOGO_MAX_BYTES = 2 * 1024 * 1024;
export const LOGO_TYPES = ['image/png', 'image/jpeg'];
