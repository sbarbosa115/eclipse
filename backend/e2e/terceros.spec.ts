import {expect, test, consoleErrors} from './support/test';
import type {Page} from '@playwright/test';

// Cases TER-01 – 19 of docs/tests/ui-regression.md (item 5 "terceros"). The ones with a smoke line there run here.

async function createCompany(
  page: Page,
  name: string,
  nit: string,
  role = 'Cliente',
) {
  await page.goto('/terceros/nuevo');
  await page.getByLabel('Número de identificación').fill(nit);
  await page.getByLabel('Razón social').fill(name);
  await page.getByRole('checkbox', {name: role, exact: true}).check();
  await page.getByRole('button', {name: 'Crear tercero'}).click();
  await expect(page.getByText(`Tercero ${name} creado.`)).toBeVisible();
}

test('TER-01 · the first tercero is a company with its DV computed', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await page.goto('/terceros');
  await expect(page.getByText(/Aún no tienes terceros/)).toBeVisible();

  await page.getByRole('link', {name: 'Crear tercero'}).click();
  await page.getByLabel('Número de identificación').fill('800197268');
  await expect(page.getByText('Calculado: 4')).toBeVisible();
  await page.getByLabel('Razón social').fill('Distribuciones Andina S.A.S.');
  await page.getByRole('checkbox', {name: 'Cliente'}).check();
  await page.getByRole('button', {name: 'Agregar teléfono'}).click();
  await page.getByLabel('Número', {exact: true}).fill('6011234567');
  await page.getByRole('button', {name: 'Agregar contacto'}).click();
  await page.getByLabel('Nombre del contacto').fill('Pedro Ruiz');
  await page.getByRole('button', {name: 'Crear tercero'}).click();

  await expect(
    page.getByText('Tercero Distribuciones Andina S.A.S. creado.'),
  ).toBeVisible();
  const row = page.getByRole('row', {name: /Distribuciones Andina/});
  await expect(row).toContainText('NIT 800197268-4');
  await expect(row).toContainText('Cliente');
  expect(errors).toEqual([]);
});

test('TER-02 · a person has nombres and a cédula, with no DV', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await page.goto('/terceros/nuevo');

  await page.getByLabel('Tipo', {exact: true}).selectOption('persona');
  await page.getByLabel('Tipo de identificación').selectOption('cc');
  await expect(page.getByLabel('DV')).toHaveCount(0);
  await page.getByLabel('Número de identificación').fill('1020304050');
  await page.getByLabel('Nombres').fill('Ana María');
  await page.getByLabel('Apellidos').fill('Pérez Soto');
  await page.getByRole('checkbox', {name: 'Empleado'}).check();
  await page.getByRole('button', {name: 'Crear tercero'}).click();

  const row = page.getByRole('row', {name: /Ana María Pérez Soto/});
  await expect(row).toContainText('CC 1020304050');
  await expect(row).toContainText('Empleado');
});

test('TER-03 · the same identification twice is refused on its field', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await createCompany(page, 'Primera S.A.S.', '800197268');

  await page.goto('/terceros/nuevo');
  await page.getByLabel('Número de identificación').fill('800.197.268');
  await page.getByLabel('Razón social').fill('Segunda S.A.S.');
  await page.getByRole('checkbox', {name: 'Cliente'}).check();
  await page.getByRole('button', {name: 'Crear tercero'}).click();

  await expect(
    page.getByText('Ya existe un tercero con esta identificación.'),
  ).toBeVisible();
  await expect(page).toHaveURL(/\/terceros\/nuevo$/);
});

test('TER-04 · the form says what is missing before saving', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await page.goto('/terceros/nuevo');

  await page.getByRole('button', {name: 'Crear tercero'}).click();

  await expect(page.getByText('Este campo es obligatorio.')).toHaveCount(2);
  await expect(page.getByText('Elige al menos un rol.')).toBeVisible();
});

