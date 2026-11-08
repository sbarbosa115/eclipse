import type {Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

// Cases RET-01 – 12 of docs/tests/ui-regression.md (observations 2026-11, item 3 withholdings-editor; item 2
// withholdings-api builds the API). Each test signs up its own company (`newCompany()` in ./support/test). The preview
// cases run on the editor's development page (/dev/editor-documento); the saving ones on the invoice pages.

type Json = Record<string, unknown>;

async function api(
  page: Page,
  method: 'GET' | 'POST' | 'PUT',
  path: string,
  data?: unknown,
): Promise<Json> {
  const response = await page.request.fetch(`/api/v1${path}`, {
    method,
    headers: {Origin: 'http://nginx'},
    data,
  });
  expect(
    response.ok(),
    `${method} ${path}: ${await response.text()}`,
  ).toBeTruthy();
  const text = await response.text();
  return text === '' ? {} : (JSON.parse(text) as Json);
}

async function taxId(
  page: Page,
  taxClass: 'charge' | 'withholding',
  name: string,
): Promise<string> {
  const {items} = (await api(page, 'GET', `/taxes?class=${taxClass}`)) as {
    items: {id: string; name: string}[];
  };
  const tax = items.find((t) => t.name === name);
  expect(tax, `the tax ${name}`).toBeTruthy();
  return tax!.id;
}

/**
 * A ReteICA of 0,966 % (none is seeded) and a service SRV-01 of 1.000.000 + IVA 19 % whose default retención is
 * ReteFuente servicios 4 %.
 */
async function seed(page: Page) {
  await api(page, 'POST', '/taxes', {
    name: 'ReteICA 0,966 %',
    tax_class: 'withholding',
    kind: 'reteica',
    calculation: 'percentage',
    rate: '0.966',
    sales_account_id: null,
    purchase_account_id: null,
    valid_from: null,
    valid_to: null,
  });
  await api(page, 'POST', '/products/quick', {
    type: 'servicio',
    code: 'SRV-01',
    name: 'Consultoría mensual',
    sale_price: '1000000',
    price_includes_tax: false,
    charge_tax_id: await taxId(page, 'charge', 'IVA 19 %'),
    withholding_tax_id: await taxId(
      page,
      'withholding',
      'ReteFuente servicios 4 %',
    ),
  });
}

async function openEditor(page: Page) {
  await page.goto('/dev/editor-documento');
  await expect(
    page.getByRole('heading', {name: 'Editor de documentos (desarrollo)'}),
  ).toBeVisible();
}

async function pickProduct(
  page: Page,
  line: number,
  label = 'Producto/Servicio',
) {
  await page
    .getByRole('combobox', {name: `${label}, línea ${line}`})
    .fill('SRV');
  await page
    .getByRole('option', {name: /SRV-01 · Consultoría mensual/})
    .click();
}

/** The amount next to a label of the totals panel (a retención's row has its base after the amount). */
function total(page: Page, label: string) {
  return page
    .getByRole('region', {name: 'Totales'})
    .locator('dt', {hasText: new RegExp(`^${label}$`)})
    .locator('xpath=following-sibling::dd[1]');
}

function base(page: Page, label: string) {
  return page
    .getByRole('region', {name: 'Totales'})
    .locator('dt', {hasText: new RegExp(`^${label}$`)})
    .locator('xpath=following-sibling::dd[2]');
}

function cell(page: Page, line: number) {
  return page.getByRole('button', {
    name: new RegExp(`^Retenciones de la línea ${line}:`),
  });
}

type Chosen = Partial<
  Record<'retefuente' | 'reteiva' | 'reteica', string | null>
>;

const LABELS = {
  retefuente: 'Retefuente',
  reteiva: 'ReteIVA',
  reteica: 'ReteICA',
};

/** Opens the line's retenciones from their cell and chooses one per kind (null: Ninguna). */
async function chooseRetenciones(page: Page, line: number, chosen: Chosen) {
  await cell(page, line).click();
  const dialog = page.getByRole('dialog', {
    name: `Impuestos de la línea ${line}`,
  });
  for (const [kind, name] of Object.entries(chosen)) {
    const select = dialog.getByLabel(LABELS[kind as keyof typeof LABELS], {
      exact: true,
    });
    await (name ? select.selectOption({label: name}) : select.selectOption(''));
  }
  await dialog.getByRole('button', {name: 'Aplicar'}).click();
  await expect(dialog).toBeHidden();
}

const OBS2: Chosen = {
  retefuente: 'ReteFuente compras 2,5 %',
  reteiva: 'ReteIVA 15 %',
  reteica: 'ReteICA 0,966 %',
};

test('RET-01 · the line’s tax dialog has one select per kind of retención', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await seed(page);
  await openEditor(page);
  await pickProduct(page, 1);

  await cell(page, 1).click();
  const dialog = page.getByRole('dialog', {name: 'Impuestos de la línea 1'});
  await expect(
    dialog.getByLabel('Retefuente', {exact: true}).locator('option:checked'),
    'the product’s default retención',
  ).toHaveText('ReteFuente servicios 4 %');
  await expect(
    dialog.getByLabel('ReteIVA', {exact: true}).locator('option:checked'),
  ).toHaveText('Ninguna');
  await expect(
    dialog.getByLabel('ReteICA', {exact: true}).locator('option'),
    'each select offers only its kind',
  ).toHaveText(['Ninguna', 'ReteICA 0,966 %']);
  await expect(
    dialog.getByLabel('ReteIVA', {exact: true}).locator('option'),
  ).toHaveText(['Ninguna', 'ReteIVA 15 %']);
  expect(errors).toEqual([]);
});

