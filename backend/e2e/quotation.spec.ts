import type {Page} from '@playwright/test';
import {emailTo} from './support/mail';
import {consoleErrors, expect, test} from './support/test';

// Cases COT-01 – 12 of docs/tests/ui-regression.md (item 10 "quotation"). Each test signs up its own company; a
// quotation needs no invoicing resolution (series C comes from the numeración interna).

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

/** A client "Distribuciones Andina" and a service SRV-01 of 1.000.000 + IVA 19 %. */
async function seed(
  page: Page,
  nit: string,
): Promise<{client: string; product: string; email: string}> {
  const email = `cotizaciones-${nit}@andina.co`;
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

/** A draft for 1.190.000, through the API. */
async function draft(
  page: Page,
  seeded: {client: string; product: string},
  over: Json = {},
): Promise<Json> {
  return api(page, 'POST', '/quotations', {
    tercero_id: seeded.client,
    contact_id: null,
    responsible_id: null,
    issue_date: day(),
    expiry_date: null,
    header: null,
    terms: null,
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
    ...over,
  });
}

async function emitted(
  page: Page,
  seeded: {client: string; product: string},
  over: Json = {},
): Promise<Json> {
  const quotation = await draft(page, seeded, over);
  return api(page, 'POST', `/quotations/${quotation.id as string}/emit`, {});
}

async function journalCount(page: Page): Promise<number> {
  const journal = (await api(page, 'GET', '/ledger/journal')) as unknown as {
    items: unknown[];
  };
  return journal.items.length;
}

test('COT-01 · a company without quotations is told what the section is for', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);

  await page.goto('/cotizaciones');

  await expect(page.getByRole('heading', {name: 'Cotizaciones'})).toBeVisible();
  await expect(page.getByText(/Aún no has hecho cotizaciones/)).toBeVisible();
  await expect(
    page.getByRole('link', {name: 'Crear la primera cotización'}),
  ).toBeVisible();
  await page.getByRole('link', {name: 'Nueva cotización'}).click();
  await expect(
    page.getByRole('heading', {name: 'Nueva cotización'}),
  ).toBeVisible();
  await expect(page.getByLabel('Tipo')).toHaveValue('Cotización');
  await expect(page.getByLabel(/Responsable de la cotización/)).toBeVisible();
  await expect(page.getByLabel('Válida hasta')).toHaveValue(ddmmyyyy(day(30)));
  await expect(page.getByLabel(/Encabezado/)).toBeVisible();
  await expect(page.getByLabel(/Condiciones comerciales/)).toBeVisible();
  await expect(
    page.getByText('Formas de pago'),
    'a quotation has no formas de pago',
  ).toHaveCount(0);
  expect(errors).toEqual([]);
});

test('COT-02 · a quotation is saved with its own fields, emitted as C-1 and posts nothing', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  await seed(page, nit);
  await api(page, 'POST', '/terceros/quick', {
    person_type: 'persona',
    identification_type: 'cc',
    identification_number: '1010101010',
    first_names: 'Elena',
    last_names: 'Pérez',
    email: 'elena@empresa.co',
    roles: ['empleado'],
  });

  await page.goto('/cotizaciones/nueva');
  await page.getByRole('combobox', {name: 'Cliente'}).fill('Dis');
  await page.getByRole('option', {name: /Distribuciones Andina/}).click();
  await page
    .getByRole('combobox', {name: 'Producto/Servicio, línea 1'})
    .fill('SRV');
  await page
    .getByRole('option', {name: /SRV-01 · Consultoría mensual/})
    .click();
  await expect(page.getByLabel(/Responsable de la cotización/)).toContainText(
    'Elena Pérez',
  );
  await page
    .getByLabel(/Responsable de la cotización/)
    .selectOption({label: 'Elena Pérez'});
  await page.getByLabel(/Encabezado/).fill('Estimados: esta es la propuesta.');
  await page.getByLabel(/Condiciones comerciales/).fill('50 % de anticipo.');

  await page.getByRole('button', {name: 'Guardar'}).click();
  await expect(page.getByText('Borrador guardado.')).toBeVisible();
  await expect(page).toHaveURL(/\/cotizaciones\/[0-9a-f-]{36}$/);
  await expect(
    page.getByRole('heading', {name: 'Cotización · borrador'}),
  ).toBeVisible();

  await page.getByRole('button', {name: 'Emitir', exact: true}).click();
  const dialog = page.getByRole('dialog', {name: '¿Emitir la cotización?'});
  await dialog.getByRole('button', {name: 'Emitir', exact: true}).click();

  await expect(page.getByText('Cotización C-1 emitida.')).toBeVisible();
  await expect(
    page.getByRole('heading', {name: 'Cotización C-1'}),
  ).toBeVisible();
  await expect(
    page.getByText(/Esta cotización ya fue emitida: no se puede modificar/),
  ).toBeVisible();
  await expect(page.getByRole('button', {name: 'Guardar'})).toHaveCount(0);
  await expect(page.getByLabel(/Condiciones comerciales/)).toHaveValue(
    '50 % de anticipo.',
  );
  expect(await journalCount(page), 'a quotation posts nothing').toBe(0);
  expect(errors).toEqual([]);
});

