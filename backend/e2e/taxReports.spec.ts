import type {Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

// Cases IMP-01 – 12 of docs/tests/ui-regression.md (observations 2026-11, item 8 tax-reports). Each test signs up its
// own company (`newCompany()` in ./support/test) and makes its invoices through the API, as the document screens would.

type Json = Record<string, unknown>;

async function api(
  page: Page,
  method: 'GET' | 'POST',
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

/** Today in Colombia, as the API dates documents. */
function today(): string {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: 'America/Bogota',
  }).format(new Date());
}

async function taxId(page: Page, tax: 'charge' | 'withholding', name: string) {
  const {items} = (await api(
    page,
    'GET',
    `/taxes?class=${tax}`,
  )) as unknown as {
    items: {id: string; name: string}[];
  };
  const found = items.find((item) => item.name === name);
  expect(found, `${name} among the taxes`).toBeTruthy();
  return found!.id;
}

async function credit(page: Page): Promise<string> {
  const {items} = (await api(page, 'GET', '/payment-methods')) as unknown as {
    items: {id: string; name: string}[];
  };
  return items.find((item) => item.name === 'Crédito')!.id;
}

async function tercero(
  page: Page,
  name: string,
  nit: string,
  role: 'cliente' | 'proveedor',
): Promise<string> {
  const created = await api(page, 'POST', '/terceros/quick', {
    municipality_code: '11001',
    person_type: 'empresa',
    identification_type: 'nit',
    identification_number: nit,
    business_name: name,
    email: 'impuestos@tercero.co',
    roles: [role],
  });
  return created.id as string;
}

/**
 * An emitted purchase of 1.000.000 + IVA 19 % from a new supplier with ReteFuente compras 2,5 % (25.000) and ReteIVA
 * 15 % (28.500): total neto 1.136.500, on crédito.
 */
async function purchase(page: Page, supplierName: string): Promise<void> {
  const supplier = await tercero(page, supplierName, '830045678', 'proveedor');
  const accounts = (await api(
    page,
    'GET',
    '/accounts/search?q=513595&purchases=1',
  )) as unknown as {items: {id: string; code: string}[]};
  const draft = await api(page, 'POST', '/purchase-invoices', {
    tercero_id: supplier,
    supplier_invoice_number: 'FAC-IMP-1',
    issue_date: today(),
    due_date: today(),
    notes: null,
    lines: [
      {
        product_id: null,
        account_id: accounts.items.find((a) => a.code === '513595')!.id,
        description: 'Mantenimiento de equipos',
        quantity: '1',
        unit_price: '1000000',
        discount: '0',
        charge_tax_id: await taxId(page, 'charge', 'IVA 19 %'),
        withholdings: [
          {
            tax_id: await taxId(
              page,
              'withholding',
              'ReteFuente compras 2,5 %',
            ),
          },
          {tax_id: await taxId(page, 'withholding', 'ReteIVA 15 %')},
        ],
      },
    ],
    payments: [
      {
        payment_method_id: await credit(page),
        amount: '1136500.00',
        due_date: today(),
      },
    ],
  });
  await api(page, 'POST', `/purchase-invoices/${draft.id as string}/emit`, {});
}

/** An emitted sale of 1.000.000 + IVA 19 % to a new client who withholds ReteFuente servicios 4 % (40.000). */
async function sale(page: Page, clientName: string): Promise<void> {
  await api(page, 'POST', '/company/resolution', {
    resolution_number: '18764000001234',
    prefix: 'FE',
    range_from: 1,
    range_to: 1000,
    valid_from: '2025-01-01',
    valid_to: '2030-12-31',
    mode: 'electronic',
  });
  const client = await tercero(page, clientName, '800197268', 'cliente');
  const iva = await taxId(page, 'charge', 'IVA 19 %');
  const product = await api(page, 'POST', '/products/quick', {
    type: 'servicio',
    code: 'CONS-IMP',
    name: 'Consultoría',
    sale_price: '1000000',
    price_includes_tax: false,
    charge_tax_id: iva,
    withholding_tax_id: null,
  });
  const draft = await api(page, 'POST', '/sales-invoices', {
    tercero_id: client,
    contact_id: null,
    seller_id: null,
    issue_date: today(),
    notes: null,
    lines: [
      {
        product_id: product.id,
        description: 'Consultoría',
        quantity: '1',
        unit_price: '1000000',
        discount: '',
        charge_tax_id: iva,
        withholdings: [
          {
            tax_id: await taxId(
              page,
              'withholding',
              'ReteFuente servicios 4 %',
            ),
          },
        ],
      },
    ],
    payments: [
      {
        payment_method_id: await credit(page),
        amount: '1150000.00',
        due_date: today(),
      },
    ],
  });
  await api(page, 'POST', `/sales-invoices/${draft.id as string}/emit`, {});
}

