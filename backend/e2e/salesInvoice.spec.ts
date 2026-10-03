import type {Page} from '@playwright/test';
import {emailTo} from './support/mail';
import {consoleErrors, expect, test} from './support/test';

// Cases SAL-01 – 10 of docs/tests/ui-regression.md (item 8 "sales-invoice"). Each test signs up its own company and
// records its invoicing resolution through the API first, as the owner would in Configuración › Resolución.

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

/** A day in Colombia, as the API dates documents: today, or `days` from it. */
function day(days = 0): string {
  const today = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'America/Bogota',
  }).format(new Date());
  const date = new Date(`${today}T12:00:00Z`);
  date.setUTCDate(date.getUTCDate() + days);
  return date.toISOString().slice(0, 10);
}

function ddmmyyyy(iso: string): string {
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y}`;
}

async function resolution(page: Page, rangeTo = 1000) {
  await api(page, 'POST', '/company/resolution', {
    resolution_number: '18764000001234',
    prefix: 'FE',
    range_from: 1,
    range_to: rangeTo,
    valid_from: day(-365),
    valid_to: day(365),
    mode: 'electronic',
  });
}

async function idOf(page: Page, path: string, name: string): Promise<string> {
  const {items} = (await api(page, 'GET', path)) as unknown as {
    items: {id: string; name: string}[];
  };
  const found = items.find((item) => item.name === name);
  expect(found, `${name} in ${path}`).toBeTruthy();
  return found!.id;
}

/** A client "Distribuciones Andina" and a service SRV-01 of 1.000.000 + IVA 19 %. */
async function seed(
  page: Page,
  nit: string,
): Promise<{client: string; product: string; email: string}> {
  const email = `facturas-${nit}@andina.co`;
  const client = await api(page, 'POST', '/terceros/quick', {
    person_type: 'empresa',
    identification_type: 'nit',
    identification_number: String(Number(nit) + 7),
    business_name: 'Distribuciones Andina S.A.S.',
    email,
    roles: ['cliente'],
  });
  const product = await api(page, 'POST', '/products/quick', {
    type: 'servicio',
    code: 'SRV-01',
    name: 'Consultoría mensual',
    sale_price: '1000000',
    price_includes_tax: false,
    charge_tax_id: await idOf(page, '/taxes?class=charge', 'IVA 19 %'),
    withholding_tax_id: null,
  });
  return {client: client.id as string, product: product.id as string, email};
}

/** A draft for 1.190.000, paid as given (in cash by default), through the API. */
async function draft(
  page: Page,
  seeded: {client: string; product: string},
  payments?: Json[],
): Promise<Json> {
  return api(page, 'POST', '/sales-invoices', {
    tercero_id: seeded.client,
    contact_id: null,
    seller_id: null,
    issue_date: day(),
    notes: null,
    lines: [
      {
        product_id: seeded.product,
        description: 'Consultoría mensual',
        quantity: '1',
        unit_price: '1000000',
        discount: '',
        charge_tax_id: await idOf(page, '/taxes?class=charge', 'IVA 19 %'),
        withholding_tax_id: null,
      },
    ],
    payments: payments ?? [
      {
        payment_method_id: await idOf(page, '/payment-methods', 'Efectivo'),
        amount: '1190000.00',
        due_date: null,
      },
    ],
  });
}

async function emitted(
  page: Page,
  seeded: {client: string; product: string},
  payments?: Json[],
): Promise<Json> {
  const invoice = await draft(page, seeded, payments);
  return api(page, 'POST', `/sales-invoices/${invoice.id as string}/emit`, {});
}

test('SAL-01 · a company without invoices is told what the section is for', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);

  await page.goto('/facturas-venta');

  await expect(
    page.getByRole('heading', {name: 'Facturas de venta'}),
  ).toBeVisible();
  await expect(
    page.getByText(/Aún no has hecho facturas de venta/),
  ).toBeVisible();
  await expect(
    page.getByRole('link', {name: 'Crear la primera factura'}),
  ).toBeVisible();
  await page.getByRole('link', {name: 'Nueva factura'}).click();
  await expect(
    page.getByRole('heading', {name: 'Nueva factura de venta'}),
  ).toBeVisible();
  await expect(
    page.getByText(/Aún no has registrado tu resolución/),
    'no resolution yet',
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test('SAL-02 · an invoice paid half in cash and half at 30 days is saved, emitted and posted', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  await resolution(page);
  await seed(page, nit);

  await page.goto('/facturas-venta/nueva');
  await expect(page.getByLabel('Tipo')).toHaveValue(
    'Factura electrónica de venta (FE)',
  );
  await page.getByRole('combobox', {name: 'Cliente'}).fill('Dis');
  await page.getByRole('option', {name: /Distribuciones Andina/}).click();
  await page
    .getByRole('combobox', {name: 'Producto/Servicio, línea 1'})
    .fill('SRV');
  await page
    .getByRole('option', {name: /SRV-01 · Consultoría mensual/})
    .click();
  await page.getByRole('button', {name: 'Agregar forma de pago'}).click();
  await page.getByLabel('Método de pago 1').selectOption({label: 'Efectivo'});
  await page.getByLabel('Valor de la forma de pago 1').fill('595000');
  await page.getByRole('button', {name: 'Agregar forma de pago'}).click();
  await page.getByLabel('Método de pago 2').selectOption({label: 'Crédito'});
  await expect(page.getByText('Coincide con el total neto')).toBeVisible();

  await page.getByRole('button', {name: 'Guardar'}).click();
  await expect(page.getByText('Borrador guardado.')).toBeVisible();
  await expect(page).toHaveURL(/\/facturas-venta\/[0-9a-f-]{36}$/);
  await expect(
    page.getByRole('heading', {name: 'Factura de venta · borrador'}),
  ).toBeVisible();

  await page.getByRole('button', {name: 'Emitir', exact: true}).click();
  const dialog = page.getByRole('dialog', {name: '¿Emitir la factura?'});
  await dialog.getByRole('button', {name: 'Emitir', exact: true}).click();
  await expect(
    page.getByText('Factura FE-1 emitida y contabilizada.'),
  ).toBeVisible();
  await expect(
    page.getByRole('heading', {name: 'Factura de venta FE-1'}),
  ).toBeVisible();
  await expect(
    page.getByText(/Saldo por cobrar: \$ 595\.000,00/),
  ).toBeVisible();
  await expect(page.getByRole('button', {name: 'Guardar'})).toHaveCount(0);

  const journal = (await api(page, 'GET', '/ledger/journal')) as unknown as {
    items: {lines: {account_code: string; debit: string; credit: string}[]}[];
  };
  expect(journal.items).toHaveLength(1);
  expect(
    journal.items[0]!.lines.map((l) => [l.account_code, l.debit, l.credit]),
  ).toEqual([
    ['11050501', '595000.00', '0.00'],
    ['13050501', '595000.00', '0.00'],
    ['413595', '0.00', '1000000.00'],
    ['240805', '0.00', '190000.00'],
  ]);
  expect(errors).toEqual([]);
});

test('SAL-03 · an invoice whose payments do not add up is not emitted', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  await resolution(page);
  const seeded = await seed(page, nit);
  const invoice = await draft(page, seeded, [
    {
      payment_method_id: await idOf(page, '/payment-methods', 'Efectivo'),
      amount: '100000.00',
      due_date: null,
    },
  ]);

  await page.goto(`/facturas-venta/${invoice.id as string}`);
  await page.getByRole('button', {name: 'Emitir', exact: true}).click();

  await expect(page.getByRole('dialog')).toHaveCount(0);
  await expect(page.getByText('Revisa los campos marcados.')).toBeVisible();
  await expect(
    page.getByText(
      'Total formas de pago ($ 100.000,00) debe ser igual al total neto ($ 1.190.000,00).',
    ),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test('SAL-04 · a new invoice warns when the resolution is running out', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await resolution(page, 10);

  await page.goto('/facturas-venta/nueva');

  await expect(
    page.getByText(
      /Tu resolución de facturación se está acabando: quedan 10 números/,
    ),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test('SAL-05 · an emitted invoice is voided with a reason and keeps its number', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  await resolution(page);
  const invoice = await emitted(page, await seed(page, nit));

  await page.goto(`/facturas-venta/${invoice.id as string}`);
  await page.getByRole('button', {name: 'Anular'}).click();
  const dialog = page.getByRole('dialog', {name: 'Anular la factura FE-1'});
  await dialog.getByRole('button', {name: 'Anular factura'}).click();
  await expect(
    dialog.getByText('Escribe el motivo de la anulación.'),
  ).toBeVisible();
  await dialog.getByLabel('Motivo de la anulación').fill('Precio equivocado');
  await dialog.getByRole('button', {name: 'Anular factura'}).click();

  await expect(page.getByText('Factura FE-1 anulada.')).toBeVisible();
  await expect(
    page.getByText(`Anulada el ${ddmmyyyy(day())}. Motivo: Precio equivocado`),
  ).toBeVisible();
  await expect(
    page.getByRole('heading', {name: 'Factura de venta FE-1'}),
  ).toBeVisible();
  await expect(page.getByRole('button', {name: 'Anular'})).toHaveCount(0);
  const journal = (await api(page, 'GET', '/ledger/journal')) as unknown as {
    items: unknown[];
  };
  expect(journal.items, 'the entry and its reversal').toHaveLength(2);
  expect(errors).toEqual([]);
});

test('SAL-06 · the list searches, filters by status and offers a way back', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  await resolution(page);
  const seeded = await seed(page, nit);
  await emitted(page, seeded);
  await draft(page, seeded);

  await page.goto('/facturas-venta');
  await expect(page.getByRole('row')).toHaveCount(3);
  await expect(page.getByRole('link', {name: 'FE-1'})).toBeVisible();
  await expect(page.getByRole('link', {name: 'Borrador'})).toBeVisible();

  await page.getByLabel('Estado').selectOption({label: 'Borrador'});
  await expect(page.getByRole('row')).toHaveCount(2);
  await expect(page.getByRole('link', {name: 'FE-1'})).toHaveCount(0);

  await page.getByLabel('Estado').selectOption({label: 'Anulada'});
  await expect(
    page.getByText('Ninguna factura coincide con estos filtros.'),
  ).toBeVisible();
  await page.getByRole('button', {name: 'Ver todo'}).click();
  await expect(page.getByRole('row')).toHaveCount(3);

  await page.getByRole('searchbox').fill('FE-1');
  await expect(page.getByRole('row')).toHaveCount(2);
  expect(errors).toEqual([]);
});

test('SAL-07 · an invoice is duplicated from the list as a new draft', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  await resolution(page);
  await emitted(page, await seed(page, nit));

  await page.goto('/facturas-venta');
  await page
    .getByRole('row', {name: /FE-1/})
    .getByRole('button', {name: 'Duplicar'})
    .click();

  await expect(
    page.getByText('Se creó un borrador nuevo a partir de la factura.'),
  ).toBeVisible();
  await expect(
    page.getByRole('heading', {name: 'Factura de venta · borrador'}),
  ).toBeVisible();
  await expect(page.getByRole('combobox', {name: 'Cliente'})).toHaveValue(
    'Distribuciones Andina S.A.S.',
  );
  await expect(page.getByRole('button', {name: 'Guardar'})).toBeVisible();
  expect(errors).toEqual([]);
});

test('SAL-08 · Emitir y enviar mails the PDF to the client', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  await resolution(page);
  const seeded = await seed(page, nit);
  const invoice = await draft(page, seeded);
  const since = new Date(Date.now() - 1000);

  await page.goto(`/facturas-venta/${invoice.id as string}`);
  await page.getByRole('button', {name: 'Emitir y enviar'}).click();
  const dialog = page.getByRole('dialog', {name: '¿Emitir la factura?'});
  await expect(
    dialog.getByText(/enviaremos el PDF al correo de facturación/),
  ).toBeVisible();
  await dialog.getByRole('button', {name: 'Emitir y enviar'}).click();

  await expect(
    page.getByText('Factura FE-1 emitida; el correo con el PDF va en camino.'),
  ).toBeVisible();
  const email = await emailTo(page.request, seeded.email, {
    subject: /Factura de venta FE-1/,
    since,
  });
  expect(email.text).toContain('FE-1');
  expect(errors).toEqual([]);
});

test('SAL-09 · the PDF of an invoice downloads', async ({newCompany}) => {
  const {page, nit} = await newCompany();
  await resolution(page);
  const invoice = await emitted(page, await seed(page, nit));

  const response = await page.request.get(
    `/api/v1/sales-invoices/${invoice.id as string}/pdf`,
  );

  expect(response.status()).toBe(200);
  expect(response.headers()['content-type']).toBe('application/pdf');
  expect((await response.body()).subarray(0, 5).toString()).toBe('%PDF-');
});

test('SAL-10 · another company’s invoice does not exist', async ({
  newCompany,
}) => {
  const first = await newCompany('Primera');
  await resolution(first.page);
  const invoice = await emitted(first.page, await seed(first.page, first.nit));
  await api(first.page, 'POST', '/auth/sign-out');
  const {page} = await newCompany('Segunda');

  await page.goto(`/facturas-venta/${invoice.id as string}`);

  await expect(page.getByText('Esta factura no existe.')).toBeVisible();
  const response = await page.request.get(
    `/api/v1/sales-invoices/${invoice.id as string}`,
  );
  expect(response.status()).toBe(404);
});