test('COT-03 · a quotation without its client or lines is not emitted and shows why', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);

  await page.goto('/cotizaciones/nueva');
  await page.getByRole('button', {name: 'Emitir', exact: true}).click();

  await expect(page.getByRole('dialog')).toHaveCount(0);
  await expect(page.getByText('Revisa los campos marcados.')).toBeVisible();
  expect(errors).toEqual([]);
});

test('COT-04 · Emitir y enviar mails the PDF to the client', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const seeded = await seed(page, nit);
  const quotation = await draft(page, seeded);
  const since = new Date(Date.now() - 1000);

  await page.goto(`/cotizaciones/${quotation.id as string}`);
  await page.getByRole('button', {name: 'Emitir y enviar'}).click();
  const dialog = page.getByRole('dialog', {name: '¿Emitir la cotización?'});
  await expect(
    dialog.getByText(/enviaremos el PDF al correo del cliente/),
  ).toBeVisible();
  await dialog.getByRole('button', {name: 'Emitir y enviar'}).click();

  await expect(
    page.getByText(
      'Cotización C-1 emitida; el correo con el PDF va en camino.',
    ),
  ).toBeVisible();
  const email = await emailTo(page.request, seeded.email, {
    subject: /Cotización C-1/,
    since,
  });
  expect(email.text).toContain('C-1');
  expect(errors).toEqual([]);
});

test('COT-05 · the client accepts one quotation and rejects another', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const seeded = await seed(page, nit);
  const first = await emitted(page, seeded);
  const second = await emitted(page, seeded);

  await page.goto(`/cotizaciones/${first.id as string}`);
  await page.getByRole('button', {name: 'Aceptar', exact: true}).click();
  await page
    .getByRole('dialog')
    .getByRole('button', {name: 'Aceptar', exact: true})
    .click();
  await expect(page.getByText('Cotización C-1 aceptada.')).toBeVisible();
  await expect(page.getByRole('button', {name: 'Rechazar'})).toHaveCount(0);

  await page.goto(`/cotizaciones/${second.id as string}`);
  await page.getByRole('button', {name: 'Rechazar', exact: true}).click();
  await page
    .getByRole('dialog')
    .getByRole('button', {name: 'Rechazar', exact: true})
    .click();
  await expect(page.getByText('Cotización C-2 rechazada.')).toBeVisible();
  await expect(
    page.getByRole('button', {name: 'Convertir a factura'}),
  ).toHaveCount(0);
  expect(errors).toEqual([]);
});

