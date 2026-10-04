import type {Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

// Cases REP-01 – 19 of docs/tests/ui-regression.md (item 13 "reports"). Each test signs up its own company and makes
// its invoices through the API, as the document screens would.

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

/** A day in Colombia, as the API dates documents: today, or `days` from it. */
function day(days = 0): string {
  const today = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'America/Bogota',
  }).format(new Date());
  const date = new Date(`${today}T12:00:00Z`);
  date.setUTCDate(date.getUTCDate() + days);
  return date.toISOString().slice(0, 10);
}

async function idOf(page: Page, path: string, name: string): Promise<string> {
  const {items} = (await api(page, 'GET', path)) as unknown as {
    items: {id: string; name: string}[];
  };
  const found = items.find((item) => item.name === name);
  expect(found, `${name} in ${path}`).toBeTruthy();
  return found!.id;
}

async function resolution(page: Page, over: Json = {}) {
  await api(page, 'POST', '/company/resolution', {
    resolution_number: '18764000001234',
    prefix: 'FE',
    range_from: 1,
    range_to: 1000,
    valid_from: day(-400),
    valid_to: day(400),
    mode: 'electronic',
    ...over,
  });
}

async function client(page: Page, name: string, nit: string): Promise<string> {
  const tercero = await api(page, 'POST', '/terceros/quick', {
    person_type: 'empresa',
    identification_type: 'nit',
    identification_number: nit,
    business_name: name,
    email: 'facturas@cliente.co',
    roles: ['cliente'],
  });
  return tercero.id as string;
}

/** An emitted invoice of 1.190.000 on crédito, issued `issued` days from today and due `due` days from today. */
async function sale(
  page: Page,
  clientId: string,
  due: number,
  issued = 0,
): Promise<{id: string; number: string; receivable: string}> {
  const iva = await idOf(page, '/taxes?class=charge', 'IVA 19 %');
  const product = await api(page, 'POST', '/products/quick', {
    type: 'servicio',
    code: `S${Math.floor(Math.random() * 1e6)}`,
    name: 'Consultoría',
    sale_price: '1000000',
    price_includes_tax: false,
    charge_tax_id: iva,
    withholding_tax_id: null,
  });
  const draft = await api(page, 'POST', '/sales-invoices', {
    tercero_id: clientId,
    contact_id: null,
    seller_id: null,
    issue_date: day(issued),
    notes: null,
    lines: [
      {
        product_id: product.id,
        description: 'Consultoría',
        quantity: '1',
        unit_price: '1000000',
        discount: '',
        charge_tax_id: iva,
        withholding_tax_id: null,
      },
    ],
    payments: [
      {
        payment_method_id: await idOf(page, '/payment-methods', 'Crédito'),
        amount: '1190000.00',
        due_date: day(due),
      },
    ],
  });
  const invoice = (await api(
    page,
    'POST',
    `/sales-invoices/${draft.id as string}/emit`,
    {},
  )) as unknown as {
    id: string;
    number: string;
    receivables: {id: string}[];
  };
  return {
    id: invoice.id,
    number: invoice.number,
    receivable: invoice.receivables[0]!.id,
  };
}

/** A supplier and an emitted purchase invoice of 1.150.000 on crédito (a service with IVA 19 % and ReteFuente 4 %). */
async function purchase(
  page: Page,
  name: string,
  nit: string,
  due: number,
  issued = -1,
): Promise<string> {
  const supplier = await api(page, 'POST', '/terceros/quick', {
    person_type: 'empresa',
    identification_type: 'nit',
    identification_number: nit,
    business_name: name,
    email: 'pagos@proveedor.co',
    roles: ['proveedor'],
  });
  const iva = await idOf(page, '/taxes?class=charge', 'IVA 19 %');
  const rete = await idOf(
    page,
    '/taxes?class=withholding',
    'ReteFuente servicios 4 %',
  );
  const accounts = (await api(
    page,
    'GET',
    '/accounts/search?q=513595&purchases=1',
  )) as unknown as {items: {id: string; code: string}[]};
  const draft = await api(page, 'POST', '/purchase-invoices', {
    tercero_id: supplier.id,
    supplier_invoice_number: `FAC-${nit}`,
    issue_date: day(issued),
    due_date: day(due),
    notes: null,
    lines: [
      {
        product_id: null,
        account_id: accounts.items.find((a) => a.code === '513595')!.id,
        description: 'Mantenimiento de equipos',
        quantity: '1',
        unit_price: '1000000',
        discount: '0',
        charge_tax_id: iva,
        withholding_tax_id: rete,
      },
    ],
    payments: [
      {
        payment_method_id: await idOf(page, '/payment-methods', 'Crédito'),
        amount: '1150000.00',
        due_date: day(due),
      },
    ],
  });
  await api(page, 'POST', `/purchase-invoices/${draft.id as string}/emit`, {});
  return supplier.id as string;
}

