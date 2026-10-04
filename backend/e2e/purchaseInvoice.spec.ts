import type {Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

// Cases PUR-01 – 12 of docs/tests/ui-regression.md (item 9 "purchase-invoice"): facturas de compra / gasto.

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

async function methodId(page: Page, name: string): Promise<string> {
  const {items} = (await api(page, 'GET', '/payment-methods')) as {
    items: {id: string; name: string}[];
  };
  return items.find((m) => m.name === name)!.id;
}

async function accountId(page: Page, code: string): Promise<string> {
  const {items} = (await api(
    page,
    'GET',
    `/accounts/search?q=${code}&purchases=1`,
  )) as {
    items: {id: string; code: string}[];
  };
  return items.find((a) => a.code === code)!.id;
}

/** A supplier, Servicios Andinos S.A.S. */
async function seedSupplier(page: Page): Promise<string> {
  const tercero = (await api(page, 'POST', '/terceros', {
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
  return tercero.id;
}

/** Yesterday in Colombia (a date the books are open on and not in the future). */
function yesterday(): string {
  const now = new Date(Date.now() - 5 * 3600 * 1000 - 24 * 3600 * 1000);
  return now.toISOString().slice(0, 10);
}

function inDays(days: number): string {
  return new Date(Date.now() - 5 * 3600 * 1000 + days * 24 * 3600 * 1000)
    .toISOString()
    .slice(0, 10);
}

/** A draft through the API: a service of 1 000 000 on 513595, IVA 19 %, ReteFuente 4 %, on credit (1 150 000). */
async function seedDraft(
  page: Page,
  supplier: string,
  supplierNumber: string | null = 'FAC-881',
): Promise<{id: string}> {
  return (await api(page, 'POST', '/purchase-invoices', {
    tercero_id: supplier,
    supplier_invoice_number: supplierNumber,
    issue_date: yesterday(),
    due_date: inDays(29),
    notes: null,
    lines: [
      {
        product_id: null,
        account_id: await accountId(page, '513595'),
        description: 'Mantenimiento de equipos',
        quantity: '1',
        unit_price: '1000000',
        discount: '0',
        charge_tax_id: await taxId(page, 'charge', 'IVA 19 %'),
        withholding_tax_id: await taxId(
          page,
          'withholding',
          'ReteFuente servicios 4 %',
        ),
      },
    ],
    payments: [
      {
        payment_method_id: await methodId(page, 'Crédito'),
        amount: '1150000.00',
        due_date: inDays(29),
      },
    ],
  })) as {id: string};
}

async function emit(page: Page, id: string): Promise<void> {
  await api(page, 'POST', `/purchase-invoices/${id}/emit`, {});
}

test('PUR-01 · a new company sees what the section is for and how to start', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await page.goto('/facturas-compra');

  await expect(
    page.getByRole('heading', {name: 'Facturas de compra'}),
  ).toBeVisible();
  await expect(
    page.getByText(/Aún no has registrado facturas de compra/),
  ).toBeVisible();
  await expect(
    page.getByRole('link', {name: 'Registrar la primera factura de compra'}),
  ).toBeVisible();
  await expect(page.getByRole('table')).toHaveCount(0);
  expect(errors).toEqual([]);
});

test('PUR-02 · a service bought on credit is saved as a draft from the form', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await seedSupplier(page);
  await page.goto('/facturas-compra');
  await page.getByRole('link', {name: 'Nueva factura de compra'}).click();

  await page.getByRole('combobox', {name: 'Proveedor'}).fill('Servicios');
  await page.getByRole('option', {name: /Servicios Andinos/}).click();
  await page.getByLabel('Número de factura del proveedor').fill('FAC-881');
  await page.getByLabel('Tipo de línea 1').selectOption('account');
  await page.getByLabel('Producto/Servicio o cuenta, línea 1').fill('513595');
  // The picker looks the code up (after a short pause) and shows the account it chose, as a person would wait for.
  await expect(
    page.getByLabel('Producto/Servicio o cuenta, línea 1'),
  ).toHaveValue(/^513595 · /);
  await page
    .getByLabel('Descripción, línea 1')
    .fill('Mantenimiento de equipos');
  await page.getByLabel('Cantidad, línea 1').fill('1');
  await page.getByLabel('Valor unitario, línea 1').fill('1000000');
  await page
    .getByLabel('Impuesto cargo, línea 1')
    .selectOption({label: 'IVA 19 %'});
  await page
    .getByLabel('Impuesto retención, línea 1')
    .selectOption({label: 'ReteFuente servicios 4 %'});
  await page.getByRole('button', {name: 'Agregar forma de pago'}).click();
  await page.getByLabel('Método de pago 1').selectOption({label: 'Crédito'});
  await expect(page.getByLabel('Valor de la forma de pago 1')).toHaveValue(
    '1150000.00',
  );
  await page.getByRole('button', {name: 'Guardar borrador'}).click();

  await expect(page.getByText('Borrador guardado.')).toBeVisible();
  await expect(
    page.getByRole('heading', {name: 'Factura de compra (borrador)'}),
  ).toBeVisible();
  await expect(page).toHaveURL(/\/facturas-compra\/[0-9a-f-]{36}$/);
  expect(errors).toEqual([]);
});

test('PUR-03 · emitting numbers the invoice, posts it and leaves it read-only', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  const draft = await seedDraft(page, await seedSupplier(page));
  await page.goto(`/facturas-compra/${draft.id}`);

  await page.getByRole('button', {name: 'Emitir'}).click();

  await expect(
    page.getByText('Factura FC-1 emitida y contabilizada.'),
  ).toBeVisible();
  await expect(
    page.getByRole('heading', {name: 'Factura de compra FC-1'}),
  ).toBeVisible();
  await expect(
    page.getByLabel('Número de factura del proveedor'),
  ).toBeDisabled();
  await expect(page.getByRole('button', {name: 'Emitir'})).toHaveCount(0);
  const journal = (await api(page, 'GET', '/ledger/journal')) as {
    items: {
      source_number: string;
      lines: {account_code: string; debit: string; credit: string}[];
    }[];
  };
  const entry = journal.items.find((e) => e.source_number === 'FC-1');
  expect(entry, 'the purchase is in the libro diario').toBeTruthy();
  expect(entry!.lines.map((l) => [l.account_code, l.debit, l.credit])).toEqual([
    ['513595', '1000000.00', '0.00'],
    ['240810', '190000.00', '0.00'],
    ['236525', '0.00', '40000.00'],
    ['22050501', '0.00', '1150000.00'],
  ]);
  expect(errors).toEqual([]);
});

test('PUR-04 · the supplier’s number is not recorded twice for the same supplier', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  const supplier = await seedSupplier(page);
  await seedDraft(page, supplier, 'FAC-881');
  const second = await seedDraft(page, supplier, null);
  await page.goto(`/facturas-compra/${second.id}`);

  await page.getByLabel('Número de factura del proveedor').fill('fac-881');
  await page.getByRole('button', {name: 'Guardar borrador'}).click();

  await expect(
    page
      .getByText(
        'Ya registraste una factura de este proveedor con este número.',
      )
      .first(),
  ).toBeVisible();
  await expect(
    page.getByLabel('Número de factura del proveedor'),
  ).toHaveAttribute('aria-invalid', 'true');
  expect(errors, 'the refusal, and nothing else').toEqual([
    'Failed to load resource: the server responded with a status of 422 (Unprocessable Content)',
  ]);
});

