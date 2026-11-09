import {apiGet, type Schema} from '@/shared/api';

export type WithholdingSide = 'practiced' | 'suffered';
export type WithholdingKind = 'retefuente' | 'reteiva' | 'reteica';
export type WithholdingReport = Schema<'WithholdingReportOutput'>;
export type WithholdingGroup = Schema<'WithholdingReportGroupOutput'>;
export type WithholdingRow = Schema<'WithholdingReportRowOutput'>;
export type WithholdingKindTotal = Schema<'WithholdingKindTotalOutput'>;
export type VatReport = Schema<'VatReportOutput'>;
export type VatRateRow = Schema<'VatRateRowOutput'>;

export interface Period {
  from: string;
  to: string;
}

const query = (params: Record<string, string | undefined>) => {
  const search = new URLSearchParams();
  for (const [name, value] of Object.entries(params)) {
    if (value) search.set(name, value);
  }
  const text = search.toString();
  return text === '' ? '' : `?${text}`;
};

export const withholdingsPath = (side: WithholdingSide) =>
  `/reports/taxes/withholdings/${side}`;
export const VAT_PATH = '/reports/taxes/vat';

export function fetchWithholdings(
  side: WithholdingSide,
  period: Period,
  kind?: WithholdingKind,
) {
  return apiGet<WithholdingReport>(
    `${withholdingsPath(side)}${query({...period, kind})}`,
  );
}

export function fetchVat(period: Period) {
  return apiGet<VatReport>(`${VAT_PATH}${query({...period})}`);
}