test('IMP-01 · a new company opens Impuestos on retenciones practicadas, with nothing yet', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);

  await page.goto('/');
  await page.getByRole('link', {name: 'Impuestos'}).click();

  await expect(page).toHaveURL(
    /\/reportes\/impuestos\/retenciones-practicadas$/,
  );
  await expect(page.getByRole('heading', {name: 'Impuestos'})).toBeVisible();
  for (const tab of [
    'Retenciones practicadas',
    'Retenciones sufridas',
    'IVA',
  ]) {
    await expect(page.getByRole('tab', {name: tab})).toBeVisible();
  }
  await expect(
    page.getByText('No hay retenciones practicadas en el periodo.'),
  ).toBeVisible();
  await expect(
    page.getByRole('link', {name: 'Descargar Retenciones practicadas en CSV'}),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test('IMP-02 · a purchase with two retenciones shows the supplier per kind, and the books agree', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await purchase(page, 'Servicios Andinos S.A.S.');

  await page.goto('/reportes/impuestos/retenciones-practicadas');

  const retefuente = page.getByRole('region', {name: /^Retefuente · /});
  await expect(retefuente.getByText('Servicios Andinos S.A.S.')).toBeVisible();
  await expect(
    retefuente.getByRole('cell', {name: '2,5 %', exact: true}),
  ).toBeVisible();
  await expect(retefuente.getByText('$ 25.000,00').first()).toBeVisible();
  await expect(
    retefuente.getByText(/Según libros \(2365\): \$ 25\.000,00/),
  ).toBeVisible();
  await expect(retefuente.getByText('Cuadra con los libros.')).toBeVisible();
  const reteiva = page.getByRole('region', {name: /^ReteIVA · /});
  await expect(reteiva.getByText('$ 190.000,00').first()).toBeVisible();
  await expect(reteiva.getByText('$ 28.500,00').first()).toBeVisible();
  expect(errors).toEqual([]);
});

test('IMP-03 · the Tipo filter keeps one kind', async ({newCompany}) => {
  const {page} = await newCompany();
  await purchase(page, 'Servicios Andinos S.A.S.');
  await page.goto('/reportes/impuestos/retenciones-practicadas');
  await expect(
    page.getByRole('region', {name: /^Retefuente · /}),
  ).toBeVisible();

  await page.getByLabel('Tipo').selectOption({label: 'ReteIVA'});

  await expect(page).toHaveURL(/kind=reteiva/);
  await expect(page.getByRole('region', {name: /^ReteIVA · /})).toBeVisible();
  await expect(page.getByRole('region', {name: /^Retefuente · /})).toHaveCount(
    0,
  );
});

test('IMP-04 · retenciones sufridas come from the sales invoices', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await sale(page, 'Cliente Retenedor S.A.S.');
  await page.goto('/reportes/impuestos/retenciones-practicadas');

  await page.getByRole('tab', {name: 'Retenciones sufridas'}).click();

  await expect(page).toHaveURL(/retenciones-sufridas/);
  const group = page.getByRole('region', {name: /^Retefuente · /});
  await expect(group.getByText('Cliente Retenedor S.A.S.')).toBeVisible();
  await expect(group.getByText(/NIT 800197268/)).toBeVisible();
  await expect(
    group.getByText(/Según libros \(135515\): \$ 40\.000,00/),
  ).toBeVisible();
});

test('IMP-05 · the IVA tab shows generado and descontable by rate and the saldo', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await sale(page, 'Cliente Retenedor S.A.S.');
  await purchase(page, 'Servicios Andinos S.A.S.');

  await page.goto('/reportes/impuestos/iva');

  const generated = page.getByRole('region', {name: 'IVA generado (ventas)'});
  await expect(generated.getByText('19 %')).toBeVisible();
  await expect(generated.getByText('$ 190.000,00').first()).toBeVisible();
  await expect(
    page.getByText(/Según libros \(240805\): \$ 190\.000,00/),
  ).toBeVisible();
  await expect(
    page.getByText(/Según libros \(240810\): \$ 190\.000,00/),
  ).toBeVisible();
  const saldo = page.getByRole('region', {name: 'Saldo de la declaración'});
  await expect(saldo.getByText('Saldo a pagar')).toBeVisible();
  const others = page.getByRole('region', {name: 'Otros valores del periodo'});
  await expect(others.getByText('$ 28.500,00')).toBeVisible();
  expect(errors).toEqual([]);
});

test('IMP-06 · the CSV downloads with the period in its name', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await purchase(page, 'Servicios Andinos S.A.S.');
  await page.goto('/reportes/impuestos/retenciones-practicadas');
  await expect(
    page.getByRole('region', {name: /^Retefuente · /}),
  ).toBeVisible();

  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page
      .getByRole('link', {name: 'Descargar Retenciones practicadas en CSV'})
      .click(),
  ]);

  expect(download.suggestedFilename()).toBe(
    `retenciones-practicadas-${today().slice(0, 7)}.csv`,
  );
});

test('IMP-08 · a period that ends before it starts is not asked for', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await page.goto('/reportes/impuestos/iva');
  await expect(
    page.getByRole('region', {name: 'IVA generado (ventas)'}),
  ).toBeVisible();

  const earlier = await page
    .getByLabel('Hasta')
    .locator('option')
    .nth(1)
    .getAttribute('value');
  await page.getByLabel('Hasta').selectOption(earlier!);

  await expect(page.getByText(/es anterior al mes «Desde»/)).toBeVisible();
  await expect(
    page.getByRole('link', {name: 'Descargar IVA en CSV'}),
  ).toHaveCount(0);
});