test('PUR-05 · emitting needs formas de pago that add up to the total neto', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  const draft = await seedDraft(page, await seedSupplier(page));
  await page.goto(`/facturas-compra/${draft.id}`);

  await page.getByLabel('Valor de la forma de pago 1').fill('1000000');
  await page.getByRole('button', {name: 'Emitir'}).click();

  await expect(page.getByText('Revisa los campos marcados.')).toBeVisible();
  await expect(
    page.getByText(/Faltan .*150\.000,00 para el total neto/).first(),
  ).toBeVisible();
  await expect(
    page.getByRole('heading', {name: 'Factura de compra (borrador)'}),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test('PUR-06 · the list shows each invoice with its numbers, money and actions', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  const supplier = await seedSupplier(page);
  const emitted = await seedDraft(page, supplier, 'FAC-881');
  await emit(page, emitted.id);
  await seedDraft(page, supplier, 'FAC-882');
  await page.goto('/facturas-compra');

  const rows = page.getByRole('row');
  await expect(rows).toHaveCount(3);
  const fc1 = page.getByRole('row', {name: /FC-1/});
  await expect(fc1).toContainText('Servicios Andinos S.A.S.');
  await expect(fc1).toContainText('FAC-881');
  await expect(fc1).toContainText('$ 1.150.000,00');
  await expect(fc1.getByRole('button', {name: 'Anular'})).toBeVisible();
  await expect(page.getByRole('row', {name: /Borrador/})).toContainText(
    'FAC-882',
  );
  expect(errors).toEqual([]);
});

test('PUR-07 · search and the status filter narrow the list, and "Ver todo" brings it back', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  const supplier = await seedSupplier(page);
  const emitted = await seedDraft(page, supplier, 'FAC-881');
  await emit(page, emitted.id);
  await seedDraft(page, supplier, 'FAC-882');
  await page.goto('/facturas-compra');
  await expect(page.getByRole('row')).toHaveCount(3);

  await page.getByRole('searchbox').fill('882');
  await expect(page.getByRole('row')).toHaveCount(2);
  await page.getByRole('searchbox').fill('');
  await expect(page.getByRole('row')).toHaveCount(3);
  await page.getByLabel('Estado').selectOption({label: 'Emitida'});
  await expect(page.getByRole('row')).toHaveCount(2);
  await page.getByLabel('Estado').selectOption({label: 'Pagada'});
  await expect(
    page.getByText('Ninguna factura de compra coincide con lo que buscas.'),
  ).toBeVisible();
  await page.getByRole('button', {name: 'Ver todo'}).click();
  await expect(page.getByRole('row')).toHaveCount(3);
  expect(errors).toEqual([]);
});