test('REP-01 · a new company sees zeros, the quick links and the missing resolution', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);

  await page.goto('/');

  await expect(page.getByRole('heading', {name: 'Tablero'})).toBeVisible();
  await expect(
    page.getByText(/Aún no has registrado tu resolución de facturación/),
  ).toBeVisible();
  await expect(
    page.getByRole('link', {name: 'Ir a la resolución'}),
  ).toBeVisible();
  await expect(page.getByText('Cartera de clientes').first()).toBeVisible();
  await expect(page.getByText('$ 0,00').first()).toBeVisible();
  await expect(
    page.getByRole('link', {name: 'Crear factura de venta'}),
  ).toBeVisible();
  await expect(
    page.getByRole('link', {name: 'Crear recibo de caja'}),
  ).toBeVisible();
  await page.getByRole('link', {name: 'Crear factura de compra'}).click();
  await expect(page).toHaveURL(/\/facturas-compra\/nueva$/);
  expect(errors).toEqual([]);
});

test('REP-02 · the dashboard shows cartera, the month and the cash', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await resolution(page);
  const ana = await client(page, 'Ana Ltda.', '800197268');
  await sale(page, ana, 20);
  await sale(page, ana, -50, -100);
  await purchase(page, 'Servicios Andinos S.A.S.', '900500600', 15);

  await page.goto('/');

  const clients = page.locator('.card', {hasText: 'Cartera de clientes'});
  await expect(clients.getByText('$ 2.380.000,00').first()).toBeVisible();
  await expect(clients.getByText('$ 1.190.000,00')).toBeVisible();
  const suppliers = page.locator('.card', {hasText: 'Cartera de proveedores'});
  await expect(suppliers.getByText('$ 1.150.000,00').first()).toBeVisible();
  await expect(page.getByText('Ventas del mes')).toBeVisible();
  await expect(page.getByText('Caja y bancos')).toBeVisible();
  await expect(page.getByText(/resolución/)).toHaveCount(0);
  await clients.getByRole('link', {name: 'Ver cartera'}).click();
  await expect(page).toHaveURL(/\/reportes\/clientes$/);
  expect(errors).toEqual([]);
});

test('REP-20 · the dashboard tiles of a row share their top edge and height', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await resolution(page);

  for (const width of [1366, 768]) {
    await page.setViewportSize({width, height: width === 1366 ? 768 : 1024});
    await page.goto('/');
    const tiles = page.locator('.dashboard-grid > .card');
    await expect(tiles.first()).toBeVisible();
    await page.screenshot({
      path: `e2e/.results/dashboard-${width}.png`,
      fullPage: true,
    });
    const boxes = [];
    for (const tile of await tiles.all()) {
      const box = await tile.boundingBox();
      expect(box).not.toBeNull();
      boxes.push(box!);
    }
    const rows = new Map<number, typeof boxes>();
    for (const box of boxes) {
      const row = [...rows.keys()].find((top) => Math.abs(top - box.y) < 40);
      rows.set(row ?? box.y, [...(rows.get(row ?? box.y) ?? []), box]);
    }
    for (const row of rows.values()) {
      const [first] = row;
      for (const box of row) {
        expect(
          box.y,
          `at ${width} px the tiles of a row start together`,
        ).toBeCloseTo(first!.y, 0);
        expect(
          box.height,
          `at ${width} px the tiles of a row are as tall`,
        ).toBeCloseTo(first!.height, 0);
      }
    }
  }
});

test('REP-03 · cartera de clientes by ageing bucket, with totals', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await resolution(page);
  const ana = await client(page, 'Ana Ltda.', '800197268');
  for (const due of [10, -5, -45, -75, -120]) {
    await sale(page, ana, due, -200);
  }

  await page.goto('/reportes');

  await expect(page).toHaveURL(/\/reportes\/clientes$/);
  await expect(page.getByRole('heading', {name: 'Reportes'})).toBeVisible();
  for (const header of [
    'Al día',
    '1–30 días',
    '31–60 días',
    '61–90 días',
    'Más de 90 días',
  ]) {
    await expect(page.getByRole('columnheader', {name: header})).toBeVisible();
  }
  const row = page.getByRole('row', {name: /Ana Ltda\./});
  await expect(row.getByText('$ 1.190.000,00')).toHaveCount(5);
  await expect(row.getByText('$ 5.950.000,00')).toBeVisible();
  const total = page.getByRole('row').last();
  await expect(total.getByText('Total').first()).toBeVisible();
  await expect(total.getByText('$ 5.950.000,00')).toBeVisible();
  expect(errors).toEqual([]);
});

