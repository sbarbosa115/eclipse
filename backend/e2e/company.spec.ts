import type {Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

// Cases CO-01 – 19 of docs/tests/ui-regression.md (item 2 "company"): Configuración › Empresa and Resolución. CO-10
// (other roles read only, needs the Usuarios tab), CO-18 and CO-19 (need emitted sales invoices) are run by hand.

// A 1×1 PNG and JPEG: real images, judged by their bytes.
const PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
  'base64',
);

const openCompany = async (page: Page) => {
  await page.goto('/configuracion?tab=company');
  await expect(page.getByRole('tab', {name: 'Empresa'})).toHaveAttribute(
    'aria-selected',
    'true',
  );
  await expect(page.getByLabel('Razón social')).toBeVisible();
};

const openResolution = async (page: Page) => {
  await page.goto('/configuracion?tab=resolution');
  await expect(page.getByRole('tab', {name: 'Resolución'})).toHaveAttribute(
    'aria-selected',
    'true',
  );
  await expect(page.getByLabel('Número de resolución')).toBeVisible();
};

/** A day in Colombia's calendar (the server's), `offsetDays` from today. */
const isoDay = (offsetDays: number): string => {
  const today = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'America/Bogota',
  }).format(new Date());
  const d = new Date(`${today}T12:00:00Z`);
  d.setUTCDate(d.getUTCDate() + offsetDays);
  return d.toISOString().slice(0, 10);
};

const fillResolution = async (
  page: Page,
  values: {
    number?: string;
    prefix?: string;
    from?: string;
    to?: string;
    start?: string;
    end?: string;
  } = {},
) => {
  await page
    .getByLabel('Número de resolución')
    .fill(values.number ?? '18760000001');
  await page.getByLabel(/^Prefijo/).fill(values.prefix ?? 'SETP');
  await page.getByLabel('Desde', {exact: true}).fill(values.from ?? '1');
  await page.getByLabel('Hasta', {exact: true}).fill(values.to ?? '1000');
  await page.getByLabel('Fecha de inicio').fill(values.start ?? isoDay(-10));
  await page.getByLabel('Fecha de fin').fill(values.end ?? isoDay(300));
};

test('CO-01 · the profile shows what the sign-up gave, with its DV', async ({
  page,
  newCompany,
}) => {
  const errors = consoleErrors(page);
  const company = await newCompany('Perfil');
  await openCompany(page);

  await expect(page.getByLabel('Razón social')).toHaveValue(company.name);
  await expect(page.getByLabel('Tipo de documento')).toHaveValue('nit');
  await expect(page.getByLabel('Número de identificación')).toHaveValue(
    company.nit,
  );
  await expect(page.getByLabel(/^DV/)).toHaveValue(/^\d$/);
  expect(errors).toEqual([]);
});

test('CO-02 · the owner edits the profile and it is kept', async ({
  page,
  newCompany,
}) => {
  await newCompany('Perfil');
  await openCompany(page);

  await page.getByLabel('Razón social').fill('Mi Empresa Ltda.');
  await page.getByLabel(/^Nombre comercial/).fill('Mi Empresa');
  await page.getByLabel(/^Dirección/).fill('Calle 1 # 2-3');
  await page.getByLabel(/^Ciudad/).fill('Bogotá');
  await page.getByLabel(/^Teléfono/).fill('6011234567');
  await page.getByLabel(/^Correo electrónico/).fill('contacto@miempresa.co');
  await page.getByRole('button', {name: 'Guardar cambios'}).click();
  await expect(
    page.getByText('Guardamos los datos de la empresa.'),
  ).toBeVisible();

  await page.reload();
  await expect(page.getByLabel('Razón social')).toHaveValue('Mi Empresa Ltda.');
  await expect(page.getByLabel(/^Ciudad/)).toHaveValue('Bogotá');
  await expect(page.getByLabel(/^Correo electrónico/)).toHaveValue(
    'contacto@miempresa.co',
  );
});

