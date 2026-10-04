import type {Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

// Cases TAX-01 – 19 of docs/tests/ui-regression.md (item 4 "taxes-payments"): Configuración › Impuestos and
// Formas de pago. The cases that need the chart of accounts (the "ledger" item) or another item's documents are run
// by hand.

const openTaxes = async (page: Page) => {
  await page.goto('/configuracion?tab=taxes');
  await expect(page.getByRole('tab', {name: 'Impuestos'})).toHaveAttribute(
    'aria-selected',
    'true',
  );
};

const openMethods = async (page: Page) => {
  await page.goto('/configuracion?tab=paymentMethods');
  await expect(page.getByRole('tab', {name: 'Formas de pago'})).toHaveAttribute(
    'aria-selected',
    'true',
  );
};

// An inactive row reads "Inactivo: <name>" to a screen reader (its colour is its status).
const rowOf = (page: Page, name: string) =>
  page.getByRole('row').filter({
    has: page.getByRole('cell', {
      name: new RegExp(
        `^(Inactiv[oa]: )?${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`,
      ),
    }),
  });

const newTax = async (
  page: Page,
  name: string,
  rate: string,
  extra?: (dialog: ReturnType<Page['getByRole']>) => Promise<void>,
) => {
  await page.getByRole('button', {name: 'Nuevo impuesto'}).click();
  const dialog = page.getByRole('dialog', {name: 'Nuevo impuesto'});
  await dialog.getByLabel('Nombre').fill(name);
  await extra?.(dialog);
  await dialog.getByLabel(/^(Tarifa|Valor por unidad)/).fill(rate);
  await dialog.getByRole('button', {name: 'Guardar'}).click();
  await expect(page.getByText('Impuesto creado.')).toBeVisible();
};

test('TAX-01 · a new company has the seeded taxes', async ({
  page,
  newCompany,
}) => {
  const errors = consoleErrors(page);
  await newCompany('Impuestos');
  await openTaxes(page);

  for (const name of [
    'IVA 19 %',
    'IVA 5 %',
    'IVA 0 %',
    'IVA por servicios 19 %',
    'Impoconsumo 8 %',
    'Impoconsumo por valor',
    'ReteFuente servicios 4 %',
    'ReteFuente compras 2,5 %',
    'ReteFuente honorarios 10 %',
    'ReteFuente honorarios 11 %',
    'ReteIVA 15 %',
  ]) {
    await expect(rowOf(page, name), name).toBeVisible();
  }
  await expect(rowOf(page, 'ReteFuente compras 2,5 %')).toContainText('2,5 %');
  await expect(
    page.getByRole('cell', {name: /^Inactiv[oa]: Impoconsumo por valor$/}),
    'seeded inactive until the company sets its value',
  ).toBeVisible();
  await expect(rowOf(page, 'IVA 19 %')).toContainText('Impuesto · IVA');
  expect(errors).toEqual([]);
});

test('TAX-02 · the class filter narrows the list', async ({
  page,
  newCompany,
}) => {
  await newCompany('Impuestos');
  await openTaxes(page);

  await page.getByLabel('Clase').selectOption('withholding');
  await expect(rowOf(page, 'ReteIVA 15 %')).toBeVisible();
  await expect(rowOf(page, 'IVA 19 %')).toHaveCount(0);
  await page.getByLabel('Clase').selectOption('charge');
  await expect(rowOf(page, 'IVA 19 %')).toBeVisible();
  await expect(rowOf(page, 'ReteIVA 15 %')).toHaveCount(0);
});

test('TAX-03 · the accountant creates a tax', async ({page, newCompany}) => {
  await newCompany('Impuestos');
  await openTaxes(page);

  await newTax(page, 'IVA 16 %', '16', async (dialog) => {
    await dialog.getByLabel(/Vigente desde/).fill('01/01/2027');
  });

  const row = rowOf(page, 'IVA 16 %');
  await expect(row).toContainText('16 %');
  await expect(row).toContainText('Desde 01/01/2027');
});

test('TAX-04 · a retención by municipality takes a decimal comma', async ({
  page,
  newCompany,
}) => {
  await newCompany('Impuestos');
  await openTaxes(page);

  await newTax(page, 'ReteICA Bogotá 0,966 %', '0,966', async (dialog) => {
    await dialog.getByLabel('Clase').selectOption('withholding');
    await dialog.getByLabel('Tipo').selectOption('reteica');
  });

  await expect(rowOf(page, 'ReteICA Bogotá 0,966 %')).toContainText(
    'Retención · ReteICA',
  );
  await expect(rowOf(page, 'ReteICA Bogotá 0,966 %')).toContainText('0,966 %');
});

test('TAX-05 · impoconsumo may be a value per unit', async ({
  page,
  newCompany,
}) => {
  await newCompany('Impuestos');
  await openTaxes(page);

  await newTax(page, 'Impoconsumo bolsa', '500', async (dialog) => {
    await dialog.getByLabel('Tipo').selectOption('impoconsumo');
    await dialog.getByLabel('Cálculo').selectOption('per_unit');
  });

  await expect(rowOf(page, 'Impoconsumo bolsa')).toContainText('$ 500,00');
});

test('TAX-06 · the form explains what is wrong', async ({page, newCompany}) => {
  await newCompany('Impuestos');
  await openTaxes(page);
  await page.getByRole('button', {name: 'Nuevo impuesto'}).click();
  const dialog = page.getByRole('dialog');

  await dialog.getByRole('button', {name: 'Guardar'}).click();
  await expect(dialog.getByText('Este campo es obligatorio.')).toBeVisible();
  await expect(
    dialog.getByText('Escribe un número, con hasta cuatro decimales.'),
  ).toBeVisible();

  await dialog.getByLabel('Nombre').fill('IVA 16 %');
  await dialog.getByLabel(/^Tarifa/).fill('16');
  await dialog.getByLabel(/Vigente desde/).fill('01/02/2027');
  await dialog.getByLabel(/Vigente hasta/).fill('01/01/2027');
  await dialog.getByRole('button', {name: 'Guardar'}).click();
  await expect(
    dialog.getByText('La fecha final no puede ser anterior a la inicial.'),
  ).toBeVisible();

  await dialog.getByLabel(/Vigente hasta/).fill('');
  await dialog.getByLabel('Nombre').fill('iva 19 %');
  await dialog.getByRole('button', {name: 'Guardar'}).click();
  await expect(
    dialog.getByText('Ya hay un impuesto con este nombre.'),
  ).toBeVisible();
  await expect(dialog, 'The form stays open with its data.').toBeVisible();
});

test('TAX-07 · editing a tax changes its rate and dates, not its kind', async ({
  page,
  newCompany,
}) => {
  await newCompany('Impuestos');
  await openTaxes(page);

  await rowOf(page, 'IVA 5 %').getByRole('button', {name: 'Editar'}).click();
  const dialog = page.getByRole('dialog', {name: 'Editar impuesto'});
  await expect(
    dialog.getByLabel('Clase'),
    'Class and kind are fixed.',
  ).toHaveCount(0);
  await expect(dialog.getByLabel(/^Tarifa/)).toHaveValue('5');
  await dialog.getByLabel(/^Tarifa/).fill('6');
  await dialog.getByLabel(/Vigente hasta/).fill('31/12/2030');
  await dialog.getByRole('button', {name: 'Guardar'}).click();

  await expect(page.getByText('Impuesto actualizado.')).toBeVisible();
  await expect(rowOf(page, 'IVA 5 %')).toContainText('6 %');
  await expect(rowOf(page, 'IVA 5 %')).toContainText('Hasta 31/12/2030');
});

test('TAX-08 · a tax is deactivated and activated again', async ({
  page,
  newCompany,
}) => {
  await newCompany('Impuestos');
  await openTaxes(page);

  await rowOf(page, 'IVA 5 %')
    .getByRole('button', {name: 'Desactivar'})
    .click();
  await expect(
    page.getByText('«IVA 5 %» ya no se ofrece en los documentos.'),
  ).toBeVisible();
  await expect(
    rowOf(page, 'IVA 5 %'),
    'It stays listed, marked inactive.',
  ).toContainText('Inactivo');

  await rowOf(page, 'IVA 5 %').getByRole('button', {name: 'Activar'}).click();
  await expect(
    page.getByText('«IVA 5 %» se ofrece de nuevo en los documentos.'),
  ).toBeVisible();
  await expect(rowOf(page, 'IVA 5 %')).not.toContainText('Inactivo');
});

test('TAX-09 · an unused tax is deleted after a confirmation', async ({
  page,
  newCompany,
}) => {
  await newCompany('Impuestos');
  await openTaxes(page);
  await newTax(page, 'IVA 16 %', '16');

  await rowOf(page, 'IVA 16 %').getByRole('button', {name: 'Eliminar'}).click();
  const dialog = page.getByRole('dialog', {name: 'Eliminar impuesto'});
  await dialog.getByRole('button', {name: 'Cancelar'}).click();
  await expect(rowOf(page, 'IVA 16 %'), 'Cancel keeps it.').toBeVisible();

  await rowOf(page, 'IVA 16 %').getByRole('button', {name: 'Eliminar'}).click();
  await page
    .getByRole('dialog')
    .getByRole('button', {name: 'Eliminar'})
    .click();
  await expect(page.getByText('«IVA 16 %» eliminado.')).toBeVisible();
  await expect(rowOf(page, 'IVA 16 %')).toHaveCount(0);
});

test('TAX-10 · "Ninguno" has no actions', async ({page, newCompany}) => {
  await newCompany('Impuestos');
  await openTaxes(page);

  await expect(rowOf(page, 'Ninguno')).toHaveCount(2);
  await expect(rowOf(page, 'Ninguno').first().getByRole('button')).toHaveCount(
    0,
  );
});

test('TAX-14 · a new company has the seeded payment methods', async ({
  page,
  newCompany,
}) => {
  const errors = consoleErrors(page);
  await newCompany('Formas');
  await openMethods(page);

  for (const name of [
    'Efectivo',
    'Tarjeta débito',
    'Tarjeta crédito',
    'Transferencia',
    'Crédito',
  ]) {
    await expect(rowOf(page, name), name).toBeVisible();
  }
  await expect(rowOf(page, 'Crédito')).toContainText('La cuenta del tercero');
  expect(errors).toEqual([]);
});

test('TAX-15 · a crédito method needs no account', async ({
  page,
  newCompany,
}) => {
  await newCompany('Formas');
  await openMethods(page);

  await page.getByRole('button', {name: 'Nueva forma de pago'}).click();
  const dialog = page.getByRole('dialog', {name: 'Nueva forma de pago'});
  await dialog.getByLabel('Nombre').fill('Crédito a 90 días');
  await dialog.getByLabel('Tipo').selectOption('credit');
  await expect(dialog.getByLabel('Cuenta')).toHaveCount(0);
  await dialog.getByRole('button', {name: 'Guardar'}).click();

  await expect(page.getByText('Forma de pago creada.')).toBeVisible();
  await expect(rowOf(page, 'Crédito a 90 días')).toContainText(
    'La cuenta del tercero',
  );
});

test('TAX-17 · a contado method asks for its account', async ({
  page,
  newCompany,
}) => {
  await newCompany('Formas');
  await openMethods(page);
  await page.getByRole('button', {name: 'Nueva forma de pago'}).click();
  const dialog = page.getByRole('dialog');

  await dialog.getByLabel('Nombre').fill('Bancolombia');
  await dialog.getByRole('button', {name: 'Guardar'}).click();
  await expect(
    dialog.getByText('Elige la cuenta donde entra el dinero.'),
  ).toBeVisible();

  await dialog.getByLabel('Cuenta').fill('texto que no es una cuenta');
  await dialog.getByRole('button', {name: 'Guardar'}).click();
  await expect(dialog.getByText('Elige una cuenta de la lista.')).toBeVisible();
});

test('TAX-18 · a method is deactivated, activated and deleted', async ({
  page,
  newCompany,
}) => {
  await newCompany('Formas');
  await openMethods(page);
  await page.getByRole('button', {name: 'Nueva forma de pago'}).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByLabel('Nombre').fill('Crédito a 90 días');
  await dialog.getByLabel('Tipo').selectOption('credit');
  await dialog.getByRole('button', {name: 'Guardar'}).click();
  await expect(page.getByText('Forma de pago creada.')).toBeVisible();

  await rowOf(page, 'Crédito a 90 días')
    .getByRole('button', {name: 'Desactivar'})
    .click();
  await expect(rowOf(page, 'Crédito a 90 días')).toContainText('Inactiva');
  await rowOf(page, 'Crédito a 90 días')
    .getByRole('button', {name: 'Activar'})
    .click();
  await expect(rowOf(page, 'Crédito a 90 días')).not.toContainText('Inactiva');

  await rowOf(page, 'Crédito a 90 días')
    .getByRole('button', {name: 'Eliminar'})
    .click();
  await page
    .getByRole('dialog')
    .getByRole('button', {name: 'Eliminar'})
    .click();
  await expect(page.getByText('«Crédito a 90 días» eliminada.')).toBeVisible();
  await expect(rowOf(page, 'Crédito a 90 días')).toHaveCount(0);
});