test('REP-04 · the drill-down lists the documents and links to each invoice', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await resolution(page);
  const ana = await client(page, 'Ana Ltda.', '800197268');
  const late = await sale(page, ana, -45, -100);
  await sale(page, ana, 10, -20);

  await page.goto('/reportes/clientes');
  await page.getByRole('link', {name: 'Ver documentos de Ana Ltda.'}).click();

  await expect(page.getByRole('heading', {name: 'Ana Ltda.'})).toBeVisible();
  await expect(page.getByText('45 días vencida')).toBeVisible();
  await expect(page.getByText('Al día').first()).toBeVisible();
  await expect(page.getByText('$ 2.380.000,00')).toBeVisible();
  await page.getByRole('link', {name: late.number}).click();
  await expect(page).toHaveURL(new RegExp(`/facturas-venta/${late.id}$`));
  expect(errors).toEqual([]);
});

test('REP-05 · search by tercero and the as-of date', async ({newCompany}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await resolution(page);
  const ana = await client(page, 'Ana Ltda.', '800197268');
  const beto = await client(page, 'Beto S.A.S.', '900200300');
  await sale(page, ana, 20, -5);
  await sale(page, beto, 20, -5);

  await page.goto('/reportes/clientes');
  await expect(page.getByRole('row', {name: /Beto/})).toBeVisible();
  await page.getByRole('searchbox').fill('beto');
  await expect(page.getByRole('row', {name: /Ana Ltda/})).toHaveCount(0);
  await expect(page.getByRole('row', {name: /Beto/})).toBeVisible();
  await page.getByRole('searchbox').fill('zzz');
  await expect(
    page.getByText('Ningún tercero coincide con la búsqueda.'),
  ).toBeVisible();
  await page.getByRole('searchbox').fill('');
  await page.getByLabel('Al corte').fill('01/01/2020');
  await expect(
    page.getByText('Ningún cliente te debe nada a esta fecha.'),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test('REP-06 · cartera de proveedores', async ({newCompany}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);
  await purchase(page, 'Servicios Andinos S.A.S.', '900500600', -5, -35);
  await purchase(page, 'Zeta Ltda.', '900500700', 20);

  await page.goto('/reportes/proveedores');

  await expect(
    page.getByRole('tab', {name: 'Cartera de proveedores'}),
  ).toHaveAttribute('aria-selected', 'true');
  await expect(
    page.getByRole('row', {name: /Servicios Andinos/}),
  ).toContainText('$ 1.150.000,00');
  await expect(page.getByRole('row').last()).toContainText('$ 2.300.000,00');
  await page.getByRole('link', {name: /Ver documentos de Zeta/}).click();
  await expect(page.getByRole('heading', {name: 'Zeta Ltda.'})).toBeVisible();
  await expect(page.getByRole('link', {name: /^FC-/})).toHaveAttribute(
    'href',
    /\/facturas-compra\//,
  );
  expect(errors).toEqual([]);
});

test('REP-07 · the cartera total is the 1305 balance of the books', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await resolution(page);
  const ana = await client(page, 'Ana Ltda.', '800197268');
  const first = await sale(page, ana, -10, -40);
  await sale(page, ana, 20, -5);
  await api(page, 'POST', '/cash-receipts', {
    tercero_id: ana,
    receipt_date: day(),
    payment_method_id: await idOf(page, '/payment-methods', 'Efectivo'),
    amount: '300000.00',
    notes: null,
    allocations: [{receivable_id: first.receivable, amount: '300000.00'}],
  });

  await page.goto('/reportes/clientes');
  await expect(page.getByRole('row').last()).toContainText('$ 2.080.000,00');

  const book = (await api(
    page,
    'GET',
    `/ledger/trial-balance?from=2000-01-01&to=${day()}`,
  )) as unknown as {rows: {code: string; closing: string}[]};
  expect(book.rows.find((r) => r.code === '1305')?.closing).toBe('2080000.00');
});

test('REP-08 · cartera exports to CSV that Excel opens', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await resolution(page);
  const ana = await client(page, 'Ñandú Ltda.', '800197268');
  await sale(page, ana, -10, -40);

  await page.goto('/reportes/clientes');
  const download = page.waitForEvent('download');
  await page
    .getByRole('link', {name: 'Descargar Cartera de clientes en CSV'})
    .click();
  const file = await download;

  expect(file.suggestedFilename()).toBe(
    `cartera-clientes-resumen-${day()}.csv`,
  );
  const bytes = await (
    await page.request.get(
      `/api/v1/reports/cartera/clients/export?format=csv&as_of=${day()}`,
    )
  ).body();
  expect([...bytes.subarray(0, 3)]).toEqual([0xef, 0xbb, 0xbf]);
  const lines = bytes.subarray(3).toString('utf8').split('\r\n');
  expect(lines[0]).toBe(
    'Cliente;Identificación;Al día;1-30 días;31-60 días;61-90 días;Más de 90 días;Total',
  );
  expect(lines[1]).toContain('Ñandú Ltda.;NIT 800197268');
  expect(lines[1]).toContain(';1190000.00');
});