test('CO-03 · the DV is computed when empty and may be typed', async ({
  page,
  newCompany,
}) => {
  await newCompany('Perfil');
  await openCompany(page);

  await page.getByLabel(/^DV/).fill('');
  await page.getByRole('button', {name: 'Guardar cambios'}).click();
  await expect(
    page.getByText('Guardamos los datos de la empresa.'),
  ).toBeVisible();
  await expect(page.getByLabel(/^DV/)).toHaveValue(/^\d$/);

  await page.getByLabel(/^DV/).fill('5');
  await page.getByRole('button', {name: 'Guardar cambios'}).click();
  await expect(page.getByLabel(/^DV/)).toHaveValue('5');
});

test('CO-04 · a NIT another company has is refused', async ({
  page,
  newCompany,
  baseURL,
}) => {
  const first = await newCompany('Primera');
  await page.request.post('/api/v1/auth/sign-out', {
    headers: {Origin: new URL(baseURL as string).origin},
  });
  await newCompany('Segunda');
  await openCompany(page);

  await page.getByLabel('Número de identificación').fill(first.nit);
  await page.getByRole('button', {name: 'Guardar cambios'}).click();

  await expect(
    page.getByText('Ya hay una empresa registrada con este NIT.'),
  ).toBeVisible();
});

test('CO-05 · a document type without a DV hides it', async ({
  page,
  newCompany,
}) => {
  await newCompany('Perfil');
  await openCompany(page);

  await page.getByLabel('Tipo de documento').selectOption('cc');
  await expect(page.getByLabel(/^DV/)).toHaveCount(0);
  await page.getByLabel('Tipo de documento').selectOption('nit');
  await expect(page.getByLabel(/^DV/)).toBeVisible();
});

test('CO-06 · régimen and responsabilidades fiscales are kept', async ({
  page,
  newCompany,
}) => {
  await newCompany('Perfil');
  await openCompany(page);

  await page.getByLabel('Régimen de IVA').selectOption('simple');
  await page.getByLabel(/O-13 Gran contribuyente/).check();
  await page.getByLabel(/O-15 Autorretenedor/).check();
  await page.getByRole('button', {name: 'Guardar cambios'}).click();
  await expect(
    page.getByText('Guardamos los datos de la empresa.'),
  ).toBeVisible();

  await page.reload();
  await expect(page.getByLabel('Régimen de IVA')).toHaveValue('simple');
  await expect(page.getByLabel(/O-13 Gran contribuyente/)).toBeChecked();
  await expect(page.getByLabel(/O-15 Autorretenedor/)).toBeChecked();
  await expect(page.getByLabel(/O-23/)).not.toBeChecked();
});

test('CO-07 · default taxes come from the active taxes of their class', async ({
  page,
  newCompany,
}) => {
  await newCompany('Perfil');
  await openCompany(page);

  const charge = page.getByLabel('Impuesto cargo');
  const withholding = page.getByLabel('Impuesto de retención');
  await expect(
    charge.locator('option', {hasText: 'IVA 19 %'}).first(),
  ).toBeAttached();
  await expect(charge.locator('option', {hasText: 'ReteIVA 15 %'})).toHaveCount(
    0,
  );
  await expect(
    withholding.locator('option', {hasText: 'ReteIVA 15 %'}),
  ).toBeAttached();
  await expect(
    withholding.locator('option', {hasText: 'IVA 19 %'}),
  ).toHaveCount(0);

  await charge.selectOption({label: 'IVA 5 %'});
  await withholding.selectOption({label: 'ReteFuente servicios 4 %'});
  await page.getByRole('button', {name: 'Guardar cambios'}).click();
  await expect(
    page.getByText('Guardamos los datos de la empresa.'),
  ).toBeVisible();
  await page.reload();
  await expect(page.getByLabel('Impuesto cargo')).toHaveValue(/.+/);
  await expect(
    page.getByLabel('Impuesto cargo').locator('option:checked'),
  ).toHaveText('IVA 5 %');
});

