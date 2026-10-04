import {apiGet, type Schema} from '@/shared/api';

export type Dashboard = Schema<'DashboardOutput'>;
export type ResolutionStatus = Schema<'ResolutionStatusOutput'>;

export function fetchDashboard(): Promise<Dashboard> {
  return apiGet<Dashboard>('/dashboard');
}

export function fetchResolutionStatus(): Promise<ResolutionStatus> {
  return apiGet<ResolutionStatus>('/company/resolution/status');
}
