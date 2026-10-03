import {apiGet, apiPut, ApiError, type Schema} from '@/shared/api';

export type Company = Schema<'CompanyOutput'>;
export type TaxChoice = Schema<'TaxOutput'>;

/** What the profile form sends (snake_case, like the API). */
export interface CompanyPayload {
  legal_name: string;
  trade_name: string | null;
  identification_type: string;
  identification_number: string;
  check_digit: string | null;
  address: string | null;
  city: string | null;
  phone: string | null;
  email: string | null;
  vat_regime: string;
  fiscal_responsibilities: string[];
  default_charge_tax_id: string | null;
  default_withholding_tax_id: string | null;
}

export function getCompany(): Promise<Company> {
  return apiGet<Company>('/company');
}

export function updateCompany(data: CompanyPayload): Promise<Company> {
  return apiPut<Company>('/company', data);
}

/** Every tax, inactive too: the current default may have been deactivated since it was chosen. */
export async function listTaxChoices(): Promise<TaxChoice[]> {
  return (await apiGet<{items: TaxChoice[]}>('/taxes?all=1')).items;
}

/** Where the logo image is served; `version` (the attachment id) makes the browser reload a replaced one. */
export const logoUrl = (version: string): string =>
  `/api/v1/company/logo?v=${encodeURIComponent(version)}`;

/** The shared client speaks JSON only: a file goes as multipart, with the same error shape on failure. */
export async function uploadLogo(file: File): Promise<Company> {
  const form = new FormData();
  form.append('file', file);
  const response = await fetch('/api/v1/company/logo', {
    method: 'POST',
    headers: {Accept: 'application/json'},
    body: form,
  });
  const text = await response.text();
  const data: unknown = text === '' ? null : JSON.parse(text);
  if (!response.ok) {
    const error = (data ?? {}) as {error?: string; message?: string};
    throw new ApiError(
      response.status,
      error.error ?? 'http_error',
      error.message ?? response.statusText,
      data,
    );
  }
  return data as Company;
}

export async function removeLogo(): Promise<void> {
  const response = await fetch('/api/v1/company/logo', {
    method: 'DELETE',
    headers: {Accept: 'application/json'},
  });
  if (!response.ok) {
    throw new ApiError(
      response.status,
      'http_error',
      response.statusText,
      null,
    );
  }
}