test('RET-02 · the preview of OBS-2: three retenciones on one line, each with its base', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await seed(page);
  await openEditor(page);
  await pickProduct(page, 1);

  await chooseRetenciones(page, 1, OBS2);

  await expect(total(page, 'Impuestos')).toHaveText('$ 190.000,00');
  await expect(total(page, 'Retenciones')).toHaveText('$ 63.160,00');
  await expect(total(page, 'Total neto')).toHaveText('$ 1.126.840,00');
  await expect(total(page, 'ReteFuente compras 2,5 %')).toHaveText(
    '$ 25.000,00',
  );
  await expect(base(page, 'ReteFuente compras 2,5 %')).toHaveText(
    'Base $ 1.000.000,00',
  );
  await expect(total(page, 'ReteIVA 15 %')).toHaveText('$ 28.500,00');
  await expect(
    base(page, 'ReteIVA 15 %'),
    'ReteIVA is withheld on the IVA',
  ).toHaveText('Base $ 190.000,00');
  await expect(total(page, 'ReteICA 0,966 %')).toHaveText('$ 9.660,00');
  expect(errors).toEqual([]);
});

test('RET-03 · the Retenciones cell lists the names chosen, or says there are none', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await seed(page);
  await openEditor(page);
  await pickProduct(page, 1);

  await expect(cell(page, 1)).toHaveText('ReteFuente servicios 4 %');
  await chooseRetenciones(page, 1, {
    retefuente: null,
    reteiva: 'ReteIVA 15 %',
  });
  await expect(cell(page, 1)).toHaveText('ReteIVA 15 %');
  await chooseRetenciones(page, 1, {reteiva: null});
  await expect(cell(page, 1)).toHaveText('Sin retenciones');
  await expect(
    page.getByRole('region', {name: 'Totales'}).getByText(/^Base /),
    'no retención rows without retenciones',
  ).toHaveCount(0);
  await expect(total(page, 'Retenciones')).toHaveText('$ 0,00');
  expect(errors).toEqual([]);
});

