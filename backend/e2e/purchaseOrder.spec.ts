import type {Page} from '@playwright/test';
import {emailTo} from './support/mail';
import {consoleErrors, expect, test} from './support/test';

// Cases OC-01 – 14 of docs/tests/ui-regression.md (observations 2026-11, item 10 purchase-orders). Each test signs up
// its own company (`newCompany()` in ./support/test); an order needs no invoicing resolution (series OC comes from the
// numeración interna). OC-12 – 14 are run by hand.

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

async function idOf(page: Page, path: string, name: string): Promise<string> {
  const {items} = (await api(page, 'GET', path)) as unknown as {
    items: {id: string; name: string}[];
  };
  const found = items.find((item) => item.name === name);
  expect(found, `${name} in ${path}`).toBeTruthy();
  return found!.id;
}

async function accountId(page: Page, code: string): Promise<string> {
  const {items} = (await api(
    page,
    'GET',
    `/accounts/search?q=${code}&purchases=1`,
  )) as unknown as {items: {id: string; code: string}[]};
  return items.find((a) => a.code === code)!.id;
}

/** A supplier "Papelería Andina" with a billing e-mail. */
async function supplier(
  page: Page,
  nit: string,
): Promise<{id: string; email: string}> {
  const email = `ordenes-${nit}@papeleria.co`;
  const created = await api(page, 'POST', '/terceros/quick', {
    municipality_code: '11001',
    person_type: 'empresa',
    identification_type: 'nit',
    identification_number: String(Number(nit) + 7),
    business_name: 'Papelería Andina S.A.S.',
    email,
    roles: ['proveedor'],
  });
  return {id: created.id as string, email};
}

/** A draft of 2 × 500 000 by account 513595 with IVA 19 % (1 190 000), through the API. */
async function draft(
  page: Page,
  supplierId: string,
  over: Json = {},
): Promise<Json> {
  return api(page, 'POST', '/purchase-orders', {
    tercero_id: supplierId,
    issue_date: day(),
    expected_date: day(10),
    notes: 'Entregar en bodega',
    lines: [
      {
        product_id: null,
        account_id: await accountId(page, '513595'),
        description: 'Mantenimiento de equipos',
        quantity: '2',
        unit_price: '500000',
        discount: '0',
        charge_tax_id: await idOf(page, '/taxes?class=charge', 'IVA 19 %'),
        withholdings: [],
      },
    ],
    ...over,
  });
}

async function emitted(
  page: Page,
  supplierId: string,
  over: Json = {},
): Promise<Json> {
  const order = await draft(page, supplierId, over);
  return api(page, 'POST', `/purchase-orders/${order.id as string}/emit`, {});
}

async function journalCount(page: Page): Promise<number> {
  const journal = (await api(page, 'GET', '/ledger/journal')) as unknown as {
    items: unknown[];
  };
  return journal.items.length;
}

test('OC-01 · a company without orders is told what the section is for', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);

  await page.goto('/ordenes-compra');

  await expect(
    page.getByRole('heading', {name: 'Órdenes de compra'}),
  ).toBeVisible();
  await expect(
    page.getByText(/Aún no has hecho órdenes de compra/),
  ).toBeVisible();
  await expect(
    page.getByRole('link', {name: 'Crear la primera orden de compra'}),
  ).toBeVisible();
  await page.getByRole('link', {name: 'Nueva orden de compra'}).click();
  await expect(
    page.getByRole('heading', {name: 'Nueva orden de compra'}),
  ).toBeVisible();
  await expect(page.getByLabel('Tipo', {exact: true})).toHaveValue(
    'Orden de compra',
  );
  await expect(page.getByRole('combobox', {name: 'Proveedor'})).toBeVisible();
  await expect(page.getByLabel(/Entrega esperada/)).toBeVisible();
  await expect(
    page.getByText('Formas de pago'),
    'an order has no formas de pago',
  ).toHaveCount(0);
  expect(errors).toEqual([]);
});

test('OC-02 · an order is saved from the form, emitted as OC-1 and posts nothing', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  await supplier(page, nit);

  await page.goto('/ordenes-compra/nueva');
  await page.getByRole('combobox', {name: 'Proveedor'}).fill('Pap');
  await page.getByRole('option', {name: /Papelería Andina/}).click();
  await page.getByLabel(/Entrega esperada/).fill(ddmmyyyy(day(10)));
  await page.getByLabel('Tipo de línea 1').selectOption('account');
  await page.getByLabel('Producto/Servicio o cuenta, línea 1').fill('513595');
  await expect(
    page.getByLabel('Producto/Servicio o cuenta, línea 1'),
  ).toHaveValue(/^513595 · /);
  await page
    .getByLabel('Descripción, línea 1')
    .fill('Mantenimiento de equipos');
  await page.getByLabel('Cantidad, línea 1').fill('2');
  await page.getByLabel('Valor unitario, línea 1').fill('500000');
  await page
    .getByLabel('Impuesto cargo, línea 1')
    .selectOption({label: 'IVA 19 %'});

  await page.getByRole('button', {name: 'Guardar'}).click();
  await expect(page.getByText('Borrador guardado.')).toBeVisible();
  await expect(page).toHaveURL(/\/ordenes-compra\/[0-9a-f-]{36}$/);
  await expect(
    page.getByRole('heading', {name: 'Orden de compra · borrador'}),
  ).toBeVisible();

  await page.getByRole('button', {name: 'Emitir', exact: true}).click();
  const dialog = page.getByRole('dialog', {
    name: '¿Emitir la orden de compra?',
  });
  await dialog.getByRole('button', {name: 'Emitir', exact: true}).click();

  await expect(page.getByText('Orden de compra OC-1 emitida.')).toBeVisible();
  await expect(
    page.getByRole('heading', {name: 'Orden de compra OC-1'}),
  ).toBeVisible();
  await expect(
    page.getByText(
      /Esta orden de compra ya fue emitida: no se puede modificar/,
    ),
  ).toBeVisible();
  await expect(page.getByRole('button', {name: 'Guardar'})).toHaveCount(0);
  await expect(page.getByLabel(/Entrega esperada/)).toHaveValue(
    ddmmyyyy(day(10)),
  );
  expect(await journalCount(page), 'an order posts nothing').toBe(0);
  expect(errors).toEqual([]);
});