test('COT-06 · converting makes a draft invoice with the same lines and the quotation shows aceptada (AC-2)', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const quotation = await emitted(page, await seed(page, nit));

  await page.goto(`/cotizaciones/${quotation.id as string}`);
  await page.getByRole('button', {name: 'Convertir a factura'}).click();
  const dialog = page.getByRole('dialog', {
    name: 'Convertir en factura de venta',
  });
  await dialog.getByRole('button', {name: 'Convertir a factura'}).click();

  await expect(page).toHaveURL(/\/facturas-venta\/[0-9a-f-]{36}$/);
  await expect(
    page.getByRole('heading', {name: 'Factura de venta · borrador'}),
  ).toBeVisible();
  await expect(
    page.getByText(
      'Se creó este borrador de factura a partir de la cotización C-1.',
    ),
  ).toBeVisible();
  await expect(page.getByRole('combobox', {name: 'Cliente'})).toHaveValue(
    'Distribuciones Andina S.A.S.',
  );
  await expect(page.getByLabel('Descripción, línea 1')).toHaveValue(
    'Consultoría mensual',
  );

  await page.goto(`/cotizaciones/${quotation.id as string}`);
  await expect(page.getByText(/La cotización fue aceptada/)).toBeVisible();
  await expect(page.getByRole('link', {name: 'Ver la factura'})).toBeVisible();
  await expect(
    page.getByRole('button', {name: 'Convertir a factura'}),
    'a quotation converts once',
  ).toHaveCount(0);
  expect(await journalCount(page), 'nothing in the books').toBe(0);
  expect(errors).toEqual([]);
});

test('COT-07 · what the invoice refuses is listed on the quotation and nothing is converted', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const seeded = await seed(page, nit);
  const quotation = await emitted(page, seeded);
  await api(page, 'POST', `/products/${seeded.product}/deactivate`, {});

  await page.goto(`/cotizaciones/${quotation.id as string}`);
  await page.getByRole('button', {name: 'Convertir a factura'}).click();
  await page
    .getByRole('dialog')
    .getByRole('button', {name: 'Convertir a factura'})
    .click();

  await expect(
    page.getByText('No se pudo convertir la cotización'),
  ).toBeVisible();
  await expect(
    page.getByText('Línea 1: Este producto está inactivo.'),
  ).toBeVisible();
  await expect(page).toHaveURL(/\/cotizaciones\/[0-9a-f-]{36}$/);
  await expect(
    page.getByRole('button', {name: 'Convertir a factura'}),
  ).toBeVisible();
  const invoices = (await api(page, 'GET', '/sales-invoices')) as unknown as {
    total: number;
  };
  expect(invoices.total).toBe(0);
  expect(errors, 'only the refused conversion itself').toEqual([
    'Failed to load resource: the server responded with a status of 422 (Unprocessable Content)',
  ]);
});

test('COT-08 · an emitted quotation past its vencimiento reads as Vencida', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const seeded = await seed(page, nit);
  // Dated 40 days ago with the default 30 days of validity: the offer ended 10 days ago.
  const old = await emitted(page, seeded, {issue_date: day(-40)});
  await emitted(page, seeded);

  await page.goto('/cotizaciones');
  await expect(page.getByRole('row')).toHaveCount(3);
  await page.getByLabel('Estado').selectOption({label: 'Vencida'});
  await expect(page.getByRole('row')).toHaveCount(2);
  await expect(page.getByRole('link', {name: 'C-1'})).toBeVisible();
  await expect(
    page.getByRole('row', {name: /C-1/}).getByText('Vencida').first(),
  ).toBeVisible();

  await page.goto(`/cotizaciones/${old.id as string}`);
  await expect(
    page.getByText(new RegExp(`La oferta venció el ${ddmmyyyy(day(-10))}`)),
  ).toBeVisible();
  await expect(page.getByRole('button', {name: 'Aceptar'})).toHaveCount(0);
  await expect(
    page.getByRole('button', {name: 'Convertir a factura'}),
  ).toHaveCount(0);
  await expect(page.getByRole('button', {name: 'Anular'})).toBeVisible();
  expect(errors).toEqual([]);
});