test('TER-05 · the list searches, filters and offers a way back', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await createCompany(page, 'Andina S.A.S.', '800197268', 'Cliente');
  await createCompany(page, 'Banco Fiable S.A.', '890903938', 'Otro');

  await page.goto('/terceros');
  await expect(page.getByRole('row')).toHaveCount(3);

  await page.getByLabel('Rol').selectOption('otro');
  await expect(page.getByRole('row', {name: /Banco Fiable/})).toBeVisible();
  await expect(page.getByRole('row', {name: /Andina/})).toHaveCount(0);

  await page.getByLabel('Rol').selectOption('');
  await page.getByRole('searchbox').fill('800.197');
  await expect(page.getByRole('row', {name: /Andina/})).toBeVisible();
  await expect(page.getByRole('row', {name: /Banco Fiable/})).toHaveCount(0);

  await page.getByRole('searchbox').fill('zzz');
  await expect(
    page.getByText('Ningún tercero coincide con tu búsqueda.'),
  ).toBeVisible();
  await page.getByRole('button', {name: 'Ver todo'}).click();
  await expect(page.getByRole('row')).toHaveCount(3);
});

test('TER-06 · a tercero is edited and the change is kept', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await createCompany(page, 'Andina S.A.S.', '800197268');

  await page.goto('/terceros');
  await page.getByRole('link', {name: 'Editar'}).click();
  await page.getByLabel('Ciudad').fill('Medellín');
  await page.getByLabel('DV').fill('9');
  await page.getByRole('button', {name: 'Guardar tercero'}).click();
  await expect(page.getByText('Cambios guardados.')).toBeVisible();

  await page.reload();
  await expect(page.getByLabel('Ciudad')).toHaveValue('Medellín');
  await expect(page.getByLabel('DV')).toHaveValue('9');
});

test('TER-07 · a tercero is deactivated and activated again', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await createCompany(page, 'Andina S.A.S.', '800197268');

  await page.goto('/terceros');
  await page.getByRole('button', {name: 'Desactivar'}).click();
  await page
    .getByRole('dialog')
    .getByRole('button', {name: 'Desactivar'})
    .click();
  await expect(page.getByRole('button', {name: 'Activar'})).toBeVisible();

  await page.getByLabel('Estado').selectOption('1');
  await expect(page.getByText('Ningún tercero coincide')).toBeVisible();
  await page.getByLabel('Estado').selectOption('0');
  await page.getByRole('button', {name: 'Activar'}).click();
  await expect(page.getByText('Ningún tercero coincide')).toBeVisible();
});

test('TER-08 · a tercero no document uses is deleted after confirming', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await createCompany(page, 'Andina S.A.S.', '800197268');

  await page.goto('/terceros');
  await page.getByRole('button', {name: 'Eliminar'}).click();
  await expect(page.getByRole('dialog')).toContainText(
    '¿Eliminar a Andina S.A.S.?',
  );
  await page
    .getByRole('dialog')
    .getByRole('button', {name: 'Eliminar'})
    .click();

  await expect(page.getByText(/Aún no tienes terceros/)).toBeVisible();
});

test('TER-09 · personal data is erased on request and the row stays', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await page.goto('/terceros/nuevo');
  await page.getByLabel('Tipo', {exact: true}).selectOption('persona');
  await page.getByLabel('Tipo de identificación').selectOption('cc');
  await page.getByLabel('Número de identificación').fill('1020304050');
  await page.getByLabel('Nombres').fill('Ana');
  await page.getByRole('checkbox', {name: 'Cliente'}).check();
  await page.getByLabel(/^Correo electrónico/).fill('ana@mail.co');
  await page.getByRole('button', {name: 'Crear tercero'}).click();
  await expect(page.getByText('Tercero Ana creado.')).toBeVisible();

  await page.getByRole('link', {name: 'Editar'}).click();
  await page.getByRole('button', {name: 'Suprimir datos personales'}).click();
  await page
    .getByRole('dialog')
    .getByRole('button', {name: 'Suprimir datos'})
    .click();

  await expect(page.getByText(/fueron suprimidos el/)).toBeVisible();
  await expect(page.getByRole('button', {name: 'Guardar tercero'})).toHaveCount(
    0,
  );
  await page.goto('/terceros');
  const row = page.getByRole('row', {name: /Datos suprimidos/});
  await expect(row).toContainText('CC 1020304050');
  await expect(row).not.toContainText('ana@mail.co');
});
