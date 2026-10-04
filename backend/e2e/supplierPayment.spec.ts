import type {Page} from '@playwright/test';
import {emailTo} from './support/mail';
import {consoleErrors, expect, test} from './support/test';

// Cases PAY-01 – 07 of docs/tests/ui-regression.md (item 12 "supplier-payment"). Each test signs up its own company,
// a supplier and registers purchase invoices on crédito through the API, as the purchase invoice screens would.

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
  supplier: string;
  email: string;
  invoices: {id: string; number: string; payable: string}[];
}

/**
 * A supplier "Servicios Andinos S.A.S." with an e-mail and, per amount given, an emitted purchase invoice of
 * 1.150.000 (a service with IVA 19 % and ReteFuente 4 %, AC-5) with that amount on crédito at 30 days (the rest cash).
 */
async function seed(
  page: Page,
  nit: string,
  credits: string[],
): Promise<Seeded> {
  const email = `pagos-${nit}@andinos.co`;
  const supplier = await api(page, 'POST', '/terceros/quick', {
    person_type: 'empresa',
    identification_type: 'nit',
    identification_number: String(Number(nit) + 7),
    business_name: 'Servicios Andinos S.A.S.',
    email,
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
  const expense = accounts.items.find((a) => a.code === '513595')!.id;
  const cash = await idOf(page, '/payment-methods', 'Efectivo');
  const credit = await idOf(page, '/payment-methods', 'Crédito');
  const invoices: Seeded['invoices'] = [];
  let n = 880;
  for (const amount of credits) {
    n += 1;
    const rest = (1150000 - Number(amount)).toFixed(2);
    const draft = await api(page, 'POST', '/purchase-invoices', {
      tercero_id: supplier.id,
      supplier_invoice_number: `FAC-${n}`,
      issue_date: day(-1),
      due_date: day(29),
      notes: null,
      lines: [
        {
          product_id: null,
          account_id: expense,
          description: 'Mantenimiento de equipos',
          quantity: '1',
          unit_price: '1000000',
          discount: '0',
          charge_tax_id: iva,
          withholding_tax_id: rete,
        },
      ],
      payments: [
        ...(rest === '0.00'
          ? []
          : [{payment_method_id: cash, amount: rest, due_date: null}]),
        {payment_method_id: credit, amount, due_date: day(30)},
      ],
    });
    const invoice = (await api(
      page,
      'POST',
      `/purchase-invoices/${draft.id as string}/emit`,
      {},
    )) as unknown as {
      id: string;
      number: string;
      payables: {id: string}[];
    };
    invoices.push({
      id: invoice.id,
      number: invoice.number,
      payable: invoice.payables[0]!.id,
    });
  }
  return {supplier: supplier.id as string, email, invoices};
}

/** On the new payment: the supplier by search, where the money goes out from and the amount paid. */
async function header(page: Page, method: string, amount: string) {
  await page.getByRole('combobox', {name: 'Proveedor'}).fill('Ser');
  await page.getByRole('option', {name: /Servicios Andinos/}).click();
  await page
    .getByLabel('De dónde sale el dinero')
    .selectOption({label: method});
  await page.getByLabel('Valor pagado').fill(amount);
}

async function pay(
  page: Page,
  supplier: string,
  payable: string,
  amount: string,
): Promise<Json> {
  return api(page, 'POST', '/supplier-payments', {
    tercero_id: supplier,
    receipt_date: day(),
    payment_method_id: await idOf(page, '/payment-methods', 'Efectivo'),
    amount,
    notes: null,
    allocations: [{payable_id: payable, amount}],
  });
}

test('PAY-01 · a company without payments is told what the section is for', async ({
  newCompany,
}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);

  await page.goto('/recibos-pago');

  await expect(
    page.getByRole('heading', {name: 'Recibos de pago'}),
  ).toBeVisible();
  await expect(
    page.getByText(/Aún no has registrado recibos de pago/),
  ).toBeVisible();
  await page
    .getByRole('link', {name: 'Registrar el primer recibo de pago'})
    .click();
  await expect(
    page.getByRole('heading', {name: 'Nuevo recibo de pago'}),
  ).toBeVisible();
  await expect(
    page.getByText(
      'Elige el proveedor para ver sus facturas pendientes de pago.',
    ),
  ).toBeVisible();
  const methods = page.getByLabel('De dónde sale el dinero').locator('option');
  await expect(methods).toContainText(['Efectivo', 'Transferencia']);
  await expect(methods.filter({hasText: /^Crédito$/})).toHaveCount(0);
  await expect(
    page.getByRole('button', {name: 'Guardar', exact: true}),
  ).toBeDisabled();
  expect(errors).toEqual([]);
});

test('PAY-02 · a payment clears the payable and pays the purchase invoice (AC-6)', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const {invoices} = await seed(page, nit, ['1150000.00']);

  await page.goto('/recibos-pago/nuevo');
  await header(page, 'Transferencia', '1.150.000');
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
    page.getByText('Recibo de pago RP-1 guardado y contabilizado.'),
  ).toBeVisible();
  await expect(
    page.getByRole('heading', {name: 'Recibo de pago RP-1'}),
  ).toBeVisible();
  await expect(page.getByText('$ 1.150.000,00').first()).toBeVisible();
  const invoice = await api(
    page,
    'GET',
    `/purchase-invoices/${invoices[0]!.id}`,
  );
  expect(invoice.status).toBe('paid');
  expect(invoice.balance).toBe('0.00');
  const trial = await api(
    page,
    'GET',
    `/ledger/trial-balance?from=${day(-30)}&to=${day(30)}`,
  );
  expect(trial.balanced).toBe(true);
  expect(errors).toEqual([]);
});