test('OC-03 · an order without its supplier or lines is not emitted and shows why', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);

  await page.goto('/ordenes-compra/nueva');
  await page.getByRole('button', {name: 'Emitir', exact: true}).click();

  await expect(page.getByRole('dialog')).toHaveCount(0);
  await expect(page.getByText('Revisa los campos marcados.')).toBeVisible();
  expect(errors).toEqual([]);
});

test('OC-04 · Emitir y enviar mails the PDF to the supplier', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const seeded = await supplier(page, nit);
  const order = await draft(page, seeded.id);
  const since = new Date(Date.now() - 1000);

  await page.goto(`/ordenes-compra/${order.id as string}`);
  await page.getByRole('button', {name: 'Emitir y enviar'}).click();
  const dialog = page.getByRole('dialog', {
    name: '¿Emitir la orden de compra?',
  });
  await expect(
    dialog.getByText(/enviaremos el PDF al correo del proveedor/),
  ).toBeVisible();
  await dialog.getByRole('button', {name: 'Emitir y enviar'}).click();

  await expect(
    page.getByText(
      'Orden de compra OC-1 emitida; el correo con el PDF va en camino.',
    ),
  ).toBeVisible();
  const email = await emailTo(page.request, seeded.email, {
    subject: /Orden de compra OC-1/,
    since,
  });
  expect(email.text).toContain('OC-1');
  expect(errors).toEqual([]);
});

test('OC-05 · the list shows the order and filters by status', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const seeded = await supplier(page, nit);
  const first = await emitted(page, seeded.id);
  await draft(page, seeded.id);

  await page.goto('/ordenes-compra');
  const row = page.getByRole('row').filter({hasText: 'OC-1'});
  await expect(row).toContainText('Papelería Andina S.A.S.');
  await expect(row).toContainText('Emitida');
  await expect(row).toContainText('1.190.000');
  await expect(row).toContainText(ddmmyyyy(day(10)));
  await expect(row.getByRole('link', {name: 'PDF'})).toHaveAttribute(
    'href',
    `/api/v1/purchase-orders/${first.id as string}/pdf`,
  );
  await expect(
    page.getByRole('row').filter({hasText: 'Borrador'}),
    'the draft has a row without number',
  ).toHaveCount(1);

  await page.getByLabel('Estado').selectOption({label: 'Borrador'});
  await expect(page.getByRole('row').filter({hasText: 'OC-1'})).toHaveCount(0);
  await page.getByLabel('Estado').selectOption({label: 'Anulada'});
  await expect(
    page.getByText('Ninguna orden de compra coincide con estos filtros.'),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test('OC-06 · the PDF is a document the supplier can read', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const seeded = await supplier(page, nit);
  const order = await emitted(page, seeded.id);

  const response = await page.request.get(
    `/api/v1/purchase-orders/${order.id as string}/pdf`,
  );

  expect(response.ok()).toBeTruthy();
  expect(response.headers()['content-type']).toBe('application/pdf');
  expect((await response.body()).subarray(0, 4).toString()).toBe('%PDF');
});

test('OC-07 · duplicating an emitted order opens a new draft dated today', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const seeded = await supplier(page, nit);
  const order = await emitted(page, seeded.id);

  await page.goto(`/ordenes-compra/${order.id as string}`);
  await page.getByRole('button', {name: 'Duplicar'}).click();

  await expect(page).toHaveURL(/\/ordenes-compra\/[0-9a-f-]{36}$/);
  await expect(
    page.getByText('Se creó un borrador nuevo a partir de la orden de compra.'),
  ).toBeVisible();
  await expect(
    page.getByRole('heading', {name: 'Orden de compra · borrador'}),
  ).toBeVisible();
  await expect(page.getByLabel('Descripción, línea 1')).toHaveValue(
    'Mantenimiento de equipos',
  );
  await expect(page.getByLabel('Fecha de elaboración')).toHaveValue(
    ddmmyyyy(day()),
  );
  await expect(
    page.getByRole('button', {name: 'Emitir', exact: true}),
  ).toBeEnabled();
  expect(errors).toEqual([]);
});

