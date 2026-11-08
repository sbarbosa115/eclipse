import type {Page} from '@playwright/test';
import {pickMunicipality} from './support/municipality';
import {consoleErrors, expect, test} from './support/test';

// Cases CIU-01 – 09 of docs/tests/ui-regression.md (observations 2026-11, item 4 terceros-city). Each test signs up its
// own company (`newCompany()` in ./support/test). CIU-06, 07 and 09 are run by hand (they need a tercero that predates
// the release, a document form and a tablet).

async function fillCompany(page: Page, name: string, nit = '800197268') {
  await page.goto('/terceros/nuevo');
  await page.getByLabel('Número de identificación').fill(nit);
  await page.getByLabel('Razón social').fill(name);
  await page.getByRole('checkbox', {name: 'Cliente', exact: true}).check();
}

test('CIU-01 · a Colombian tercero asks for its municipio before saving', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await fillCompany(page, 'Andina S.A.S.');

  await page.getByRole('button', {name: 'Crear tercero'}).click();

  await expect(page.getByText('Elige el municipio.')).toBeVisible();
  await expect(page).toHaveURL(/\/terceros\/nuevo$/);
  expect(errors).toEqual([]);
});

test('CIU-02 · the municipio is chosen by name and the list shows its city', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await fillCompany(page, 'Andina S.A.S.');

  await pickMunicipality(page);
  await expect(page.getByLabel('Municipio')).toHaveValue(
    'Antioquia › Medellín (05001)',
  );
  await page.getByRole('button', {name: 'Crear tercero'}).click();

  await expect(page.getByText('Tercero Andina S.A.S. creado.')).toBeVisible();
  await expect(page.getByRole('row', {name: /Andina/})).toContainText(
    'MEDELLÍN',
  );
  await expect(page.getByText(/sin municipio/)).toHaveCount(0);
});

test('CIU-03 · the municipio is also found by its code', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await fillCompany(page, 'Andina S.A.S.');

  await page.getByLabel('Municipio').fill('11001');

  await expect(
    page.getByRole('option', {
      name: /Bogotá, D\.C\. › Bogotá, D\.C\. \(11001\)/,
    }),
  ).toBeVisible();
});

test('CIU-04 · a tercero abroad has a city instead of a municipio', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await fillCompany(page, 'Acme Inc.');

  await page.getByLabel('País').selectOption('US');
  await expect(page.getByLabel('Municipio')).toHaveCount(0);
  await page.getByRole('button', {name: 'Crear tercero'}).click();
  await expect(page.getByText('Escribe la ciudad.')).toBeVisible();

  await page.getByLabel('Ciudad').fill('Miami');
  await page.getByRole('button', {name: 'Crear tercero'}).click();
  await expect(page.getByText('Tercero Acme Inc. creado.')).toBeVisible();
  await expect(page.getByRole('row', {name: /Acme/})).toContainText('Miami');
});

test('CIU-05 · an edited tercero reopens with its municipio and can change it', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await fillCompany(page, 'Andina S.A.S.');
  await pickMunicipality(page);
  await page.getByRole('button', {name: 'Crear tercero'}).click();
  await expect(page.getByText('Tercero Andina S.A.S. creado.')).toBeVisible();

  await page.getByRole('link', {name: 'Editar'}).click();
  await expect(page.getByLabel('Municipio')).toHaveValue(
    'Antioquia › Medellín (05001)',
  );
  await page.getByLabel('Municipio').fill('11001');
  await page.getByRole('option', {name: /\(11001\)/}).click();
  await page.getByRole('button', {name: 'Guardar tercero'}).click();
  await expect(page.getByText('Cambios guardados.')).toBeVisible();

  await page.goto('/terceros');
  await expect(page.getByRole('row', {name: /Andina/})).toContainText(
    'BOGOTÁ, D.C.',
  );
});

test('CIU-08 · the company profile asks for its municipio on the next save', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await page.goto('/configuracion?tab=company');
  await expect(page.getByLabel('Razón social')).toBeVisible();

  await page.getByRole('button', {name: 'Guardar cambios'}).click();
  await expect(page.getByText('Elige el municipio.')).toBeVisible();

  await pickMunicipality(page);
  await page.getByRole('button', {name: 'Guardar cambios'}).click();
  await expect(
    page.getByText('Guardamos los datos de la empresa.'),
  ).toBeVisible();
  await page.reload();
  await expect(page.getByLabel('Municipio')).toHaveValue(
    'Antioquia › Medellín (05001)',
  );
});
