import type {Page} from '@playwright/test';
import {expect, test} from './support/test';

// Cases COD-01 – 05 of docs/tests/ui-regression.md (observations 2026-11, item 5 product-code). Each test signs up its
// own company; a quotation needs no invoicing resolution.

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

async function ivaId(page: Page): Promise<string> {
  const {items} = (await api(
    page,
    'GET',
    '/taxes?class=charge',
  )) as unknown as {items: {id: string; name: string}[]};
  return items.find((tax) => tax.name === 'IVA 19 %')!.id;
}

/** An emitted quotation of the service SRV-01, and the product recoded afterwards to NUEVO-9. */
async function recodedQuotation(
  page: Page,
  nit: string,
): Promise<{quotation: Json; product: string}> {
  const iva = await ivaId(page);
  const client = await api(page, 'POST', '/terceros/quick', {
    municipality_code: '11001',
    person_type: 'empresa',
    identification_type: 'nit',
    identification_number: String(Number(nit) + 7),
    business_name: 'Distribuciones Andina S.A.S.',
    email: `codigos-${nit}@andina.co`,
    roles: ['cliente'],
  });
  const product = await api(page, 'POST', '/products/quick', {
    type: 'servicio',
    code: 'SRV-01',
    name: 'Consultoría mensual',
    sale_price: '1000000',
    price_includes_tax: false,
    charge_tax_id: iva,
    withholding_tax_id: null,
  });
  const draft = await api(page, 'POST', '/quotations', {
    tercero_id: client.id,
    contact_id: null,
    responsible_id: null,
    issue_date: new Intl.DateTimeFormat('en-CA', {
      timeZone: 'America/Bogota',
    }).format(new Date()),
    expiry_date: null,
    header: null,
    terms: null,
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
  });
  const quotation = await api(
    page,
    'POST',
    `/quotations/${draft.id as string}/emit`,
    {},
  );
  await api(page, 'PUT', `/products/${product.id as string}`, {
    type: 'servicio',
    code: 'NUEVO-9',
    name: 'Otro nombre',
    sale_price: '1000000',
    price_includes_tax: false,
    charge_tax_id: iva,
    withholding_tax_id: null,
  });
  return {quotation, product: product.id as string};
}

test("COD-01 · A saved quotation shows its lines' code in a Código column, even after the product is recoded", async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany('Códigos');
  const {quotation} = await recodedQuotation(page, nit);

  await page.goto(`/cotizaciones/${quotation.id as string}`);
  await expect(page.getByRole('columnheader', {name: 'Código'})).toBeVisible();
  await expect(
    page.getByRole('cell', {name: 'SRV-01', exact: true}),
  ).toBeVisible();
  await expect(page.getByText('NUEVO-9', {exact: true})).toHaveCount(0);
});

test('COD-02 · The quotation PDF is generated', async ({newCompany}) => {
  const {page, nit} = await newCompany('Códigos');
  const {quotation} = await recodedQuotation(page, nit);

  const response = await page.request.get(
    `/api/v1/quotations/${quotation.id as string}/pdf`,
  );
  expect(response.status()).toBe(200);
  expect(response.headers()['content-type']).toBe('application/pdf');
});

test("COD-03 · Converting carries the quotation's code to the draft invoice", async ({
  newCompany,
}) => {
  const {page, nit} = await newCompany('Códigos');
  const {quotation} = await recodedQuotation(page, nit);

  const converted = await api(
    page,
    'POST',
    `/quotations/${quotation.id as string}/convert`,
    {},
  );
  const invoice = (await api(
    page,
    'GET',
    `/sales-invoices/${converted.converted_invoice_id as string}`,
  )) as unknown as {lines: {product_code: string}[]};
  expect(invoice.lines.map((line) => line.product_code)).toEqual(['SRV-01']);
});
