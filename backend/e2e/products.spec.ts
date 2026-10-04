import {type Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

// Cases PRD-01 – 11 of docs/tests/ui-regression.md (item "catalog"). The rest need taxes, a chart, documents or other
// roles and are run by hand.

/** A product through the API, for a test that is about something else than creating it. */
async function seedProduct(
  page: Page,
  code: string,
  name: string,
  extra: Record<string, unknown> = {},
) {
  const origin = new URL(
    page.url() === 'about:blank' ? 'http://nginx' : page.url(),
  ).origin;
  const response = await page.request.post('/api/v1/products', {
    headers: {Origin: origin},
    data: {
      type: 'producto',
      code,
      name,
      sale_price: '10000',
      price_includes_tax: false,
      ...extra,
    },
  });
  expect(response.status(), `creating ${code}`).toBe(201);
}

async function open(page: Page) {
  await page.goto('/productos');
  await expect(
    page.getByRole('heading', {name: 'Productos y servicios'}),
  ).toBeVisible();
}

async function fillForm(
  page: Page,
  values: {code: string; name: string; price: string},
) {
  const form = page.getByRole('dialog');
  await form.getByLabel('Código').fill(values.code);
  await form.getByLabel('Nombre').fill(values.name);
  await form.getByLabel('Precio de venta (COP)').fill(values.price);
  return form;
}

test('PRD-01 · a new company sees what the section is for and how to start', async ({
  page,
  newCompany,
}) => {
  const errors = consoleErrors(page);
  await newCompany('Vacía');

  await open(page);

  await expect(
    page.getByText(/Aún no tienes productos ni servicios/),
  ).toBeVisible();
  await expect(
    page.getByRole('button', {name: 'Nuevo producto o servicio'}).first(),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test('PRD-02 · the full form creates a product that shows in the list', async ({
  page,
  newCompany,
}) => {
  const errors = consoleErrors(page);
  await newCompany();
  await open(page);

  await page
    .getByRole('button', {name: 'Nuevo producto o servicio'})
    .first()
    .click();
  const form = await fillForm(page, {
    code: 'CUA-01',
    name: 'Cuaderno rayado',
    price: '4500',
  });
  await form.getByLabel('Descripción larga').fill('100 hojas, tapa dura');
  await form.getByRole('button', {name: 'Guardar'}).click();

  await expect(page.getByText('Producto creado.')).toBeVisible();
  const row = page.getByRole('row', {name: /CUA-01/});
  await expect(row).toContainText('Cuaderno rayado');
  await expect(row).toContainText('Producto');
  await expect(row).toContainText('Unidad');
  await expect(row).toContainText('$ 4.500,00');
  expect(errors).toEqual([]);
});

test('PRD-03 · a código already used is refused on its field', async ({
  page,
  newCompany,
}) => {
  await newCompany();
  await open(page);
  await seedProduct(page, 'DUP-1', 'Primero');
  await page.reload();

  await page
    .getByRole('button', {name: 'Nuevo producto o servicio'})
    .first()
    .click();
  const form = await fillForm(page, {
    code: 'DUP-1',
    name: 'Segundo',
    price: '1',
  });
  await form.getByRole('button', {name: 'Guardar'}).click();

  await expect(
    form.getByText('Ya hay un producto o servicio con este código.'),
  ).toBeVisible();
  await expect(form, 'the form stays open for the fix').toBeVisible();
});

test('PRD-04 · the form explains what is missing or wrong', async ({
  page,
  newCompany,
}) => {
  await newCompany();
  await open(page);

  await page
    .getByRole('button', {name: 'Nuevo producto o servicio'})
    .first()
    .click();
  const form = page.getByRole('dialog');
  await form.getByRole('button', {name: 'Guardar'}).click();
  await expect(form.getByText('Este campo es obligatorio.')).toHaveCount(3);

  await form.getByLabel('Código').fill('X');
  await form.getByLabel('Nombre').fill('X');
  await form.getByLabel('Precio de venta (COP)').fill('12,34567');
  await form.getByRole('button', {name: 'Guardar'}).click();
  await expect(
    form.getByText('Escribe el precio en pesos, con máximo cuatro decimales.'),
  ).toBeVisible();
});

test('PRD-05 · a service starts with the unit "servicio"', async ({
  page,
  newCompany,
}) => {
  await newCompany();
  await open(page);

  await page
    .getByRole('button', {name: 'Nuevo producto o servicio'})
    .first()
    .click();
  const form = page.getByRole('dialog');
  await expect(form.getByLabel('Unidad de medida DIAN')).toHaveValue('94');
  await form.getByLabel('Tipo').selectOption('servicio');
  await expect(form.getByLabel('Unidad de medida DIAN')).toHaveValue('ZZ');
  await form.getByLabel('Código').fill('ASE-1');
  await form.getByLabel('Nombre').fill('Asesoría por hora');
  await form.getByLabel('Unidad de medida DIAN').selectOption('HUR');
  await form.getByLabel('Precio de venta (COP)').fill('120000');
  await form.getByRole('button', {name: 'Guardar'}).click();

  const row = page.getByRole('row', {name: /ASE-1/});
  await expect(row).toContainText('Servicio');
  await expect(row).toContainText('Hora');
});

test('PRD-06 · editing a product changes it', async ({page, newCompany}) => {
  await newCompany();
  await open(page);
  await seedProduct(page, 'ED-1', 'Antes');
  await page.reload();

  await page
    .getByRole('row', {name: /ED-1/})
    .getByRole('button', {name: 'Editar'})
    .click();
  const form = page.getByRole('dialog');
  await expect(form.getByLabel('Nombre')).toHaveValue('Antes');
  await expect(form.getByLabel('Precio de venta (COP)')).toHaveValue('10.000');
  await form.getByLabel('Nombre').fill('Después');
  await form.getByLabel('Precio de venta (COP)').fill('12.500,5');
  await form.getByRole('button', {name: 'Guardar'}).click();

  await expect(page.getByText('Producto guardado.')).toBeVisible();
  const row = page.getByRole('row', {name: /ED-1/});
  await expect(row).toContainText('Después');
  await expect(row).toContainText('$ 12.500,50');
});

test('PRD-07 · search and filters narrow the list, and "Ver todo" brings it back', async ({
  page,
  newCompany,
}) => {
  await newCompany();
  await open(page);
  await seedProduct(page, 'A-1', 'Arroz');
  await seedProduct(page, 'S-1', 'Consultoría', {type: 'servicio'});
  await page.reload();
  await expect(page.getByRole('row')).toHaveCount(3);

  await page.getByLabel('Buscar').fill('arr');
  await expect(page.getByRole('row', {name: /A-1/})).toBeVisible();
  await expect(page.getByRole('row', {name: /S-1/})).toHaveCount(0);

  await page.getByLabel('Buscar').fill('');
  await page.getByLabel('Tipo').selectOption('servicio');
  await expect(page.getByRole('row', {name: /S-1/})).toBeVisible();
  await expect(page.getByRole('row', {name: /A-1/})).toHaveCount(0);

  await page.getByLabel('Buscar').fill('zzz');
  await expect(
    page.getByText('Ningún producto o servicio coincide con lo que buscas.'),
  ).toBeVisible();
  await page.getByRole('button', {name: 'Ver todo'}).click();
  await expect(page.getByRole('row')).toHaveCount(3);
  await expect(page.getByLabel('Buscar')).toHaveValue('');
  await expect(page.getByLabel('Tipo')).toHaveValue('');
});

test('PRD-08 · deactivating takes a product out of the active list and reactivating brings it back', async ({
  page,
  newCompany,
}) => {
  await newCompany();
  await open(page);
  await seedProduct(page, 'D-1', 'Descontinuado');
  await page.reload();

  await page.getByRole('button', {name: 'Desactivar'}).click();
  await expect(
    page.getByText(
      '«Descontinuado» quedó inactivo: no aparece en documentos nuevos.',
    ),
  ).toBeVisible();
  await page.getByLabel('Estado').selectOption('1');
  await expect(
    page.getByText('Ningún producto o servicio coincide con lo que buscas.'),
  ).toBeVisible();
  await page.getByLabel('Estado').selectOption('0');
  await expect(page.getByRole('row', {name: /D-1/})).toContainText('Inactivo');

  await page.getByRole('button', {name: 'Reactivar'}).click();
  await expect(
    page.getByText('«Descontinuado» está activo otra vez.'),
  ).toBeVisible();
});

test('PRD-09 · a product no document used can be deleted, after confirming', async ({
  page,
  newCompany,
}) => {
  await newCompany();
  await open(page);
  await seedProduct(page, 'X-1', 'Para borrar');
  await page.reload();

  await page.getByRole('button', {name: 'Eliminar'}).click();
  const dialog = page.getByRole('dialog');
  await expect(dialog).toContainText('¿Eliminar «Para borrar»?');
  await dialog.getByRole('button', {name: 'Cancelar'}).click();
  await expect(page.getByRole('row', {name: /X-1/})).toBeVisible();

  await page.getByRole('button', {name: 'Eliminar'}).click();
  await page
    .getByRole('dialog')
    .getByRole('button', {name: 'Eliminar'})
    .click();
  await expect(page.getByText('«Para borrar» se eliminó.')).toBeVisible();
  await expect(page.getByRole('row', {name: /X-1/})).toHaveCount(0);
});

test('PRD-10 · categories: add, rename, no repeated name, and use one on a product', async ({
  page,
  newCompany,
}) => {
  const errors = consoleErrors(page);
  await newCompany();
  await open(page);

  await page.getByRole('button', {name: 'Categorías'}).click();
  const dialog = page.getByRole('dialog');
  await expect(dialog.getByText('Aún no hay categorías.')).toBeVisible();
  await dialog.getByLabel('Nombre de la categoría').fill('Papelería');
  await dialog.getByRole('button', {name: 'Agregar categoría'}).click();
  await expect(dialog.getByRole('cell', {name: 'Papelería'})).toBeVisible();

  await dialog.getByLabel('Nombre de la categoría').fill('Papelería');
  await dialog.getByRole('button', {name: 'Agregar categoría'}).click();
  await expect(
    dialog.getByText('Ya hay una categoría con este nombre.'),
  ).toBeVisible();

  await dialog.getByRole('button', {name: 'Renombrar'}).click();
  await dialog
    .getByRole('textbox', {name: 'Nombre de la categoría'})
    .last()
    .fill('Útiles escolares');
  await dialog.getByRole('button', {name: 'Guardar nombre'}).click();
  await expect(
    dialog.getByRole('cell', {name: 'Útiles escolares'}),
  ).toBeVisible();
  await dialog
    .getByRole('button', {name: 'Cerrar', exact: true})
    .last()
    .click();

  await page
    .getByRole('button', {name: 'Nuevo producto o servicio'})
    .first()
    .click();
  const form = await fillForm(page, {code: 'C-1', name: 'Lápiz', price: '800'});
  await form.getByLabel('Categoría').selectOption({label: 'Útiles escolares'});
  await form.getByRole('button', {name: 'Guardar'}).click();
  await expect(page.getByRole('row', {name: /C-1/})).toContainText(
    'Útiles escolares',
  );
  expect(
    errors,
    'only the 422 of the repeated name, which Chrome logs as a failed resource',
  ).toEqual([
    'Failed to load resource: the server responded with a status of 422 (Unprocessable Content)',
  ]);
});

test('PRD-11 · a long list is paged', async ({page, newCompany}) => {
  await newCompany();
  await open(page);
  for (let i = 1; i <= 22; i += 1) {
    await seedProduct(
      page,
      `L-${String(i).padStart(2, '0')}`,
      `Producto ${String(i).padStart(2, '0')}`,
    );
  }
  await page.reload();

  await expect(page.getByText('Página 1 de 2')).toBeVisible();
  await expect(page.getByRole('row')).toHaveCount(21);
  await page.getByRole('button', {name: 'Siguiente'}).click();
  await expect(page.getByText('Página 2 de 2')).toBeVisible();
  await expect(page.getByRole('row')).toHaveCount(3);
});