test('PUR-08 · voiding from the list asks why and keeps the number', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  const draft = await seedDraft(page, await seedSupplier(page));
  await emit(page, draft.id);
  await page.goto('/facturas-compra');

  await page
    .getByRole('row', {name: /FC-1/})
    .getByRole('button', {name: 'Anular'})
    .click();
  const dialog = page.getByRole('dialog');
  await dialog.getByRole('button', {name: 'Anular factura'}).click();
  await expect(
    dialog.getByText('Escribe por qué se anula la factura.'),
  ).toBeVisible();
  await dialog
    .getByLabel('Motivo de la anulación')
    .fill('Registrada dos veces');
  await dialog.getByRole('button', {name: 'Anular factura'}).click();

  await expect(page.getByText('Factura FC-1 anulada.')).toBeVisible();
  const row = page.getByRole('row', {name: /FC-1/});
  await expect(row).toHaveAttribute('title', 'Anulada');
  await expect(row.getByRole('button', {name: 'Anular'})).toHaveCount(0);
  expect(errors).toEqual([]);
});

test('PUR-09 · duplicating makes a new draft without the supplier’s number', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  const draft = await seedDraft(page, await seedSupplier(page));
  await emit(page, draft.id);
  await page.goto('/facturas-compra');

  await page
    .getByRole('row', {name: /FC-1/})
    .getByRole('button', {name: 'Duplicar'})
    .click();

  await expect(page.getByText(/Se creó un borrador igual/)).toBeVisible();
  await expect(
    page.getByRole('heading', {name: 'Factura de compra (borrador)'}),
  ).toBeVisible();
  await expect(page.getByLabel('Número de factura del proveedor')).toHaveValue(
    '',
  );
  await expect(page.getByLabel('Descripción, línea 1')).toHaveValue(
    'Mantenimiento de equipos',
  );
  expect(errors).toEqual([]);
});

test('PUR-10 · the supplier’s PDF is attached to a draft and can be downloaded', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  const draft = await seedDraft(page, await seedSupplier(page));
  await page.goto(`/facturas-compra/${draft.id}`);

  await page.getByLabel('Adjuntar archivo').setInputFiles({
    name: 'factura-881.pdf',
    mimeType: 'application/pdf',
    buffer: Buffer.from(
      '%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n',
    ),
  });

  await expect(page.getByText('Archivo adjunto.')).toBeVisible();
  const link = page.getByRole('link', {name: 'Descargar factura-881.pdf'});
  await expect(link).toBeVisible();
  const download = await page.request.get((await link.getAttribute('href'))!);
  expect(download.headers()['content-type']).toBe('application/pdf');

  await page.getByLabel('Adjuntar archivo').setInputFiles({
    name: 'foto.pdf',
    mimeType: 'application/pdf',
    buffer: Buffer.from('\x89PNG\r\n\x1a\nnot a pdf'),
  });
  await expect(
    page.getByText('Adjunta la factura del proveedor en PDF o XML.'),
  ).toBeVisible();
  expect(errors, 'the refusal, and nothing else').toEqual([
    'Failed to load resource: the server responded with a status of 415 (Unsupported Media Type)',
  ]);
});

test('PUR-11 · the PDF of an emitted invoice downloads', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const draft = await seedDraft(page, await seedSupplier(page));
  await emit(page, draft.id);
  await page.goto(`/facturas-compra/${draft.id}`);

  const href = await page.getByRole('link', {name: 'PDF'}).getAttribute('href');
  const response = await page.request.get(href!);
  expect(response.headers()['content-type']).toBe('application/pdf');
  expect((await response.body()).subarray(0, 5).toString()).toBe('%PDF-');
});

test('PUR-12 · a draft is deleted after confirming', async ({newCompany}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  const draft = await seedDraft(page, await seedSupplier(page));
  await page.goto(`/facturas-compra/${draft.id}`);

  await page.getByRole('button', {name: 'Eliminar borrador'}).click();
  await page
    .getByRole('dialog')
    .getByRole('button', {name: 'Eliminar'})
    .click();

  await expect(page.getByText('Borrador eliminado.')).toBeVisible();
  await expect(page).toHaveURL(/\/facturas-compra$/);
  await expect(
    page.getByText(/Aún no has registrado facturas de compra/),
  ).toBeVisible();
  expect(errors).toEqual([]);
});
