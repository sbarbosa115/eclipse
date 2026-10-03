import {apiGet, apiPost, apiPut, type Schema} from '@/shared/api';

export type ResolutionSettings = Schema<'ResolutionSettingsOutput'>;
export type ResolutionStatus = Schema<'ResolutionStatusOutput'>;
export type NumberingSeries = Schema<'NumberingSeriesOutput'>;

/** What the resolution form sends. Dates are "YYYY-MM-DD". */
export interface ResolutionPayload {
  resolution_number: string;
  prefix: string;
  range_from: number;
  range_to: number;
  valid_from: string;
  valid_to: string;
  mode: string;
}

export const getResolution = (): Promise<ResolutionSettings> =>
  apiGet<ResolutionSettings>('/company/resolution');

export const createResolution = (
  data: ResolutionPayload,
): Promise<ResolutionSettings> =>
  apiPost<ResolutionSettings>('/company/resolution', data);

export const updateResolution = (
  data: ResolutionPayload,
): Promise<ResolutionSettings> =>
  apiPut<ResolutionSettings>('/company/resolution', data);

export const updateWarnings = (
  warningNumbers: number,
  warningDays: number,
): Promise<ResolutionSettings> =>
  apiPut<ResolutionSettings>('/company/resolution/warnings', {
    warning_numbers: warningNumbers,
    warning_days: warningDays,
  });

export const confirmManualInvoicing = (): Promise<ResolutionSettings> =>
  apiPost<ResolutionSettings>('/company/manual-invoicing-confirmation', {});

export async function listNumbering(): Promise<NumberingSeries[]> {
  return (await apiGet<{items: NumberingSeries[]}>('/company/numbering')).items;
}

export const updateNumbering = (
  kind: string,
  prefix: string,
  nextNumber: number,
): Promise<NumberingSeries> =>
  apiPut<NumberingSeries>(`/company/numbering/${kind}`, {
    prefix,
    next_number: nextNumber,
  });
