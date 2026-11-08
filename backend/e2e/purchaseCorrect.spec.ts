import type {Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

// Cases COR-01 – 06 of docs/tests/ui-regression.md (observations 2026-11, item 7 purchase-correct). Each test signs up
// its own company (`newCompany()` in ./support/test). COR-04 – 06 are run by hand.

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

async function idByName(
  page: Page,
  path: string,
  name: string,
): Promise<string> {
  const {items} = (await api(page, 'GET', path)) as {
    items: {id: string; name: string}[];
  };
  const found = items.find((i) => i.name === name);
  expect(found, `the seeded ${name}`).toBeTruthy();
  return found!.id;
}

async function accountId(page: Page, code: string): Promise<string> {
  const {items} = (await api(
    page,
    'GET',
    `/accounts/search?q=${code}&purchases=1`,
  )) as {items: {id: string; code: string}[]};
  return items.find((a) => a.code === code)!.id;
}

function inDays(days: number): string {
  return new Date(Date.now() - 5 * 3600 * 1000 + days * 24 * 3600 * 1000)
    .toISOString()
    .slice(0, 10);
}

/**
 * An emitted factura de compra FC-1 through the API: a service of 1 000 000 on 513595 with IVA 19 % and ReteFuente
 * 4 % from Servicios Andinos S.A.S., supplier number FAC-881, on credit (1 150 000).
 */
async function seedEmitted(page: Page): Promise<{id: string}> {
  const supplier = (await api(page, 'POST', '/terceros', {
    municipality_code: '11001',
    person_type: 'empresa',
    identification_type: 'nit',
    identification_number: '800197268',
    check_digit: null,
    branch_code: '0',
    first_names: null,
    last_names: null,
    business_name: 'Servicios Andinos S.A.S.',
    trade_name: null,
    city: null,
    address: null,
    phones: [],
    billing_contact_name: null,
    email: 'facturas@andinos.co',
    mobile: null,
    postal_code: null,
    vat_regime: null,
    billing_contact_is_payer: true,
    fiscal_responsibilities: [],
    roles: ['proveedor'],
    receivable_account_id: null,
    payable_account_id: null,
    contacts: [],
  })) as {id: string};
  const draft = (await api(page, 'POST', '/purchase-invoices', {
    tercero_id: supplier.id,
    supplier_invoice_number: 'FAC-881',
    issue_date: inDays(-1),
    due_date: inDays(29),
    notes: 'Mantenimiento de octubre',
    lines: [
      {
        product_id: null,
        account_id: await accountId(page, '513595'),
        description: 'Mantenimiento de equipos',
        quantity: '1',
        unit_price: '1000000',
        discount: '0',
        charge_tax_id: await idByName(page, '/taxes?class=charge', 'IVA 19 %'),
        withholdings: [
          {
            tax_id: await idByName(
              page,
              '/taxes?class=withholding',
              'ReteFuente servicios 4 %',
            ),
          },
        ],
      },
    ],
    payments: [
      {
        payment_method_id: await idByName(page, '/payment-methods', 'Crédito'),
        amount: '1150000.00',
        due_date: inDays(29),
      },
    ],
  })) as {id: string};
  await api(page, 'POST', `/purchase-invoices/${draft.id}/emit`, {});
  return draft;
}

/** Corregir on the invoice's page, with a reason, landing on the copy. */
async function correctFromThePage(page: Page, id: string): Promise<void> {
  await page.goto(`/facturas-compra/${id}`);
  await page.getByRole('button', {name: 'Corregir', exact: true}).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByLabel('Qué se corrige').fill('Valor mal digitado');
  await dialog.getByRole('button', {name: 'Anular y corregir'}).click();
  await expect(
    page.getByText('Factura FC-1 anulada. Corrige este borrador y emítelo.'),
  ).toBeVisible();
}

test('COR-01 · Corregir voids an emitted invoice and opens an editable copy', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  const invoice = await seedEmitted(page);
  await page.goto(`/facturas-compra/${invoice.id}`);

  await expect(
    page.getByText(
      'Una factura emitida no se edita. Corregir la anula y abre una copia editable.',
    ),
  ).toBeVisible();
  await page.getByRole('button', {name: 'Corregir', exact: true}).click();
  const dialog = page.getByRole('dialog');
  await expect(
    dialog.getByRole('heading', {name: 'Corregir la factura FC-1'}),
  ).toBeVisible();
  await dialog.getByRole('button', {name: 'Anular y corregir'}).click();
  await expect(dialog.getByText('Escribe qué se corrige.')).toBeVisible();
  await dialog.getByLabel('Qué se corrige').fill('Valor mal digitado');
  await dialog.getByRole('button', {name: 'Anular y corregir'}).click();

  await expect(
    page.getByText('Factura FC-1 anulada. Corrige este borrador y emítelo.'),
  ).toBeVisible();
  await expect(
    page.getByRole('heading', {name: 'Factura de compra (borrador)'}),
  ).toBeVisible();
  await expect(page.getByLabel('Número de factura del proveedor')).toHaveValue(
    'FAC-881',
  );
  await expect(page.getByLabel('Descripción, línea 1')).toHaveValue(
    'Mantenimiento de equipos',
  );
  await expect(page.getByRole('button', {name: 'Emitir'})).toBeEnabled();
  await page.getByRole('link', {name: 'Ver la factura original'}).click();

  await expect(
    page.getByRole('heading', {name: 'Factura de compra FC-1'}),
  ).toBeVisible();
  await expect(
    page.getByText('Anulada: Corregida: Valor mal digitado'),
  ).toBeVisible();
  await expect(
    page.getByRole('link', {name: 'Ver la factura que la reemplaza'}),
  ).toBeVisible();
  await expect(
    page.getByRole('button', {name: 'Corregir', exact: true}),
  ).toHaveCount(0);
  expect(errors).toEqual([]);
});

test('COR-02 · the corrected copy is emitted again under the same supplier number', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  const invoice = await seedEmitted(page);
  await correctFromThePage(page, invoice.id);

  await page.getByRole('button', {name: 'Emitir'}).click();

  await expect(
    page.getByText('Factura FC-2 emitida y contabilizada.'),
  ).toBeVisible();
  await expect(
    page.getByRole('heading', {name: 'Factura de compra FC-2'}),
  ).toBeVisible();
  await expect(page.getByLabel('Número de factura del proveedor')).toHaveValue(
    'FAC-881',
  );
  expect(errors).toEqual([]);
});

test('COR-03 · the list marks the voided invoice Corregida and links to its replacement', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  const invoice = await seedEmitted(page);
  await correctFromThePage(page, invoice.id);
  const copyUrl = page.url();

  await page.goto('/facturas-compra');
  const voided = page.getByRole('row').filter({hasText: 'FC-1'});
  await voided.getByRole('link', {name: 'Corregida'}).click();

  await expect(page).toHaveURL(copyUrl);
  await expect(
    page.getByRole('heading', {name: 'Factura de compra (borrador)'}),
  ).toBeVisible();
  expect(errors).toEqual([]);
});
