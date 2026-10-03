import {consoleErrors, DEMO, expect, test} from './support/test';

// Cases ACC-01 – 05 of docs/tests/ui-regression.md (item 0). The "access" item adds ACC-06 – 19 in a spec of its own.

test('ACC-01 · a company signs up and lands on its dashboard', async ({
  page,
}) => {
  const errors = consoleErrors(page);
  const nit = String(700_000_000 + (Date.now() % 100_000_000));
  await page.goto('/registro');

  await page.getByLabel('Razón social').fill('Prueba Registro S.A.S.');
  await page.getByLabel('NIT').fill(nit);
  await page.getByLabel('Tu nombre').fill('Ana Pérez');
  await page
    .getByLabel('Tu correo electrónico')
    .fill(`ana-${nit}@mustang.test`);
  await page.getByLabel('Contraseña').fill('una clave bien larga');
  await page.getByRole('button', {name: 'Crear cuenta'}).click();

  await expect(page.getByRole('heading', {name: 'Tablero'})).toBeVisible();
  await expect(page.getByText('Prueba Registro S.A.S.')).toBeVisible();
  await expect(page.getByText(/^NIT \d{9}-\d$/)).toBeVisible();
  expect(errors).toEqual([]);
});

test('ACC-02 · the owner signs in and out', async ({page}) => {
  await page.goto('/ingresar');

  await page.getByLabel('Correo electrónico').fill(DEMO.email);
  await page.getByLabel('Contraseña').fill(DEMO.password);
  await page.getByRole('button', {name: 'Ingresar'}).click();
  await expect(page.getByText(DEMO.company)).toBeVisible();

  await page.getByRole('button', {name: 'Cerrar sesión'}).click();
  await expect(
    page.getByRole('heading', {name: 'Ingresa a tu empresa'}),
  ).toBeVisible();
  await page.goto('/');
  await expect(
    page.getByRole('heading', {name: 'Ingresa a tu empresa'}),
    'Signed out stays signed out after a reload.',
  ).toBeVisible();
});

test('ACC-03 · a wrong password is refused without saying which part', async ({
  page,
}) => {
  await page.goto('/ingresar');

  await page.getByLabel('Correo electrónico').fill(DEMO.email);
  await page.getByLabel('Contraseña').fill('no-es-la-clave');
  await page.getByRole('button', {name: 'Ingresar'}).click();

  await expect(page.getByRole('alert')).toHaveText(
    'Correo o contraseña incorrectos.',
  );
});

test('ACC-04 · a signed-out link to a section signs in first, then opens it', async ({
  page,
}) => {
  await page.goto('/facturas-venta');
  await expect(
    page.getByRole('heading', {name: 'Ingresa a tu empresa'}),
  ).toBeVisible();

  await page.getByLabel('Correo electrónico').fill(DEMO.email);
  await page.getByLabel('Contraseña').fill(DEMO.password);
  await page.getByRole('button', {name: 'Ingresar'}).click();

  await expect(
    page.getByRole('heading', {name: 'Facturas de venta'}),
  ).toBeVisible();
  await expect(page).toHaveURL(/\/facturas-venta$/);
});

test('ACC-05 · the sign-up form explains what is wrong', async ({
  page,
  newCompany,
}) => {
  const taken = await newCompany('Tomada');
  await page.context().clearCookies();
  await page.goto('/registro');

  await page.getByLabel('NIT').fill('abc');
  await page.getByLabel('Contraseña').fill('corta');
  await page.getByRole('button', {name: 'Crear cuenta'}).click();
  await expect(
    page.getByText(
      'Escribe el NIT solo con números, sin el dígito de verificación.',
    ),
  ).toBeVisible();
  await expect(
    page.getByText('La contraseña debe tener al menos 10 caracteres.'),
  ).toBeVisible();

  await page.getByLabel('Razón social').fill('Otra S.A.S.');
  await page.getByLabel('NIT').fill(taken.nit);
  await page.getByLabel('Tu nombre').fill('Luis');
  await page.getByLabel('Tu correo electrónico').fill(taken.email);
  await page.getByLabel('Contraseña').fill('una clave bien larga');
  await page.getByRole('button', {name: 'Crear cuenta'}).click();

  await expect(page.getByText('Este correo ya está registrado.')).toBeVisible();
});