test('PAY-03 · the running difference holds Guardar until it is zero', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const {invoices} = await seed(page, nit, ['595000.00', '1150000.00']);
  const [first, second] = invoices;

  await page.goto('/recibos-pago/nuevo');
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

test('PAY-04 · a payment is voided with a reason, and the invoice is owed again', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const {supplier, invoices} = await seed(page, nit, ['1150000.00']);
  const payment = await pay(page, supplier, invoices[0]!.payable, '1150000.00');

  await page.goto(`/recibos-pago/${payment.id as string}`);
  await page.getByRole('button', {name: 'Anular'}).click();
  const dialog = page.getByRole('dialog', {
    name: 'Anular el recibo de pago RP-1',
  });
  await dialog.getByRole('button', {name: 'Anular recibo de pago'}).click();
  await expect(
    dialog.getByText('Escribe el motivo de la anulación.'),
  ).toBeVisible();
  await dialog
    .getByLabel('Motivo de la anulación')
    .fill('Transferencia rechazada');
  await dialog.getByRole('button', {name: 'Anular recibo de pago'}).click();

  await expect(page.getByText('Recibo de pago RP-1 anulado.')).toBeVisible();
  await expect(
    page.getByText(/Anulado el .*\. Motivo: Transferencia rechazada/),
  ).toBeVisible();
  await expect(page.getByRole('button', {name: 'Anular'})).toHaveCount(0);
  const invoice = await api(
    page,
    'GET',
    `/purchase-invoices/${invoices[0]!.id}`,
  );
  expect(invoice.status).toBe('emitted');
  expect(invoice.balance).toBe('1150000.00');
  expect(errors).toEqual([]);
});

test('PAY-05 · the list filters by status and offers a way back', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const {supplier, invoices} = await seed(page, nit, ['1150000.00']);
  for (const amount of ['100000.00', '200000.00']) {
    await pay(page, supplier, invoices[0]!.payable, amount);
  }

  await page.goto('/recibos-pago');
  await expect(page.getByRole('row')).toHaveCount(3);
  await expect(page.getByRole('link', {name: 'RP-2'})).toBeVisible();
  await page.getByLabel('Estado').selectOption({label: 'Anulado'});
  await expect(
    page.getByText('Ningún recibo coincide con estos filtros.'),
  ).toBeVisible();
  await page.getByRole('button', {name: 'Ver todo'}).click();
  await expect(page.getByRole('row')).toHaveCount(3);
  await page.getByLabel('Buscar').fill('RP-1');
  await expect(page.getByRole('row')).toHaveCount(2);
  expect(errors).toEqual([]);
});

test('PAY-06 · Guardar y enviar mails the PDF to the supplier', async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany();
  const errors = consoleErrors(page);
  const {email, invoices} = await seed(page, nit, ['595000.00']);
  const since = new Date();

  await page.goto('/recibos-pago/nuevo');
  await header(page, 'Efectivo', '595000');
  await page
    .getByLabel(`Valor a aplicar a ${invoices[0]!.number}`)
    .fill('595000');
  await page.getByRole('button', {name: 'Guardar y enviar'}).click();

  await expect(
    page.getByText(
      'Recibo de pago RP-1 guardado; el correo con el PDF va en camino.',
    ),
  ).toBeVisible();
  const mail = await emailTo(page.request, email, {
    subject: /Recibo de pago RP-1/,
    since,
  });
  expect(mail.text).toContain('recibo de pago RP-1');
  expect(errors).toEqual([]);
});

test('PAY-07 · a payment that does not exist says so', async ({newCompany}) => {
  const {page} = await newCompany();
  const errors = consoleErrors(page);

  await page.goto('/recibos-pago/01928b6e-0000-7000-8000-000000000000');

  await expect(page.getByText('Este recibo de pago no existe.')).toBeVisible();
  await expect(
    page.getByRole('link', {name: 'Volver a recibos de pago'}),
  ).toBeVisible();
  // The 404 itself is logged by the browser as a failed resource; nothing else.
  expect(errors.filter((e) => !e.includes('404'))).toEqual([]);
});
