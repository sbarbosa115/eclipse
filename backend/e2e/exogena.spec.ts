import type {Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

// Cases EXO-01 – 08 of docs/tests/ui-regression.md (observations 2026-11, item 9 exogena): the simple ones, EXO-01,
// 02, 03 and 05. Each test signs up its own company (`newCompany()` in ./support/test) and makes its invoices through
// the API, as the document screens would.

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

const THIS_YEAR = today().slice(0, 4);
const LAST_YEAR = String(Number(THIS_YEAR) - 1);

async function taxId(page: Page, tax: 'charge' | 'withholding', name: string) {
  const {items} = (await api(
    page,
    'GET',
    `/taxes?class=${tax}`,
  )) as unknown as {items: {id: string; name: string}[]};
  const found = items.find((item) => item.name === name);
  expect(found, `${name} among the taxes`).toBeTruthy();
  return found!.id;
}

/**
 * An emitted purchase this year of 1.000.000 + IVA 19 % on 513595 from a new supplier in Medellín, with ReteFuente
 * compras 2,5 % (25.000) and ReteIVA 15 % (28.500): total neto 1.136.500, on crédito.
 */
async function purchase(page: Page, supplierName: string): Promise<void> {
  const supplier = await api(page, 'POST', '/terceros/quick', {
    municipality_code: '05001',
    person_type: 'empresa',
    identification_type: 'nit',
    identification_number: '830045678',
    business_name: supplierName,
    email: 'exogena@proveedor.co',
    roles: ['proveedor'],
  });
  const accounts = (await api(
    page,
    'GET',
    '/accounts/search?q=513595&purchases=1',
  )) as unknown as {items: {id: string; code: string}[]};
  const methods = (await api(page, 'GET', '/payment-methods')) as unknown as {
    items: {id: string; name: string}[];
  };
  const draft = await api(page, 'POST', '/purchase-invoices', {
    tercero_id: supplier.id,
    supplier_invoice_number: 'FAC-EXO-1',
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
        payment_method_id: methods.items.find((m) => m.name === 'Crédito')!
          .id,
        amount: '1136500.00',
        due_date: today(),
      },
    ],
  });
  await api(page, 'POST', `/purchase-invoices/${draft.id as string}/emit`, {});
}

test('EXO-01 · a new company opens the exógena on format 1001 of the year that ended, with nothing yet', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);

  await page.goto('/reportes/impuestos');
  await page.getByRole('tab', {name: 'Exógena'}).click();

  await expect(page).toHaveURL(/\/reportes\/impuestos\/exogena/);
  for (const format of [
    '1001 · Pagos',
    '1003 · Retenciones',
    '1005 · IVA descontable',
    '1006 · IVA generado',
    '1007 · Ingresos',
    '1008 · Por cobrar',
    '1009 · Por pagar',
  ]) {
    await expect(page.getByRole('tab', {name: format})).toBeVisible();
  }
  await expect(page.getByRole('tab', {name: '1001 · Pagos'})).toHaveAttribute(
    'aria-selected',
    'true',
  );
  await expect(page.getByLabel('Año gravable')).toHaveValue(LAST_YEAR);
  await expect(
    page.getByText(`Ningún tercero para el formato 1001 en ${LAST_YEAR}.`),
  ).toBeVisible();
  await expect(
    page.getByRole('link', {name: 'Descargar Formato 1001 en CSV'}),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test('EXO-02 · a purchase of the year is in 1001 by account and concept, and in 1005 and 1009', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await purchase(page, 'Servicios Andinos S.A.S.');

  await page.goto(`/reportes/impuestos/exogena?year=${THIS_YEAR}`);

  const row = page.getByRole('row', {name: /Servicios Andinos S\.A\.S\./});
  await expect(row).toContainText('5003');
  await expect(row).toContainText('513595');
  await expect(row).toContainText('NIT 830045678');
  await expect(row).toContainText('05001');
  await expect(row).toContainText('$ 1.000.000,00');
  await expect(row).toContainText('$ 25.000,00');
  await expect(row).toContainText('$ 28.500,00');

  await page.getByRole('tab', {name: '1005 · IVA descontable'}).click();
  await expect(
    page.getByRole('row', {name: /Servicios Andinos/}),
  ).toContainText('$ 190.000,00');
  await page.getByRole('tab', {name: '1009 · Por pagar'}).click();
  await expect(
    page.getByRole('row', {name: /Servicios Andinos/}),
  ).toContainText('$ 1.136.500,00');
  expect(errors).toEqual([]);
});

test('EXO-03 · a threshold groups the cuantías menores under 222222222', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await purchase(page, 'Servicios Andinos S.A.S.');
  await page.goto(`/reportes/impuestos/exogena?year=${THIS_YEAR}`);
  await expect(page.getByRole('row', {name: /Servicios Andinos/})).toBeVisible();

  await page.getByLabel('Cuantías menores de').fill('2.000.000');
  await page.getByLabel('Cuantías menores de').press('Enter');

  await expect(page).toHaveURL(/threshold=2000000/);
  const minor = page.getByRole('row', {name: /Cuantías menores/});
  await expect(minor).toContainText('222222222');
  await expect(minor).toContainText('$ 1.000.000,00');
  await expect(page.getByRole('row', {name: /Servicios Andinos/})).toHaveCount(
    0,
  );
});

test('EXO-05 · Configuración › Exógena lists the default concepts and edits them', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);

  await page.goto('/configuracion?tab=exogena');

  await expect(page.getByRole('tab', {name: 'Exógena'})).toHaveAttribute(
    'aria-selected',
    'true',
  );
  await expect(
    page.getByRole('row', {name: /1001 · Pagos 5135 5003/}),
  ).toBeVisible();
  await expect(
    page.getByRole('row', {name: /1007 · Ingresos 41 4001/}),
  ).toBeVisible();

  await page.getByRole('button', {name: 'Nueva fila'}).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByLabel('Prefijo de cuenta').fill('5115');
  await dialog.getByLabel('Concepto DIAN').fill('5002');
  await dialog.getByRole('button', {name: 'Guardar'}).click();
  await expect(page.getByText('Tabla de conceptos guardada.')).toBeVisible();
  const added = page.getByRole('row', {name: /1001 · Pagos 5115 5002/});
  await expect(added).toBeVisible();

  await added.getByRole('button', {name: 'Quitar'}).click();
  await page.getByRole('dialog').getByRole('button', {name: 'Quitar'}).click();
  await expect(page.getByText('Se quitó la fila 1001 · 5115.')).toBeVisible();
  await expect(page.getByRole('row', {name: /5115/})).toHaveCount(0);
  expect(errors).toEqual([]);
});
