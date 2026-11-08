import {type Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

// Cases CTA-01 – 09 of docs/tests/ui-regression.md (observations 2026-11, item 1 account-picker). Each test signs up
// its own company (`newCompany()` in ./support/test). CTA-07 – 09 are run by hand.

/** An account added through the API, as the signed-in owner, for a test about something else than adding it. */
async function addAccount(
  page: Page,
  parent: string,
  code: string,
  name: string,
) {
  const origin = new URL(
    page.url() === 'about:blank' ? 'http://nginx' : page.url(),
  ).origin;
  const response = await page.request.post('/api/v1/accounts', {
    headers: {Origin: origin},
    data: {parent_code: parent, code, name},
  });
  expect(response.status(), `adding ${code}`).toBe(201);
}

async function openNewProduct(page: Page) {
  await page.goto('/productos');
  await page
    .getByRole('button', {name: 'Nuevo producto o servicio'})
    .first()
    .click();
  return page.getByRole('dialog');
}

async function addUnder(page: Page, parent: string) {
  await page.goto('/configuracion?tab=chart');
  await page.getByRole('searchbox').fill(parent);
  await page
    .getByRole('button', {
      name: `Agregar subcuenta bajo ${parent}`,
      exact: true,
    })
    .click();
  return page.getByRole('dialog');
}

const option = (form: ReturnType<Page['getByRole']>, label: string) =>
  form.locator(`datalist option[value="${label}"]`);

test('CTA-01 · the picker finds an auxiliar by its parent’s code', async ({
  page,
  newCompany,
}) => {
  const errors = consoleErrors(page);
  await newCompany('Picker');
  await page.goto('/productos');
  await addAccount(page, '413595', '41359501', 'Ventas gravados');
  await addAccount(page, '413595', '41359502', 'Productos gravados celulares');

  const form = await openNewProduct(page);
  await form.getByLabel('Cuenta de ingreso').fill('4135');

  await expect(
    option(form, '41359502 · Productos gravados celulares'),
  ).toBeAttached();
  await expect(option(form, '41359501 · Ventas gravados')).toBeAttached();
  await expect(form.locator('datalist option')).toHaveCount(39);
  await expect(
    option(form, '413595 · VENTA DE OTROS PRODUCTOS'),
  ).not.toBeAttached();
  expect(errors).toEqual([]);
});

test('CTA-02 · the picker matches every word of the name', async ({
  page,
  newCompany,
}) => {
  await newCompany('Palabras');
  await page.goto('/productos');
  await addAccount(page, '413595', '41359501', 'Ventas gravados');
  await addAccount(page, '413595', '41359502', 'Productos gravados celulares');

  const form = await openNewProduct(page);
  const box = form.getByLabel('Cuenta de ingreso');
  await box.fill('gravados');
  await expect(form.locator('datalist option')).toHaveCount(2);

  await box.fill('gravados celulares');
  await expect(form.locator('datalist option')).toHaveCount(1);
  await expect(
    option(form, '41359502 · Productos gravados celulares'),
  ).toBeAttached();

  await box.fill('41359502');
  await expect(box).toHaveValue('41359502 · Productos gravados celulares');
});

test('CTA-03 · the picker says how many more matched than it lists', async ({
  page,
  newCompany,
}) => {
  await newCompany('Mas');
  const form = await openNewProduct(page);
  const box = form.getByLabel('Cuenta de ingreso');

  await box.fill('4');
  await expect(
    form.getByText(
      /^Mostrando 50 de \d+: escribe más para encontrar la cuenta\.$/,
    ),
  ).toBeVisible();

  await box.fill('4135');
  await expect(form.getByText(/^Mostrando/)).toHaveCount(0);
});

test('CTA-04 · subdividing an account in use warns and moves its rule', async ({
  page,
  newCompany,
}) => {
  const errors = consoleErrors(page);
  await newCompany('Subdividir');

  const dialog = await addUnder(page, '413595');
  await expect(
    dialog.getByText(
      '413595 deja de recibir movimientos: van a sus auxiliares.',
      {exact: false},
    ),
  ).toBeVisible();
  await dialog.getByLabel('Código').fill('41359501');
  await expect(
    dialog.getByText(
      'Lo que la usa pasará a 41359501: regla Ingresos por ventas y los productos y terceros que la usen.',
      {exact: false},
    ),
  ).toBeVisible();
  await expect(dialog.getByLabel('Mover a la nueva auxiliar')).toBeChecked();
  await dialog.getByLabel('Nombre').fill('Ventas gravadas');
  await dialog.getByRole('button', {name: 'Guardar'}).click();

  await expect(
    page.getByText(
      'Cuenta 41359501 creada. Pasaron a 41359501: regla Ingresos por ventas.',
    ),
  ).toBeVisible();
  await page.goto('/configuracion?tab=postingRules');
  await expect(
    page.getByRole('row').filter({hasText: 'Ingresos por ventas'}),
  ).toContainText('41359501');
  expect(errors).toEqual([]);
});

test('CTA-05 · a second auxiliar under the same account moves nothing', async ({
  page,
  newCompany,
}) => {
  await newCompany('Segunda');
  await page.goto('/configuracion?tab=chart');
  await addAccount(page, '413595', '41359501', 'Ventas gravadas');

  const dialog = await addUnder(page, '413595');
  await expect(dialog.getByText(/deja de recibir/)).toHaveCount(0);
  await dialog.getByLabel('Código').fill('41359502');
  await dialog.getByLabel('Nombre').fill('Ventas excluidas');
  await dialog.getByRole('button', {name: 'Guardar'}).click();

  await expect(page.getByText('Cuenta 41359502 creada.')).toBeVisible();
  await expect(page.getByText(/Pasaron a/)).toHaveCount(0);
});

test('CTA-06 · without moving, the account shows it has no default auxiliar', async ({
  page,
  newCompany,
}) => {
  await newCompany('SinMover');

  const dialog = await addUnder(page, '413595');
  await dialog.getByLabel('Mover a la nueva auxiliar').uncheck();
  await expect(
    dialog.getByText(
      'Sin mover, 413595 queda marcada «Sin auxiliar por defecto» hasta que cambies a mano lo que la usa.',
      {exact: false},
    ),
  ).toBeVisible();
  await dialog.getByLabel('Código').fill('41359501');
  await dialog.getByLabel('Nombre').fill('Ventas gravadas');
  await dialog.getByRole('button', {name: 'Guardar'}).click();

  await expect(page.getByText('Cuenta 41359501 creada.')).toBeVisible();
  await expect(
    page.getByRole('row').filter({hasText: 'VENTA DE OTROS PRODUCTOS'}),
  ).toContainText('Sin auxiliar por defecto');
  await page.goto('/configuracion?tab=postingRules');
  await expect(
    page.getByRole('row').filter({hasText: 'Ingresos por ventas'}),
  ).toContainText('413595');
});
