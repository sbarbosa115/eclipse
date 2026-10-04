import {consoleErrors, expect, test} from './support/test';

// Cases LED-01 – 06 of docs/tests/ui-regression.md (item 3 "ledger"). LED-07 onwards are run by hand.

test('LED-01 · a new company has the PUC with its own auxiliares', async ({
  page,
  newCompany,
}) => {
  const errors = consoleErrors(page);
  await newCompany('Plan');
  await page.goto('/configuracion?tab=chart');

  await expect(
    page.getByRole('cell', {name: 'ACTIVO', exact: true}),
  ).toBeVisible();
  await page.getByRole('searchbox').fill('1105');
  const caja = page.getByRole('row').filter({hasText: '11050501'});
  await expect(caja).toContainText('CAJA GENERAL');
  await expect(caja).toContainText('Propia');
  await expect(caja).toContainText('Auxiliar');

  await page.getByRole('searchbox').fill('iva generado');
  await expect(page.getByRole('row').filter({hasText: '240805'})).toBeVisible();
  expect(errors).toEqual([]);
});

test('LED-02 · the owner adds an auxiliar for a bank account', async ({
  page,
  newCompany,
}) => {
  await newCompany('Banco');
  await page.goto('/configuracion?tab=chart');
  await page.getByRole('searchbox').fill('111005');

  await page
    .getByRole('button', {name: 'Agregar subcuenta bajo 111005', exact: true})
    .click();
  const dialog = page.getByRole('dialog');
  await dialog.getByLabel('Código').fill('11100502');
  await dialog.getByLabel('Nombre').fill('Bancolombia ahorros');
  await dialog.getByRole('button', {name: 'Guardar'}).click();

  await expect(page.getByText('Cuenta 11100502 creada.')).toBeVisible();
  await expect(
    page.getByRole('row').filter({hasText: 'Bancolombia ahorros'}),
  ).toBeVisible();

  await page
    .getByRole('button', {name: 'Agregar subcuenta bajo 111005', exact: true})
    .click();
  await dialog.getByLabel('Código').fill('11100502');
  await dialog.getByLabel('Nombre').fill('Repetida');
  await dialog.getByRole('button', {name: 'Guardar'}).click();
  await expect(
    dialog.getByText('Ya existe una cuenta con ese código.'),
  ).toBeVisible();
});

test('LED-03 · a PUC account keeps its name and can be deactivated', async ({
  page,
  newCompany,
}) => {
  await newCompany('Inactiva');
  await page.goto('/configuracion?tab=chart');
  await page.getByRole('searchbox').fill('110510');

  await page.getByRole('button', {name: 'Editar la cuenta 110510'}).click();
  const dialog = page.getByRole('dialog');
  await expect(dialog.getByLabel('Nombre')).toBeDisabled();
  await dialog.getByLabel('Cuenta activa').uncheck();
  await dialog.getByRole('button', {name: 'Guardar'}).click();

  await expect(page.getByText('Cuenta 110510 guardada.')).toBeVisible();
  await expect(page.getByRole('row').filter({hasText: '110510'})).toContainText(
    'Inactiva',
  );
});

test('LED-04 · the owner moves revenue to a services account', async ({
  page,
  newCompany,
}) => {
  await newCompany('Reglas');
  await page.goto('/configuracion?tab=postingRules');
  await expect(
    page.getByRole('row').filter({hasText: 'Ingresos por ventas'}),
  ).toContainText('413595 VENTA DE OTROS PRODUCTOS');

  await page
    .getByRole('button', {name: 'Cambiar la cuenta de Ingresos por ventas'})
    .click();
  const dialog = page.getByRole('dialog');
  await expect(
    dialog.getByText('Solo cuentas que empiezan por 41.'),
  ).toBeVisible();
  await dialog.getByLabel('Buscar cuenta').fill('415595');
  await expect(
    dialog.getByRole('option', {name: '415595 ACTIVIDADES CONEXAS'}),
  ).toBeAttached();
  await dialog.getByLabel('Cuenta', {exact: true}).selectOption({
    label: '415595 ACTIVIDADES CONEXAS',
  });
  await dialog.getByRole('button', {name: 'Guardar'}).click();

  await expect(
    page.getByText('Ingresos por ventas ahora va a la cuenta 415595.'),
  ).toBeVisible();
});

test('LED-05 · the owner locks the books, never beyond today', async ({
  page,
  newCompany,
}) => {
  await newCompany('Bloqueo');
  await page.goto('/configuracion?tab=postingRules');
  await expect(
    page.getByText('Los libros están abiertos: no hay fecha de bloqueo.'),
  ).toBeVisible();

  await page.getByLabel('Bloquear hasta').fill('01/01/2999');
  await page.getByRole('button', {name: 'Guardar fecha'}).click();
  await expect(
    page.getByText('La fecha de bloqueo no puede ser posterior a hoy.'),
  ).toBeVisible();

  await page.getByLabel('Bloquear hasta').fill('31/01/2026');
  await page.getByRole('button', {name: 'Guardar fecha'}).click();
  await expect(
    page.getByText('Libros bloqueados hasta el 31/01/2026.'),
  ).toBeVisible();
  await page.reload();
  await expect(page.getByText('Bloqueado hasta el 31/01/2026.')).toBeVisible();
});

test('LED-06 · a new company opens each book, empty and balanced', async ({
  page,
  newCompany,
}) => {
  const errors = consoleErrors(page);
  await newCompany('Libros');
  await page.goto('/contabilidad');

  await expect(page).toHaveURL(/\/contabilidad\/diario$/);
  await expect(
    page.getByText(
      'Aún no hay asientos. Aparecen cuando emites facturas, recibos y pagos.',
    ),
  ).toBeVisible();

  await page.getByRole('tab', {name: 'Balance de prueba'}).click();
  await expect(
    page.getByText('No hay movimientos en este periodo.'),
  ).toBeVisible();

  await page.getByRole('tab', {name: 'Estado de resultados'}).click();
  await expect(
    page.getByRole('row').filter({hasText: 'Utilidad (pérdida) del periodo'}),
  ).toContainText('$ 0,00');

  await page.getByRole('tab', {name: 'Balance general'}).click();
  await expect(page.getByText('Activo = pasivo + patrimonio.')).toBeVisible();
  expect(errors).toEqual([]);
});

test('LED-13 · each book downloads as CSV and PDF from its own page', async ({
  page,
  newCompany,
}) => {
  const errors = consoleErrors(page);
  await newCompany('Libros');

  for (const [path, name] of [
    ['diario?from=2026-01-01&to=2026-12-31', 'Libro diario'],
    ['balance-prueba?from=2026-01-01&to=2026-12-31', 'Balance de prueba'],
    ['estado-resultados?from=2026-01-01&to=2026-12-31', 'Estado de resultados'],
    ['balance-general?date=2026-12-31', 'Balance general'],
  ] as const) {
    await page.goto(`/contabilidad/${path}`);
    const csv = page.getByRole('link', {name: `Descargar ${name} en CSV`});
    await expect(csv).toBeVisible();
    await expect(
      page.getByRole('link', {name: `Descargar ${name} en PDF`}),
    ).toBeVisible();
    const download = page.waitForEvent('download');
    await csv.click();
    const file = await download;
    expect(file.suggestedFilename(), name).toMatch(/\.csv$/);
  }
  expect(errors).toEqual([]);
});
