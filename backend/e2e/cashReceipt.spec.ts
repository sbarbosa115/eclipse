import type {Page} from '@playwright/test';
import {emailTo} from './support/mail';
import {consoleErrors, expect, test} from './support/test';

// Cases RC-01 – 07 of docs/tests/ui-regression.md (item 11 "cash-receipt"). Each test signs up its own company,
// records its invoicing resolution and emits invoices on crédito through the API, as the sales invoice screens would.

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

interface Seeded {
  client: string;
  email: string;
  invoices: {id: string; number: string}[];
}

/**
 * The resolution, a client "Distribuciones Andina" with a billing e-mail and, per amount given, an emitted invoice of
 * 1.190.000 paid with that amount on crédito at 30 days (the rest in cash).
 */
async function seed(
  page: Page,
  nit: string,
  credits: string[],
): Promise<Seeded> {
  await api(page, 'POST', '/company/resolution', {
    resolution_number: '18764000001234',
    prefix: 'FE',
    range_from: 1,
    range_to: 1000,
    valid_from: day(-365),
    valid_to: day(365),
    mode: 'electronic',
  });
  const email = `cartera-${nit}@andina.co`;
  const client = await api(page, 'POST', '/terceros/quick', {
    person_type: 'empresa',
    identification_type: 'nit',
    identification_number: String(Number(nit) + 7),
    business_name: 'Distribuciones Andina S.A.S.',
    email,
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
  const cash = await idOf(page, '/payment-methods', 'Efectivo');
  const credit = await idOf(page, '/payment-methods', 'Crédito');
  const invoices: {id: string; number: string}[] = [];
  for (const amount of credits) {
    const rest = (1190000 - Number(amount)).toFixed(2);
    const draft = await api(page, 'POST', '/sales-invoices', {
      tercero_id: client.id,
      contact_id: null,
      seller_id: null,
      issue_date: day(),
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
        ...(rest === '0.00'
          ? []
          : [{payment_method_id: cash, amount: rest, due_date: null}]),
        {payment_method_id: credit, amount, due_date: day(30)},
      ],
    });
    const invoice = await api(
      page,
      'POST',
      `/sales-invoices/${draft.id as string}/emit`,
      {},
    );
    invoices.push({id: invoice.id as string, number: invoice.number as string});
  }
  return {client: client.id as string, email, invoices};
}

/** On the new receipt: the client by search, where the money comes in and the amount received. */
async function header(page: Page, method: string, amount: string) {
  await page.getByRole('combobox', {name: 'Cliente'}).fill('Dis');
  await page.getByRole('option', {name: /Distribuciones Andina/}).click();
  await page
    .getByLabel('Dónde ingresa el dinero')
    .selectOption({label: method});
  await page.getByLabel('Valor recibido').fill(amount);
}

test('RC-01 · a company without receipts is told what the section is for', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);

  await page.goto('/recibos-caja');

  await expect(
    page.getByRole('heading', {name: 'Recibos de caja'}),
  ).toBeVisible();
  await expect(
    page.getByText(/Aún no has registrado recibos de caja/),
  ).toBeVisible();
  await page.getByRole('link', {name: 'Registrar el primer recibo'}).click();
  await expect(
    page.getByRole('heading', {name: 'Nuevo recibo de caja'}),
  ).toBeVisible();
  await expect(
    page.getByText('Elige el cliente para ver sus facturas pendientes.'),
  ).toBeVisible();
  const methods = page.getByLabel('Dónde ingresa el dinero').locator('option');
  await expect(methods).toContainText(['Efectivo', 'Transferencia']);
  await expect(methods.filter({hasText: /^Crédito$/})).toHaveCount(0);
  await expect(
    page.getByRole('button', {name: 'Guardar', exact: true}),
  ).toBeDisabled();
  expect(errors).toEqual([]);
});

test('RC-02 · a receipt for the 30-day receivable pays the invoice (AC-4)', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const {invoices} = await seed(page, nit, ['595000.00']);

  await page.goto('/recibos-caja/nuevo');
  await header(page, 'Transferencia', '595.000');
  await page
    .getByRole('button', {
      name: `Pagar todo el saldo de ${invoices[0]!.number}`,
    })
    .click();
  await expect(
    page.getByRole('status').filter({hasText: 'Cuadra'}),
  ).toBeVisible();
  await page.getByRole('button', {name: 'Guardar', exact: true}).click();

  await expect(
    page.getByText('Recibo RC-1 guardado y contabilizado.'),
  ).toBeVisible();
  await expect(
    page.getByRole('heading', {name: 'Recibo de caja RC-1'}),
  ).toBeVisible();
  await expect(page.getByText('$ 595.000,00').first()).toBeVisible();
  const invoice = await api(page, 'GET', `/sales-invoices/${invoices[0]!.id}`);
  expect(invoice.status).toBe('paid');
  expect(invoice.balance).toBe('0.00');
  expect(errors).toEqual([]);
});

