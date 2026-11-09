import type {Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

// Cases ASI-01 – 06 of docs/tests/ui-regression.md (observations 2026-11, item 6 journal-entry-tab). Each test signs up
// its own company (`newCompany()` in ./support/test). ASI-05 and ASI-06 (facturas de compra, recibo de pago) are run
// by hand.

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

/** A day in Colombia, as the API dates documents. */
function today(): string {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: 'America/Bogota',
  }).format(new Date());
}

async function idOf(page: Page, path: string, name: string): Promise<string> {
  const {items} = (await api(page, 'GET', path)) as unknown as {
    items: {id: string; name: string}[];
  };
  const found = items.find((item) => item.name === name);
  expect(found, `${name} in ${path}`).toBeTruthy();
  return found!.id;
}

/** The resolution, a client and a service; an invoice of 1.190.000 half in cash and half on crédito, draft or emitted. */
async function invoice(
  page: Page,
  nit: string,
  emit: boolean,
): Promise<{id: string; number: string | null; client: string}> {
  await api(page, 'POST', '/company/resolution', {
    resolution_number: '18764000001234',
    prefix: 'FE',
    range_from: 1,
    range_to: 1000,
    valid_from: '2020-01-01',
    valid_to: '2099-12-31',
    mode: 'electronic',
  });
  const client = await api(page, 'POST', '/terceros/quick', {
    municipality_code: '11001',
    person_type: 'empresa',
    identification_type: 'nit',
    identification_number: String(Number(nit) + 7),
    business_name: 'Distribuciones Andina S.A.S.',
    email: `asi-${nit}@andina.co`,
    roles: ['cliente'],
  });
  const iva = await idOf(page, '/taxes?class=charge', 'IVA 19 %');
  const product = await api(page, 'POST', '/products/quick', {
    type: 'servicio',
    code: 'SRV-01',
    name: 'Consultoría mensual',
    sale_price: '1000000',
    price_includes_tax: false,
    charge_tax_id: iva,
    withholding_tax_id: null,
  });
  const draft = await api(page, 'POST', '/sales-invoices', {
    tercero_id: client.id,
    contact_id: null,
    seller_id: null,
    issue_date: today(),
    notes: null,
    lines: [
      {
        product_id: product.id,
        description: 'Consultoría mensual',
        quantity: '1',
        unit_price: '1000000',
        discount: '',
        charge_tax_id: iva,
        withholding_tax_id: null,
      },
    ],
    payments: [
      {
        payment_method_id: await idOf(page, '/payment-methods', 'Efectivo'),
        amount: '595000.00',
        due_date: null,
      },
      {
        payment_method_id: await idOf(page, '/payment-methods', 'Crédito'),
        amount: '595000.00',
        due_date: today(),
      },
    ],
  });
  const saved = emit
    ? await api(page, 'POST', `/sales-invoices/${draft.id as string}/emit`, {})
    : draft;
  return {
    id: saved.id as string,
    number: (saved.number as string | null) ?? null,
    client: client.id as string,
  };
}

test('ASI-01 · an emitted factura de venta shows its entry under Contabilización', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const sale = await invoice(page, nit, true);

  await page.goto(`/facturas-venta/${sale.id}`);
  await expect(page.getByRole('tab', {name: 'Documento'})).toBeVisible();
  await page.getByRole('tab', {name: 'Contabilización'}).click();

  await expect(page.getByText(/^Asiento \d+/)).toBeVisible();
  await expect(page.getByRole('cell', {name: '413595'})).toBeVisible();
  await expect(page.getByRole('cell', {name: '240805'})).toBeVisible();
  await expect(
    page.getByRole('cell', {name: 'Distribuciones Andina S.A.S.'}).first(),
  ).toBeVisible();
  const totals = page.getByRole('row', {name: /Totales/});
  await expect(totals.getByText('$ 1.190.000,00')).toHaveCount(2);
  expect(errors).toEqual([]);
});

test('ASI-02 · after the void the tab also shows the reversal', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const sale = await invoice(page, nit, true);
  await api(page, 'POST', `/sales-invoices/${sale.id}/void`, {
    reason: 'Factura duplicada',
  });

  await page.goto(`/facturas-venta/${sale.id}`);
  await page.getByRole('tab', {name: 'Contabilización'}).click();

  await expect(page.getByText(/^Asiento \d+/)).toHaveCount(2);
  await expect(page.getByText(/^Anulación del asiento \d+/)).toBeVisible();
  expect(errors).toEqual([]);
});

test('ASI-03 · a draft factura de venta has no Contabilización tab', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const sale = await invoice(page, nit, false);

  await page.goto(`/facturas-venta/${sale.id}`);

  await expect(
    page.getByRole('button', {name: 'Emitir', exact: true}),
  ).toBeVisible();
  await expect(page.getByRole('tab')).toHaveCount(0);
});

test('ASI-04 · a recibo de caja shows Dr caja, Cr clientes', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const sale = await invoice(page, nit, true);
  const {items} = (await api(
    page,
    'GET',
    `/cash-receipts/open-receivables?tercero_id=${sale.client}`,
  )) as unknown as {items: {id: string}[]};
  const receipt = await api(page, 'POST', '/cash-receipts', {
    tercero_id: sale.client,
    receipt_date: today(),
    payment_method_id: await idOf(page, '/payment-methods', 'Efectivo'),
    amount: '595000.00',
    notes: null,
    allocations: [{receivable_id: items[0]!.id, amount: '595000.00'}],
  });

  await page.goto(`/recibos-caja/${receipt.id as string}`);
  await page.getByRole('tab', {name: 'Contabilización'}).click();

  await expect(page.getByText(/^Asiento \d+/)).toBeVisible();
  await expect(page.getByRole('cell', {name: '13050501'})).toBeVisible();
  await expect(page.getByRole('row', {name: /Totales/})).toContainText(
    '$ 595.000,00',
  );
  expect(errors).toEqual([]);
});