test('CO-08 · the logo is uploaded, replaced and removed', async ({
  page,
  newCompany,
}) => {
  const errors = consoleErrors(page);
  await newCompany('Perfil');
  await openCompany(page);
  await expect(page.getByText('Todavía no has subido un logo.')).toBeVisible();

  await page.getByRole('button', {name: 'Subir logo'}).click();
  await page.getByLabel('Elegir archivo').setInputFiles({
    name: 'logo.png',
    mimeType: 'image/png',
    buffer: PNG,
  });
  await expect(page.getByText('Guardamos el logo.')).toBeVisible();
  await expect(page.getByAltText('Logo de la empresa')).toBeVisible();
  const src = await page.getByAltText('Logo de la empresa').getAttribute('src');
  const served = await page.request.get(src as string);
  expect(served.status()).toBe(200);
  expect(served.headers()['content-type']).toBe('image/png');

  await page.getByRole('button', {name: 'Cambiar logo'}).click();
  await page.getByLabel('Elegir archivo').setInputFiles({
    name: 'otro.png',
    mimeType: 'image/png',
    buffer: PNG,
  });
  await expect(page.getByAltText('Logo de la empresa')).not.toHaveAttribute(
    'src',
    src as string,
  );

  await page.getByRole('button', {name: 'Quitar logo'}).click();
  await expect(page.getByText('Quitamos el logo.')).toBeVisible();
  await expect(page.getByText('Todavía no has subido un logo.')).toBeVisible();
  expect(errors).toEqual([]);
});

test('CO-09 · the logo must be a PNG or JPEG of 2 MB at most, judged by its content', async ({
  page,
  newCompany,
}) => {
  await newCompany('Perfil');
  await openCompany(page);
  await page.getByRole('button', {name: 'Subir logo'}).click();
  const picker = page.getByLabel('Elegir archivo');

  await picker.setInputFiles({
    name: 'logo.svg',
    mimeType: 'image/svg+xml',
    buffer: Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"/>'),
  });
  await expect(
    page.getByText('El logo debe ser una imagen PNG o JPG.'),
  ).toBeVisible();

  await picker.setInputFiles({
    name: 'grande.png',
    mimeType: 'image/png',
    buffer: Buffer.concat([PNG, Buffer.alloc(2 * 1024 * 1024)]),
  });
  await expect(page.getByText('El logo pesa más de 2 MB.')).toBeVisible();

  // A script dressed as a PNG passes the browser's check and is refused by the server, which reads the bytes.
  await picker.setInputFiles({
    name: 'logo.png',
    mimeType: 'image/png',
    buffer: Buffer.from('<?php echo 1;'),
  });
  await expect(
    page.getByText('El logo debe ser una imagen PNG o JPG.'),
  ).toBeVisible();
  await expect(page.getByText('Todavía no has subido un logo.')).toBeVisible();
});

test('CO-11 · the owner sets the resolution up and sees it in force', async ({
  page,
  newCompany,
}) => {
  const errors = consoleErrors(page);
  await newCompany('Resolución');
  await openResolution(page);
  await expect(
    page.getByText(/Todavía no has configurado tu resolución/),
  ).toBeVisible();

  await fillResolution(page);
  await page.getByRole('button', {name: 'Crear resolución'}).click();

  await expect(page.getByText('Creamos la resolución.')).toBeVisible();
  await expect(page.getByText(/Tu resolución está vigente/)).toBeVisible();
  await expect(page.getByLabel('Consecutivo actual')).toHaveValue('1');
  await page.reload();
  await expect(page.getByLabel(/^Prefijo/)).toHaveValue('SETP');
  expect(errors).toEqual([]);
});

test('CO-12 · the resolution form explains what is wrong', async ({
  page,
  newCompany,
}) => {
  await newCompany('Resolución');
  await openResolution(page);

  await page.getByRole('button', {name: 'Crear resolución'}).click();
  await expect(
    page.getByText('Este dato es obligatorio.').first(),
  ).toBeVisible();

  await fillResolution(page, {from: '500', to: '10'});
  await page.getByRole('button', {name: 'Crear resolución'}).click();
  await expect(page.getByLabel('Hasta', {exact: true})).toHaveAttribute(
    'aria-invalid',
    'true',
  );

  await fillResolution(page, {start: isoDay(10), end: isoDay(5)});
  await page.getByRole('button', {name: 'Crear resolución'}).click();
  await expect(page.getByLabel('Fecha de fin')).toHaveAttribute(
    'aria-invalid',
    'true',
  );
});

