import {apiGet, type Schema} from '@/shared/api';

export type Municipality = Schema<'MunicipalityOutput'>;
export type Department = Schema<'DepartmentOutput'>;

/** DANE's DIVIPOLA: municipalities whose name contains `q` or whose code starts with it (at most 50). */
export async function searchMunicipalities(
  q: string,
  department = '',
): Promise<Municipality[]> {
  const params = new URLSearchParams({q});
  if (department) params.set('department', department);
  return (
    await apiGet<{items: Municipality[]}>(`/geo/municipalities?${params}`)
  ).items;
}

export async function fetchDepartments(): Promise<Department[]> {
  return (await apiGet<{items: Department[]}>('/geo/departments')).items;
}
