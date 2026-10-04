import type {Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

// Cases DOC-01 – 08 of docs/tests/ui-regression.md (item 7 "document-editor"). No document page mounts the editor
// yet (items 8–10 will), so these run on its development page, /dev/editor-documento.

async function api(
  page: Page,
  method: 'GET' | 'POST',
  path: string,
  data?: unknown,
): Promise<unknown> {
  const response = await page.request.fetch(`/api/v1${path}`, {
    method,
    headers: {Origin: 'http://nginx'},
    data,
  });
  expect(response.ok(), `${method} ${path}`).toBeTruthy();
  return response.json();
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
  expect(tax, `the seeded tax ${name}`).toBeTruthy();
  return tax!.id;
}

/** A service of 1 000 000 before IVA, with IVA 19 % and ReteFuente servicios 4 % by default. */
async function seedService(page: Page) {
  await api(page, 'POST', '/products', {
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

async function seedClient(page: Page) {
  await api(page, 'POST', '/terceros', {
    person_type: 'empresa',
    identification_type: 'nit',
    identification_number: '800197268',
    check_digit: null,
    branch_code: '0',
    first_names: null,
    last_names: null,
    business_name: 'Distribuciones Andina S.A.S.',
    trade_name: null,
    city: null,
    address: null,
    phones: [],
    billing_contact_name: null,
    email: 'compras@andina.co',
    mobile: null,
    postal_code: null,
    vat_regime: null,
    billing_contact_is_payer: true,
    fiscal_responsibilities: [],
    roles: ['cliente'],
    receivable_account_id: null,
    payable_account_id: null,
    contacts: [{id: null, name: 'Ana Pérez', email: null, phone: null}],
  });
}

async function open(page: Page) {
  await page.goto('/dev/editor-documento');
  await expect(
    page.getByRole('heading', {name: 'Editor de documentos (desarrollo)'}),
  ).toBeVisible();
}

async function pickProduct(page: Page, line: number, search: string) {
  await page
    .getByRole('combobox', {name: `Producto/Servicio, línea ${line}`})
    .fill(search);
  await page
    .getByRole('option', {name: /SRV-01 · Consultoría mensual/})
    .click();
}

function total(page: Page, label: string) {
  return page
    .getByRole('region', {name: 'Totales'})
    .locator('dt', {hasText: new RegExp(`^${label}$`)})
    .locator('xpath=following-sibling::dd');
}

test('DOC-01 · the totals preview shows the PRD example as the server computes it', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await seedService(page);
  await open(page);

  await pickProduct(page, 1, 'SRV');
  await page.getByLabel('Cantidad, línea 1').fill('2');
  await page.getByLabel('% Descuento, línea 1').fill('10');

  await expect(total(page, 'Total bruto')).toHaveText('$ 2.000.000,00');
  await expect(total(page, 'Descuentos')).toHaveText('$ 200.000,00');
  await expect(total(page, 'Subtotal')).toHaveText('$ 1.800.000,00');
  await expect(total(page, 'Impuestos')).toHaveText('$ 342.000,00');
  await expect(total(page, 'Retenciones')).toHaveText('$ 72.000,00');
  await expect(total(page, 'Total neto')).toHaveText('$ 2.070.000,00');
  await expect(
    page.getByRole('cell', {name: '$ 2.142.000,00'}),
    'Valor total of the line: base plus IVA',
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test('DOC-02 · the tercero is searched from the third character and brings its contacts', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await seedClient(page);
  await open(page);

  const search = page.getByRole('combobox', {name: 'Cliente'});
  await search.fill('Di');
  await expect(page.getByText('Escribe al menos 3 caracteres.')).toBeVisible();
  await search.fill('Dis');
  await page.getByRole('option', {name: /Distribuciones Andina/}).click();

  await expect(search).toHaveValue('Distribuciones Andina S.A.S.');
  await page.getByLabel(/^Contacto/).selectOption({label: 'Ana Pérez'});
  expect(errors).toEqual([]);
});

test('DOC-03 · a missing tercero is created from the search with the document’s role', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await open(page);

  await page.getByRole('combobox', {name: 'Cliente'}).fill('Nuevo cliente');
  await page.getByRole('option', {name: '+ Crear nuevo'}).click();
  const modal = page.getByRole('dialog');
  await expect(
    modal.getByRole('checkbox', {name: 'Cliente', exact: true}),
  ).toBeChecked();
  await modal.getByLabel('Número de identificación').fill('900123456');
  await modal.getByLabel('Razón social').fill('Nuevo Cliente S.A.S.');
  await modal.getByLabel('Correo electrónico').fill('nuevo@cliente.co');
  await modal.getByRole('button', {name: 'Crear tercero'}).click();

  await expect(modal).toBeHidden();
  await expect(page.getByRole('combobox', {name: 'Cliente'})).toHaveValue(
    'Nuevo Cliente S.A.S.',
  );

  await page.getByLabel('Documento').selectOption('purchase_invoice');
  await page.getByRole('combobox', {name: 'Proveedor'}).fill('Acme');
  await page.getByRole('option', {name: '+ Crear nuevo'}).click();
  await expect(
    page
      .getByRole('dialog')
      .getByRole('checkbox', {name: 'Proveedor', exact: true}),
    'a purchase creates a proveedor',
  ).toBeChecked();
  expect(errors).toEqual([]);
});

test('DOC-04 · a product is created from a line and fills it', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await open(page);

  await page
    .getByRole('combobox', {name: 'Producto/Servicio, línea 1'})
    .fill('Cuaderno rayado');
  await page.getByRole('option', {name: '+ Crear nuevo'}).click();
  const modal = page.getByRole('dialog');
  await expect(modal.getByLabel('Nombre')).toHaveValue('Cuaderno rayado');
  await modal.getByLabel('Código').fill('CUA-01');
  await modal.getByLabel('Precio de venta (COP)').fill('11900');
  await modal.getByLabel('Incluir IVA en el precio').check();
  await modal.getByLabel('Impuesto cargo').selectOption({label: 'IVA 19 %'});
  await modal.getByRole('button', {name: 'Crear'}).click();

  await expect(modal).toBeHidden();
  await expect(
    page.getByRole('combobox', {name: 'Producto/Servicio, línea 1'}),
  ).toHaveValue('CUA-01 · Cuaderno rayado');
  await expect(page.getByLabel('Valor unitario, línea 1')).toHaveValue('10000');
  await expect(
    page.getByRole('cell', {name: '$ 11.900,00'}),
    'an IVA-included price is the line total',
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test('DOC-05 · the tax dialog changes a line and, when asked, the product from now on', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await seedService(page);
  await open(page);

  await pickProduct(page, 1, 'SRV');
  await page.getByRole('button', {name: 'Impuestos de la línea 1'}).click();
  const dialog = page.getByRole('dialog', {name: 'Impuestos de la línea 1'});
  await expect(dialog.getByText('$ 1.000.000,00')).toBeVisible();
  await dialog.getByLabel('Impuesto cargo').selectOption({label: 'IVA 5 %'});
  await dialog
    .getByLabel('Aplicar estos impuestos al producto de ahora en adelante')
    .check();
  await dialog.getByRole('button', {name: 'Aplicar'}).click();

  await expect(dialog).toBeHidden();
  await expect(total(page, 'Impuestos')).toHaveText('$ 50.000,00');

  await page.getByRole('button', {name: 'Agregar línea'}).click();
  await pickProduct(page, 2, 'SRV');
  await expect(
    page.getByLabel('Impuesto cargo, línea 2').locator('option:checked'),
    'the product now brings IVA 5 %',
  ).toHaveText('IVA 5 %');
  expect(errors).toEqual([]);
});

test('DOC-07 · formas de pago add up to Total neto, with a due date on crédito', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await seedService(page);
  await open(page);

  await pickProduct(page, 1, 'SRV');
  await page.getByLabel('Impuesto retención, línea 1').selectOption('');
  await expect(total(page, 'Total neto')).toHaveText('$ 1.190.000,00');

  await page.getByRole('button', {name: 'Agregar forma de pago'}).click();
  await page.getByLabel('Método de pago 1').selectOption({label: 'Efectivo'});
  await expect(page.getByLabel('Valor de la forma de pago 1')).toHaveValue(
    '1190000.00',
  );
  await expect(page.getByText('Coincide con el total neto')).toBeVisible();

  await page.getByLabel('Valor de la forma de pago 1').fill('595000');
  await expect(
    page.getByText('Faltan $ 595.000,00 para el total neto'),
  ).toBeVisible();
  await page.getByRole('button', {name: 'Comprobar'}).click();
  await expect(
    page.getByText(
      'Total formas de pago ($ 595.000,00) debe ser igual al total neto ($ 1.190.000,00).',
    ),
  ).toBeVisible();

  await page.getByRole('button', {name: 'Agregar forma de pago'}).click();
  await page.getByLabel('Método de pago 2').selectOption({label: 'Crédito'});
  await expect(page.getByLabel('Plazo de la forma de pago 2')).toHaveValue(
    '30',
  );
  await expect(page.getByText('Coincide con el total neto')).toBeVisible();

  await page.getByLabel('Documento').selectOption('quotation');
  await expect(
    page.getByRole('heading', {name: 'Formas de pago'}),
    'a quotation has none',
  ).toHaveCount(0);
  expect(errors).toEqual([]);
});

test('DOC-08 · the lines work from the keyboard', async ({newCompany}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await open(page);

  await page.getByLabel('Descripción, línea 1').fill('Primera');
  await page.getByLabel('% Descuento, línea 1').press('Enter');
  await expect(
    page.getByRole('combobox', {name: 'Producto/Servicio, línea 2'}),
    'Enter on the last line adds one, ready to type in',
  ).toBeFocused();

  await page.getByLabel('Descripción, línea 2').fill('Segunda');
  await page.getByLabel('Descripción, línea 2').press('Alt+ArrowUp');
  await expect(page.getByLabel('Descripción, línea 1')).toHaveValue('Segunda');
  await expect(page.getByLabel('Descripción, línea 1')).toBeFocused();

  await page.getByRole('button', {name: 'Quitar línea 1'}).click();
  await expect(page.getByLabel('Descripción, línea 1')).toHaveValue('Primera');
  expect(errors).toEqual([]);
});

/** Every column of the lines grid (headers and the cells of line 1) lies inside the table's visible box. */
async function expectEveryColumnVisible(page: Page) {
  const table = page
    .locator('.doc-lines .table-wrap, .doc-lines table')
    .first();
  await expect(table).toBeVisible();
  const box = await table.boundingBox();
  expect(box, 'the lines table').not.toBeNull();
  const scrolls = await page.locator('.doc-lines table').evaluate((el) => {
    let node: HTMLElement | null = el.parentElement;
    while (node && !node.classList.contains('doc-lines')) {
      if (node.scrollWidth > node.clientWidth + 1) return true;
      node = node.parentElement;
    }
    return false;
  });
  expect(scrolls, 'the lines table does not scroll sideways').toBe(false);
  const headers = page.locator('.doc-lines thead th');
  const names = await headers.allTextContents();
  for (const name of ['Impuesto retención', 'Valor total', 'Acciones']) {
    expect(names.map((n) => n.trim())).toContain(name);
  }
  const cells = page.locator(
    '.doc-lines thead th, .doc-lines tbody tr:first-child > td',
  );
  const count = await cells.count();
  for (let i = 0; i < count; i += 1) {
    const cell = await cells.nth(i).boundingBox();
    const label = (await cells.nth(i).textContent())?.trim() || `cell ${i}`;
    expect(cell, label).not.toBeNull();
    expect(cell!.x, `${label} starts inside the table`).toBeGreaterThanOrEqual(
      box!.x - 1,
    );
    expect(
      cell!.x + cell!.width,
      `${label} ends inside the table (${box!.x + box!.width})`,
    ).toBeLessThanOrEqual(box!.x + box!.width + 1);
  }
}

test('DOC-10 · at 1366×768 every column of the lines is visible without scrolling the table', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await page.setViewportSize({width: 1366, height: 768});

  await seedService(page);
  await page.goto('/facturas-venta/nueva');
  await expect(
    page.getByRole('combobox', {name: 'Producto/Servicio, línea 1'}),
  ).toBeVisible();
  await pickProduct(page, 1, 'SRV');
  await expectEveryColumnVisible(page);

  await page.goto('/facturas-compra/nueva');
  await expect(page.getByLabel('Tipo de línea 1')).toBeVisible();
  await page.getByLabel('Tipo de línea 1').selectOption('account');
  await expectEveryColumnVisible(page);
});