test('CO-13 · manual mode waits for the owner to confirm the DIAN permission', async ({
  page,
  newCompany,
}) => {
  await newCompany('Resolución');
  await openResolution(page);

  await expect(
    page.getByLabel('Modalidad').locator('option', {hasText: 'Manual'}),
  ).toBeDisabled();
  await page
    .getByRole('button', {name: 'Confirmar permiso de la DIAN'})
    .click();
  const dialog = page.getByRole('dialog', {name: 'Facturación manual'});
  await expect(dialog).toContainText(
    'Por ley las facturas de venta son electrónicas',
  );
  await dialog
    .getByRole('button', {name: 'Confirmo que la empresa tiene el permiso'})
    .click();

  await expect(
    page.getByText('Registramos la confirmación del permiso de la DIAN.'),
  ).toBeVisible();
  await expect(
    page.getByLabel('Modalidad').locator('option', {hasText: 'Manual'}),
  ).toBeEnabled();
  await fillResolution(page);
  await page.getByLabel('Modalidad').selectOption('manual');
  await page.getByRole('button', {name: 'Crear resolución'}).click();
  await expect(page.getByText('Creamos la resolución.')).toBeVisible();
  await page.reload();
  await expect(page.getByLabel('Modalidad')).toHaveValue('manual');
});

test('CO-14 · the owner is warned when few numbers are left', async ({
  page,
  newCompany,
}) => {
  await newCompany('Resolución');
  await openResolution(page);

  await fillResolution(page, {from: '1', to: '50'});
  await page.getByRole('button', {name: 'Crear resolución'}).click();

  const warning = page.getByText(/Tu resolución se está agotando/);
  await expect(warning).toBeVisible();
  await expect(warning).toContainText('te quedan 50 números');
  await expect(page.locator('.alert-warning')).toBeVisible();
});

test('CO-15 · the warning thresholds are editable', async ({
  page,
  newCompany,
}) => {
  await newCompany('Resolución');
  await openResolution(page);
  await fillResolution(page, {from: '1', to: '50'});
  await page.getByRole('button', {name: 'Crear resolución'}).click();
  await expect(page.getByText(/se está agotando/)).toBeVisible();

  await page.getByLabel('Avisar con menos de (números)').fill('10');
  await page.getByLabel('Avisar con menos de (días)').fill('5');
  await page.getByRole('button', {name: 'Guardar avisos'}).click();

  await expect(page.getByText('Guardamos los avisos.')).toBeVisible();
  await expect(page.getByText(/se está agotando/)).toHaveCount(0);
  await expect(page.getByText(/Tu resolución está vigente/)).toBeVisible();
});

test('CO-16 · an expired resolution is shown as blocking', async ({
  page,
  newCompany,
}) => {
  await newCompany('Resolución');
  await openResolution(page);

  await fillResolution(page, {start: isoDay(-60), end: isoDay(-1)});
  await page.getByRole('button', {name: 'Crear resolución'}).click();

  const banner = page.getByText(/Tu resolución venció/);
  await expect(banner).toBeVisible();
  await expect(page.locator('.alert-error')).toContainText(
    'no puedes emitir facturas',
  );
});

test('CO-17 · the internal numbering is edited and never goes back', async ({
  page,
  newCompany,
}) => {
  await newCompany('Numeración');
  await openResolution(page);

  const row = page.getByRole('row').filter({hasText: 'Recibo de caja'});
  await expect(row).toContainText('RC');
  await row.getByRole('button', {name: 'Editar'}).click();
  const dialog = page.getByRole('dialog', {name: 'Numeración: Recibo de caja'});
  await dialog.getByLabel(/^Prefijo/).fill('rcb');
  await dialog.getByLabel('Próximo número').fill('250');
  await dialog.getByRole('button', {name: 'Guardar'}).click();

  await expect(page.getByText('Guardamos la numeración.')).toBeVisible();
  await expect(row).toContainText('RCB');
  await expect(row).toContainText('250');

  await row.getByRole('button', {name: 'Editar'}).click();
  await dialog.getByLabel('Próximo número').fill('100');
  await dialog.getByRole('button', {name: 'Guardar'}).click();
  await expect(
    dialog.getByText(
      'El próximo número no puede ser menor que 250, el actual.',
    ),
  ).toBeVisible();
  await expect(
    page.getByRole('row').filter({hasText: 'Cotización'}),
  ).toContainText('C');
  await expect(page.getByRole('row').filter({hasText: 'Registro'})).toHaveCount(
    0,
  );
});