test('RET-04 · a sales invoice keeps its three retenciones when saved and reopened', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await seed(page);
  await api(page, 'POST', '/terceros/quick', {
    person_type: 'empresa',
    identification_type: 'nit',
    identification_number: '800197268',
    business_name: 'Distribuciones Andina S.A.S.',
    email: 'compras@andina.co',
    roles: ['cliente'],
  });

  await page.goto('/facturas-venta/nueva');
  await page.getByRole('combobox', {name: 'Cliente'}).fill('Dis');
  await page.getByRole('option', {name: /Distribuciones Andina/}).click();
  await pickProduct(page, 1);
  await chooseRetenciones(page, 1, OBS2);
  await expect(total(page, 'Total neto')).toHaveText('$ 1.126.840,00');
  await page.getByRole('button', {name: 'Guardar'}).click();
  await expect(page.getByText('Borrador guardado.')).toBeVisible();
  await expect(page).toHaveURL(/\/facturas-venta\/[0-9a-f-]{36}$/);

  await page.reload();
  await expect(
    cell(page, 1),
    'every retención came back from the server, in kind order',
  ).toHaveText('ReteFuente compras 2,5 % · ReteIVA 15 % · ReteICA 0,966 %');
  await expect(total(page, 'Retenciones')).toHaveText('$ 63.160,00');
  expect(errors).toEqual([]);
});

test('RET-05 · a purchase invoice keeps its retenciones when saved and reopened', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await seed(page);
  await api(page, 'POST', '/terceros/quick', {
    person_type: 'empresa',
    identification_type: 'nit',
    identification_number: '900555123',
    business_name: 'Servicios Andinos S.A.S.',
    email: 'facturas@andinos.co',
    roles: ['proveedor'],
  });

  await page.goto('/facturas-compra/nueva');
  await page.getByRole('combobox', {name: 'Proveedor'}).fill('Serv');
  await page.getByRole('option', {name: /Servicios Andinos/}).click();
  await pickProduct(page, 1, 'Producto/Servicio o cuenta');
  await page.getByLabel('Valor unitario, línea 1').fill('1000000');
  await chooseRetenciones(page, 1, OBS2);
  await expect(total(page, 'Retenciones')).toHaveText('$ 63.160,00');
  await page.getByRole('button', {name: 'Guardar borrador'}).click();
  await expect(page.getByText('Borrador guardado.')).toBeVisible();

  await page.reload();
  await expect(cell(page, 1)).toHaveText(
    'ReteFuente compras 2,5 % · ReteIVA 15 % · ReteICA 0,966 %',
  );
  await expect(total(page, 'Total neto')).toHaveText('$ 1.126.840,00');
  expect(errors).toEqual([]);
});

test('RET-07 · three lines with odd cents: the retenciones are rounded once', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await seed(page);
  await openEditor(page);

  for (const line of [1, 2, 3]) {
    if (line > 1) {
      await page.getByRole('button', {name: 'Agregar línea'}).click();
    }
    await pickProduct(page, line);
    await page.getByLabel(`Valor unitario, línea ${line}`).fill('333,33');
    await chooseRetenciones(page, line, {
      retefuente: 'ReteFuente compras 2,5 %',
      reteica: 'ReteICA 0,966 %',
    });
  }

  await expect(
    total(page, 'Retenciones'),
    '3 × (8,33325 + 3,2199678) = 34,6596534, rounded once',
  ).toHaveText('$ 34,66');
  await expect(total(page, 'ReteFuente compras 2,5 %')).toHaveText('$ 25,00');
  await expect(total(page, 'ReteICA 0,966 %')).toHaveText('$ 9,66');
  expect(errors).toEqual([]);
});

test('RET-08 · “Aplicar al producto” keeps one default retención: the Retefuente one', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await seed(page);
  await openEditor(page);
  await pickProduct(page, 1);

  await cell(page, 1).click();
  const dialog = page.getByRole('dialog', {name: 'Impuestos de la línea 1'});
  await dialog
    .getByLabel('ReteIVA', {exact: true})
    .selectOption({label: 'ReteIVA 15 %'});
  await dialog
    .getByLabel('Retefuente', {exact: true})
    .selectOption({label: 'ReteFuente compras 2,5 %'});
  await dialog
    .getByLabel('Aplicar estos impuestos al producto de ahora en adelante')
    .check();
  await dialog.getByRole('button', {name: 'Aplicar'}).click();
  await expect(dialog).toBeHidden();

  await page.getByRole('button', {name: 'Agregar línea'}).click();
  await pickProduct(page, 2);
  await expect(
    cell(page, 2),
    'the product now brings ReteFuente compras 2,5 % alone',
  ).toHaveText('ReteFuente compras 2,5 %');
  expect(errors).toEqual([]);
});