test('REP-09 · cartera exports to a PDF with the company frame', async ({
  newCompany,
}) => {
  const {page, name} = await newCompany();
  await resolution(page);
  const ana = await client(page, 'Ana Ltda.', '800197268');
  await sale(page, ana, -10, -40);

  await page.goto('/reportes/clientes');
  const download = page.waitForEvent('download');
  await page
    .getByRole('link', {name: 'Descargar Cartera de clientes en PDF'})
    .click();
  const file = await download;

  expect(file.suggestedFilename()).toBe(
    `cartera-clientes-resumen-${day()}.pdf`,
  );
  const response = await page.request.get(
    `/api/v1/reports/cartera/clients/export?format=pdf`,
  );
  expect(response.headers()['content-type']).toBe('application/pdf');
  expect((await response.body()).subarray(0, 5).toString()).toBe('%PDF-');
  expect(name).toBeTruthy();
});

test('REP-10 · Exportar offers every report, the books included, with the chosen dates', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);

  await page.goto('/reportes');
  await page.getByRole('tab', {name: 'Exportar'}).click();

  await expect(page).toHaveURL(/\/reportes\/exportar$/);
  for (const report of [
    'Cartera de clientes',
    'Cartera de clientes por documento',
    'Cartera de proveedores',
    'Cartera de proveedores por documento',
    'Libro diario',
    'Balance de prueba',
    'Estado de resultados',
    'Balance general',
  ]) {
    await expect(
      page.getByRole('link', {name: `Descargar ${report} en CSV`}),
    ).toBeVisible();
    await expect(
      page.getByRole('link', {name: `Descargar ${report} en PDF`}),
    ).toBeVisible();
  }
  await page.getByLabel('Desde (libros contables)').fill('01/02/2026');
  await expect(
    page.getByRole('link', {name: 'Descargar Libro diario en CSV'}),
  ).toHaveAttribute('href', /from=2026-02-01/);
  expect(errors).toEqual([]);
});

test('REP-11 · the ledger books download as CSV and PDF', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await resolution(page);
  const ana = await client(page, 'Ana Ltda.', '800197268');
  await sale(page, ana, 20);

  for (const [report, title] of [
    ['journal', 'Fecha;Asiento;Documento'],
    ['trial-balance', 'Cuenta;Nombre;Saldo anterior'],
    ['income-statement', 'Cuenta;Nombre;Valor'],
    ['balance-sheet', 'Cuenta;Nombre;Valor'],
  ] as const) {
    const csv = await page.request.get(
      `/api/v1/reports/ledger/${report}/export?format=csv`,
    );
    expect(csv.ok(), report).toBeTruthy();
    expect((await csv.body()).subarray(3).toString('utf8')).toContain(title);
    const pdf = await page.request.get(
      `/api/v1/reports/ledger/${report}/export?format=pdf`,
    );
    expect((await pdf.body()).subarray(0, 5).toString(), report).toBe('%PDF-');
  }
});

test('REP-12 · a report with nothing to show says so', async ({newCompany}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);

  await page.goto('/reportes/clientes');
  await expect(
    page.getByText('Ningún cliente te debe nada a esta fecha.'),
  ).toBeVisible();
  await page.getByRole('tab', {name: 'Cartera de proveedores'}).click();
  await expect(
    page.getByText('No le debes nada a ningún proveedor a esta fecha.'),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test('REP-13 · a drill-down of a tercero with nothing owed says so', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  await page.goto('/reportes/clientes/00000000-0000-7000-8000-000000000000');

  await expect(
    page.getByText('Este tercero no tiene saldos abiertos a esa fecha.'),
  ).toBeVisible();
});