test('OC-08 · an emitted order is voided with a reason and keeps its number', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const seeded = await supplier(page, nit);
  const order = await emitted(page, seeded.id);

  await page.goto(`/ordenes-compra/${order.id as string}`);
  await page.getByRole('button', {name: 'Anular', exact: true}).click();
  const dialog = page.getByRole('dialog', {
    name: 'Anular la orden de compra OC-1',
  });
  await dialog.getByRole('button', {name: 'Anular orden de compra'}).click();
  await expect(
    dialog.getByText('Escribe el motivo de la anulación.'),
  ).toBeVisible();
  await dialog
    .getByLabel('Motivo de la anulación')
    .fill('El proveedor ya no vende');
  await dialog.getByRole('button', {name: 'Anular orden de compra'}).click();

  await expect(page.getByText('Orden de compra OC-1 anulada.')).toBeVisible();
  await expect(
    page.getByText(/Anulada el .* Motivo: El proveedor ya no vende/),
  ).toBeVisible();
  await expect(
    page.getByRole('button', {name: 'Anular', exact: true}),
  ).toHaveCount(0);
  await expect(
    page.getByRole('button', {name: 'Convertir en factura'}),
  ).toHaveCount(0);
  expect(await journalCount(page), 'nothing to reverse').toBe(0);
  expect(errors).toEqual([]);
});

test('OC-09 · converting makes a draft purchase invoice with the same lines and no number (OBS-10)', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const seeded = await supplier(page, nit);
  const order = await emitted(page, seeded.id);

  await page.goto(`/ordenes-compra/${order.id as string}`);
  await page.getByRole('button', {name: 'Convertir en factura'}).click();
  const dialog = page.getByRole('dialog', {
    name: 'Convertir en factura de compra',
  });
  await dialog.getByRole('button', {name: 'Convertir en factura'}).click();

  await expect(page).toHaveURL(/\/facturas-compra\/[0-9a-f-]{36}$/);
  await expect(
    page.getByRole('heading', {name: 'Factura de compra (borrador)'}),
  ).toBeVisible();
  await expect(
    page.getByText(
      /Se creó este borrador de factura a partir de la orden de compra OC-1/,
    ),
  ).toBeVisible();
  await expect(page.getByRole('combobox', {name: 'Proveedor'})).toHaveValue(
    'Papelería Andina S.A.S.',
  );
  await expect(page.getByLabel('Número de factura del proveedor')).toHaveValue(
    '',
  );
  await expect(page.getByLabel('Descripción, línea 1')).toHaveValue(
    'Mantenimiento de equipos',
  );
  await expect(page.getByLabel('Cantidad, línea 1')).toHaveValue('2');
  expect(await journalCount(page), 'nothing in the books').toBe(0);
  expect(errors).toEqual([]);
});

test('OC-10 · an order converts once and links to its invoice', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const seeded = await supplier(page, nit);
  const order = await emitted(page, seeded.id);
  await api(page, 'POST', `/purchase-orders/${order.id as string}/convert`, {});

  await page.goto(`/ordenes-compra/${order.id as string}`);

  await expect(
    page.getByText(/La orden de compra se convirtió en factura/),
  ).toBeVisible();
  await expect(page.getByRole('link', {name: 'Ver la factura'})).toBeVisible();
  await expect(
    page.getByRole('button', {name: 'Convertir en factura'}),
    'an order converts once',
  ).toHaveCount(0);
  await expect(
    page.getByRole('button', {name: 'Anular', exact: true}),
  ).toHaveCount(0);
  await page.goto('/ordenes-compra');
  await expect(page.getByRole('row').filter({hasText: 'OC-1'})).toContainText(
    'Convertida',
  );
  expect(errors).toEqual([]);
});

test('OC-11 · what the invoice refuses is listed and nothing is converted', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const seeded = await supplier(page, nit);
  const order = await emitted(page, seeded.id);
  await api(page, 'POST', `/terceros/${seeded.id}/deactivate`, {});

  await page.goto(`/ordenes-compra/${order.id as string}`);
  await page.getByRole('button', {name: 'Convertir en factura'}).click();
  await page
    .getByRole('dialog')
    .getByRole('button', {name: 'Convertir en factura'})
    .click();

  await expect(
    page.getByText('No se pudo convertir la orden de compra'),
  ).toBeVisible();
  await expect(page.getByText('Este tercero está inactivo.')).toBeVisible();
  await expect(page).toHaveURL(/\/ordenes-compra\/[0-9a-f-]{36}$/);
  await expect(
    page.getByRole('button', {name: 'Convertir en factura'}),
    'the order stays open',
  ).toBeVisible();
  const invoices = (await api(
    page,
    'GET',
    '/purchase-invoices',
  )) as unknown as {
    total: number;
  };
  expect(invoices.total).toBe(0);
  // The refused conversion is the one 422 the browser logs.
  expect(errors).toEqual([
    'Failed to load resource: the server responded with a status of 422 (Unprocessable Content)',
  ]);
});
