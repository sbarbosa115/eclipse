import type {Locator, Page} from '@playwright/test';

/** Types in the Municipio box of the form (or modal) in `scope` and picks Medellín, Antioquia (05001). */
export async function pickMunicipality(scope: Page | Locator): Promise<void> {
  await scope.getByLabel('Municipio').fill('Medell');
  await scope.getByRole('option', {name: /Medellín \(05001\)/}).click();
}