test('COT-09 · an emitted quotation is voided with a reason and keeps its number', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const quotation = await emitted(page, await seed(page, nit));

  await page.goto(`/cotizaciones/${quotation.id as string}`);
  await page.getByRole('button', {name: 'Anular'}).click();
  const dialog = page.getByRole('dialog', {name: 'Anular la cotización C-1'});
  await dialog.getByRole('button', {name: 'Anular cotización'}).click();
  await expect(
    dialog.getByText('Escribe el motivo de la anulación.'),
  ).toBeVisible();
  await dialog.getByLabel('Motivo de la anulación').fill('Cambió el alcance');
  await dialog.getByRole('button', {name: 'Anular cotización'}).click();

  await expect(page.getByText('Cotización C-1 anulada.')).toBeVisible();
  await expect(
    page.getByText(`Anulada el ${ddmmyyyy(day())}. Motivo: Cambió el alcance`),
  ).toBeVisible();
  await expect(page.getByRole('button', {name: 'Anular'})).toHaveCount(0);
  expect(errors).toEqual([]);
});

test('COT-10 · the list searches, filters by status and duplicates', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const seeded = await seed(page, nit);
  await emitted(page, seeded);
  await draft(page, seeded);

  await page.goto('/cotizaciones');
  await expect(page.getByRole('row')).toHaveCount(3);
  await page.getByLabel('Estado').selectOption({label: 'Borrador'});
  await expect(page.getByRole('row')).toHaveCount(2);
  await page.getByLabel('Estado').selectOption({label: 'Anulada'});
  await expect(
    page.getByText('Ninguna cotización coincide con estos filtros.'),
  ).toBeVisible();
  await page.getByRole('button', {name: 'Ver todo'}).click();
  await expect(page.getByRole('row')).toHaveCount(3);
  await page.getByRole('searchbox').fill('C-1');
  await expect(page.getByRole('row')).toHaveCount(2);

  await page
    .getByRole('row', {name: /C-1/})
    .getByRole('button', {name: 'Duplicar'})
    .click();
  await expect(
    page.getByText('Se creó un borrador nuevo a partir de la cotización.'),
  ).toBeVisible();
  await expect(
    page.getByRole('heading', {name: 'Cotización · borrador'}),
  ).toBeVisible();
  await expect(page.getByLabel('Válida hasta')).toHaveValue(ddmmyyyy(day(30)));
  expect(errors).toEqual([]);
});

test('COT-11 · what a person types in the texts is shown as typed, never as markup', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const seeded = await seed(page, nit);
  const quotation = await emitted(page, seeded, {
    header: '<script>window.__pwned = true</script><b>Hola</b>',
    terms: '<img src=x onerror="window.__pwned = true">',
  });

  await page.goto(`/cotizaciones/${quotation.id as string}`);

  await expect(page.getByLabel(/Encabezado/)).toHaveValue(
    '<script>window.__pwned = true</script><b>Hola</b>',
  );
  expect(await page.evaluate(() => 'x' in window && 'pwned' in window)).toBe(
    false,
  );
  expect(await page.locator('.doc-editor b, .doc-editor img').count()).toBe(0);
  expect(errors).toEqual([]);
});

test('COT-12 · the PDF downloads, and another company’s quotation does not exist', async ({
  newCompany,
}) => {
  const first = await newCompany('Primera');
  const quotation = await emitted(
    first.page,
    await seed(first.page, first.nit),
  );

  const response = await first.page.request.get(
    `/api/v1/quotations/${quotation.id as string}/pdf`,
  );
  expect(response.status()).toBe(200);
  expect(response.headers()['content-type']).toBe('application/pdf');
  expect((await response.body()).subarray(0, 5).toString()).toBe('%PDF-');

  await api(first.page, 'POST', '/auth/sign-out');
  const {page} = await newCompany('Segunda');
  await page.goto(`/cotizaciones/${quotation.id as string}`);
  await expect(page.getByText('Esta cotización no existe.')).toBeVisible();
  const other = await page.request.get(
    `/api/v1/quotations/${quotation.id as string}`,
  );
  expect(other.status()).toBe(404);
});