test('RC-03 · the running difference holds Guardar until it is zero', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const {invoices} = await seed(page, nit, ['595000.00', '1190000.00']);
  const [first, second] = invoices;

  await page.goto('/recibos-caja/nuevo');
  await header(page, 'Efectivo', '700000');
  const save = page.getByRole('button', {name: 'Guardar', exact: true});
  const status = page
    .getByRole('status')
    .filter({hasText: /Falta|Aplicaste|Cuadra/});

  await page.getByLabel(`Valor a aplicar a ${first!.number}`).fill('595000');
  await expect(status).toHaveText('Falta aplicar $ 105.000,00.');
  await expect(save).toBeDisabled();
  await page.getByLabel(`Valor a aplicar a ${second!.number}`).fill('200000');
  await expect(status).toHaveText('Aplicaste $ 95.000,00 de más.');
  await expect(save).toBeDisabled();
  await page.getByLabel(`Valor a aplicar a ${first!.number}`).fill('595000,01');
  await expect(page.getByText('Supera el saldo de la factura.')).toBeVisible();
  await page.getByLabel(`Valor a aplicar a ${first!.number}`).fill('500000');
  await expect(status).toContainText('Cuadra');
  await expect(save).toBeEnabled();
  expect(errors).toEqual([]);
});

test('RC-04 · a receipt is voided with a reason, and the invoice is owed again', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const {client, invoices} = await seed(page, nit, ['1190000.00']);
  const receivable = (
    (await api(
      page,
      'GET',
      `/cash-receipts/open-receivables?tercero_id=${client}`,
    )) as {
      items: {id: string}[];
    }
  ).items[0]!.id;
  const receipt = await api(page, 'POST', '/cash-receipts', {
    tercero_id: client,
    receipt_date: day(),
    payment_method_id: await idOf(page, '/payment-methods', 'Efectivo'),
    amount: '1190000.00',
    notes: null,
    allocations: [{receivable_id: receivable, amount: '1190000.00'}],
  });

  await page.goto(`/recibos-caja/${receipt.id as string}`);
  await page.getByRole('button', {name: 'Anular'}).click();
  const dialog = page.getByRole('dialog', {name: 'Anular el recibo RC-1'});
  await dialog.getByRole('button', {name: 'Anular recibo'}).click();
  await expect(
    dialog.getByText('Escribe el motivo de la anulación.'),
  ).toBeVisible();
  await dialog.getByLabel('Motivo de la anulación').fill('Cheque devuelto');
  await dialog.getByRole('button', {name: 'Anular recibo'}).click();

  await expect(page.getByText('Recibo RC-1 anulado.')).toBeVisible();
  await expect(
    page.getByText(/Anulado el .*\. Motivo: Cheque devuelto/),
  ).toBeVisible();
  await expect(page.getByRole('button', {name: 'Anular'})).toHaveCount(0);
  const invoice = await api(page, 'GET', `/sales-invoices/${invoices[0]!.id}`);
  expect(invoice.status).toBe('emitted');
  expect(invoice.balance).toBe('1190000.00');
  expect(errors).toEqual([]);
});

test('RC-05 · the list filters by status and offers a way back', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const {client} = await seed(page, nit, ['1190000.00']);
  const receivable = (
    (await api(
      page,
      'GET',
      `/cash-receipts/open-receivables?tercero_id=${client}`,
    )) as {
      items: {id: string}[];
    }
  ).items[0]!.id;
  const method = await idOf(page, '/payment-methods', 'Efectivo');
  for (const amount of ['100000.00', '200000.00']) {
    await api(page, 'POST', '/cash-receipts', {
      tercero_id: client,
      receipt_date: day(),
      payment_method_id: method,
      amount,
      notes: null,
      allocations: [{receivable_id: receivable, amount}],
    });
  }

  await page.goto('/recibos-caja');
  await expect(page.getByRole('row')).toHaveCount(3);
  await expect(page.getByRole('link', {name: 'RC-2'})).toBeVisible();
  await page.getByLabel('Estado').selectOption({label: 'Anulado'});
  await expect(
    page.getByText('Ningún recibo coincide con estos filtros.'),
  ).toBeVisible();
  await page.getByRole('button', {name: 'Ver todo'}).click();
  await expect(page.getByRole('row')).toHaveCount(3);
  await page.getByLabel('Buscar').fill('RC-1');
  await expect(page.getByRole('row')).toHaveCount(2);
  expect(errors).toEqual([]);
});

test('RC-06 · Guardar y enviar mails the PDF to the client', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const {email, invoices} = await seed(page, nit, ['595000.00']);
  const since = new Date();

  await page.goto('/recibos-caja/nuevo');
  await header(page, 'Efectivo', '595000');
  await page
    .getByLabel(`Valor a aplicar a ${invoices[0]!.number}`)
    .fill('595000');
  await page.getByRole('button', {name: 'Guardar y enviar'}).click();

  await expect(
    page.getByText('Recibo RC-1 guardado; el correo con el PDF va en camino.'),
  ).toBeVisible();
  const mail = await emailTo(page.request, email, {
    subject: /Recibo de caja RC-1/,
    since,
  });
  expect(mail.text).toContain('recibo de caja RC-1');
  expect(errors).toEqual([]);
});

test('RC-07 · a receipt that does not exist says so', async ({newCompany}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);

  await page.goto('/recibos-caja/01928b6e-0000-7000-8000-000000000000');

  await expect(page.getByText('Este recibo no existe.')).toBeVisible();
  await expect(
    page.getByRole('link', {name: 'Volver a recibos'}),
  ).toBeVisible();
  // The 404 itself is logged by the browser as a failed resource; nothing else.
  expect(errors.filter((e) => !e.includes('404'))).toEqual([]);
});
