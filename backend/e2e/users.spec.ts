import type {Browser, Page} from '@playwright/test';
import {consoleErrors, expect, PASSWORD, test} from './support/test';

// Cases ACC-06 – 19 of docs/tests/ui-regression.md (item 1 "access"): Configuración › Usuarios, invitations, roles,
// deactivation and the password reset. E-mails are read from Mailpit (the worker sends them from the queue).

const MAILPIT = process.env.MAILPIT_URL ?? 'http://localhost:8035';
let people = 0;

// Every case waits for the worker to send at least one e-mail.
test.describe.configure({timeout: 60_000});

/** An e-mail address nobody used in this run. */
function nextEmail(label: string): string {
  people += 1;
  return `${label}-${Date.now()}-${people}@mustang.test`;
}

interface MailpitMessage {
  ID: string;
  Subject: string;
}

/**
 * The path of the last link e-mailed to this address for this page (`/invitacion#…`), once the worker has sent it.
 * `after` skips the e-mails already seen (a resent invitation).
 */
async function linkFor(to: string, page: string, after = 0): Promise<string> {
  let messages: MailpitMessage[] = [];
  await expect
    .poll(
      async () => {
        const response = await fetch(
          `${MAILPIT}/api/v1/search?query=${encodeURIComponent(`to:"${to}"`)}`,
        );
        messages = ((await response.json()) as {messages: MailpitMessage[]})
          .messages;
        return messages.length;
      },
      {message: `an e-mail to ${to} arrives`, timeout: 20_000},
    )
    .toBeGreaterThan(after);
  // Mailpit lists the newest first.
  const message = (await (
    await fetch(`${MAILPIT}/api/v1/message/${messages[0]?.ID ?? ''}`)
  ).json()) as {Text: string};
  const match = message.Text.match(new RegExp(`/${page}#[A-Za-z0-9_-]+`));
  expect(match, `the e-mail links to /${page}`).not.toBeNull();
  return (match as RegExpMatchArray)[0];
}

async function subjectOfLast(to: string): Promise<string> {
  const response = await fetch(
    `${MAILPIT}/api/v1/search?query=${encodeURIComponent(`to:"${to}"`)}`,
  );
  const {messages} = (await response.json()) as {messages: MailpitMessage[]};
  return messages[0]?.Subject ?? '';
}

const openUsers = async (page: Page) => {
  await page.goto('/configuracion?tab=users');
  await expect(page.getByRole('tab', {name: 'Usuarios'})).toHaveAttribute(
    'aria-selected',
    'true',
  );
};

const rowOf = (page: Page, text: string) =>
  page.getByRole('row').filter({has: page.getByRole('cell', {name: text})});

async function invite(page: Page, email: string, accountant = false) {
  await page
    .getByRole('button', {
      name: accountant ? 'Invitar a tu contador' : 'Invitar usuario',
    })
    .click();
  const dialog = page.getByRole('dialog');
  await dialog.getByLabel('Correo electrónico').fill(email);
  await dialog.getByRole('button', {name: 'Enviar invitación'}).click();
  await expect(
    page.getByText(`Enviamos la invitación a ${email}.`),
  ).toBeVisible();
}

/** A fresh browser of its own (another person, or the same one on another device). */
async function anotherBrowser(browser: Browser, baseURL: string) {
  const context = await browser.newContext({
    baseURL,
    locale: 'es-CO',
    extraHTTPHeaders: {
      'X-Forwarded-For': `10.88.${people % 250}.${(Date.now() % 250) + 1}`,
    },
  });
  return {context, page: await context.newPage()};
}

async function accept(page: Page, link: string, name: string) {
  await page.goto(link);
  await page.getByLabel('Tu nombre').fill(name);
  await page.getByLabel('Contraseña').fill(PASSWORD);
  await page.getByRole('button', {name: 'Entrar a la empresa'}).click();
}

test('ACC-06 · the owner sees the Usuarios tab with themself in it', async ({
  newCompany,
}) => {
  const {page, email} = await newCompany('Usuarios');
  const errors = consoleErrors(page);
  await openUsers(page);

  const me = rowOf(page, email);
  await expect(me).toContainText('Tú');
  await expect(me).toContainText('Administrador');
  await expect(
    me.getByRole('button', {name: 'Desactivar'}),
    'Nobody deactivates themselves.',
  ).toHaveCount(0);
  expect(errors).toEqual([]);
});

test('ACC-07 · an invited billing user accepts by e-mail and lands signed in', async ({
  newCompany,
  browser,
  baseURL,
}) => {
  const {page, name} = await newCompany('Invita');
  await openUsers(page);
  const luis = nextEmail('luis');
  await invite(page, luis);
  await expect(rowOf(page, luis)).toContainText('Invitación vigente hasta el');
  const link = await linkFor(luis, 'invitacion');
  expect(await subjectOfLast(luis)).toBe(`${name} te invita a Mustang`);

  const other = await anotherBrowser(browser, baseURL as string);
  const errors = consoleErrors(other.page);
  await other.page.goto(link);
  await expect(
    other.page.getByRole('heading', {name: `Únete a ${name}`}),
  ).toBeVisible();
  await expect(other.page.getByText(/como Facturación/)).toBeVisible();
  await other.page.getByLabel('Tu nombre').fill('Luis Gómez');
  await other.page.getByLabel('Contraseña').fill(PASSWORD);
  await other.page.getByRole('button', {name: 'Entrar a la empresa'}).click();

  await expect(
    other.page.getByRole('heading', {name: 'Tablero'}),
  ).toBeVisible();
  await expect(other.page.getByText('Luis Gómez')).toBeVisible();
  await expect(
    other.page.getByRole('link', {name: 'Configuración'}),
    'A billing user does not configure the company.',
  ).toHaveCount(0);
  expect(errors).toEqual([]);
  await other.context.close();

  await page.reload();
  await expect(rowOf(page, luis)).toContainText('Luis Gómez');
  await expect(rowOf(page, luis)).toContainText('Facturación');
});

test('ACC-08 · "Invitar a tu contador" invites an accountant', async ({
  newCompany,
  browser,
  baseURL,
}) => {
  const {page} = await newCompany('Contador');
  await openUsers(page);
  const eva = nextEmail('contadora');
  await invite(page, eva, true);
  await expect(rowOf(page, eva)).toContainText('Contador');

  const other = await anotherBrowser(browser, baseURL as string);
  await accept(other.page, await linkFor(eva, 'invitacion'), 'Eva Ruiz');
  await expect(other.page.getByText('Contador', {exact: true})).toBeVisible();
  await other.page.goto('/configuracion?tab=users');
  await expect(
    other.page.getByText(
      'Solo el administrador de la empresa gestiona los usuarios.',
    ),
  ).toBeVisible();
  await other.context.close();
});

test('ACC-09 · an e-mail already registered is refused under the field', async ({
  newCompany,
}) => {
  const {page, email} = await newCompany('Repetido');
  await openUsers(page);

  await page.getByRole('button', {name: 'Invitar usuario'}).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByLabel('Correo electrónico').fill(email);
  await dialog.getByRole('button', {name: 'Enviar invitación'}).click();

  await expect(
    dialog.getByText('Este correo ya está registrado.'),
  ).toBeVisible();
  await dialog.getByLabel('Correo electrónico').fill('no-es-correo');
  await dialog.getByRole('button', {name: 'Enviar invitación'}).click();
  await expect(dialog.getByText('Escribe un correo válido.')).toBeVisible();
});

test('ACC-10 · resending an invitation replaces the earlier link', async ({
  newCompany,
  browser,
  baseURL,
}) => {
  const {page} = await newCompany('Reenvio');
  await openUsers(page);
  const luis = nextEmail('reenvio');
  await invite(page, luis);
  const first = await linkFor(luis, 'invitacion');

  await rowOf(page, luis)
    .getByRole('button', {name: 'Reenviar invitación'})
    .click();
  await expect(
    page.getByText(`Enviamos de nuevo la invitación a ${luis}.`, {
      exact: false,
    }),
  ).toBeVisible();
  const second = await linkFor(luis, 'invitacion', 1);
  expect(second).not.toBe(first);

  const other = await anotherBrowser(browser, baseURL as string);
  await other.page.goto(first);
  await expect(
    other.page.getByRole('heading', {name: 'Este enlace ya no sirve'}),
  ).toBeVisible();
  // The new e-mail's link opens in a new tab, as a mail client does.
  const tab = await other.context.newPage();
  await accept(tab, second, 'Luis');
  await expect(tab.getByRole('heading', {name: 'Tablero'})).toBeVisible();
  await other.context.close();
});

test('ACC-11 · the owner changes a role; the last owner keeps theirs', async ({
  newCompany,
  browser,
  baseURL,
}) => {
  const {page, email} = await newCompany('Roles');
  await openUsers(page);
  const luis = nextEmail('roles');
  await invite(page, luis);
  const other = await anotherBrowser(browser, baseURL as string);
  await accept(other.page, await linkFor(luis, 'invitacion'), 'Luis Gómez');
  await expect(
    other.page.getByRole('heading', {name: 'Tablero'}),
  ).toBeVisible();
  await other.context.close();

  await page.reload();
  await rowOf(page, luis).getByRole('button', {name: 'Cambiar rol'}).click();
  let dialog = page.getByRole('dialog', {name: 'Cambiar el rol de Luis Gómez'});
  await dialog.getByLabel('Rol').selectOption('accountant');
  await dialog.getByRole('button', {name: 'Cambiar rol'}).click();
  await expect(
    page.getByText('Luis Gómez ahora tiene el rol Contador.'),
  ).toBeVisible();
  await expect(rowOf(page, luis)).toContainText('Contador');

  await rowOf(page, email).getByRole('button', {name: 'Cambiar rol'}).click();
  dialog = page.getByRole('dialog');
  await dialog.getByLabel('Rol').selectOption('billing');
  await dialog.getByRole('button', {name: 'Cambiar rol'}).click();
  await expect(dialog.getByRole('alert')).toHaveText(
    'La empresa debe tener al menos un administrador activo.',
  );
});

test('ACC-13 · a deactivated user is signed out and cannot sign in; reactivated, they can', async ({
  newCompany,
  browser,
  baseURL,
}) => {
  const {page} = await newCompany('Desactiva');
  await openUsers(page);
  const luis = nextEmail('desactiva');
  await invite(page, luis);
  const other = await anotherBrowser(browser, baseURL as string);
  await accept(other.page, await linkFor(luis, 'invitacion'), 'Luis Gómez');
  await expect(
    other.page.getByRole('heading', {name: 'Tablero'}),
  ).toBeVisible();

  await page.reload();
  await rowOf(page, luis).getByRole('button', {name: 'Desactivar'}).click();
  await page
    .getByRole('dialog', {name: 'Desactivar a Luis Gómez'})
    .getByRole('button', {name: 'Desactivar'})
    .click();
  await expect(
    page.getByText('Luis Gómez ya no puede ingresar. Su sesión se cerró.'),
  ).toBeVisible();

  await other.page.reload();
  await expect(
    other.page.getByRole('heading', {name: 'Ingresa a tu empresa'}),
    'His open session ended.',
  ).toBeVisible();
  await other.page.getByLabel('Correo electrónico').fill(luis);
  await other.page.getByLabel('Contraseña').fill(PASSWORD);
  await other.page.getByRole('button', {name: 'Ingresar'}).click();
  await expect(
    other.page.getByText('Correo o contraseña incorrectos.'),
  ).toBeVisible();

  await rowOf(page, luis).getByRole('button', {name: 'Reactivar'}).click();
  await expect(
    page.getByText('Luis Gómez puede ingresar de nuevo.'),
  ).toBeVisible();
  await other.page.getByRole('button', {name: 'Ingresar'}).click();
  await expect(
    other.page.getByRole('heading', {name: 'Tablero'}),
  ).toBeVisible();
  await other.context.close();
});

test('ACC-15 · a forgotten password is reset by e-mail', async ({
  newCompany,
  browser,
  baseURL,
}) => {
  const {email, page: owner} = await newCompany('Olvido');
  const other = await anotherBrowser(browser, baseURL as string);
  const page = other.page;
  const errors = consoleErrors(page);

  await page.goto('/ingresar');
  await page.getByRole('link', {name: '¿Olvidaste tu contraseña?'}).click();
  await page.getByLabel('Correo electrónico').fill(email);
  await page.getByRole('button', {name: 'Enviar enlace'}).click();
  await expect(
    page.getByText(`Si ${email} tiene una cuenta en Mustang`),
  ).toBeVisible();

  await page.goto(await linkFor(email, 'restablecer-contrasena'));
  await page.getByLabel('Contraseña nueva').fill('una clave nueva y larga');
  await page.getByRole('button', {name: 'Guardar y entrar'}).click();
  await expect(page.getByRole('heading', {name: 'Tablero'})).toBeVisible();
  expect(errors).toEqual([]);

  // The owner's other session, open since signing up, has ended.
  await owner.goto('/configuracion?tab=users');
  await expect(
    owner.getByRole('heading', {name: 'Ingresa a tu empresa'}),
  ).toBeVisible();
  await owner.getByLabel('Correo electrónico').fill(email);
  await owner.getByLabel('Contraseña').fill(PASSWORD);
  await owner.getByRole('button', {name: 'Ingresar'}).click();
  await expect(
    owner.getByText('Correo o contraseña incorrectos.'),
    'The old password no longer works.',
  ).toBeVisible();
  await other.context.close();
});

test('ACC-16 · a reset link works once; an unknown e-mail gets the same answer', async ({
  newCompany,
  browser,
  baseURL,
}) => {
  const {email} = await newCompany('UnaVez');
  const {context, page} = await anotherBrowser(browser, baseURL as string);

  await page.goto('/recuperar-contrasena');
  const nobody = nextEmail('nadie');
  await page.getByLabel('Correo electrónico').fill(nobody);
  await page.getByRole('button', {name: 'Enviar enlace'}).click();
  await expect(
    page.getByText(`Si ${nobody} tiene una cuenta en Mustang`),
  ).toBeVisible();

  await page.goto('/recuperar-contrasena');
  await page.getByLabel('Correo electrónico').fill(email);
  await page.getByRole('button', {name: 'Enviar enlace'}).click();
  const link = await linkFor(email, 'restablecer-contrasena');
  await page.goto(link);
  await page.getByLabel('Contraseña nueva').fill('una clave nueva y larga');
  await page.getByRole('button', {name: 'Guardar y entrar'}).click();
  await expect(page.getByRole('heading', {name: 'Tablero'})).toBeVisible();

  await page.goto(link);
  await expect(
    page.getByRole('heading', {name: 'Este enlace ya no sirve'}),
  ).toBeVisible();
  await page.getByRole('link', {name: 'Pedir otro enlace'}).click();
  await expect(
    page.getByRole('heading', {name: '¿Olvidaste tu contraseña?'}),
  ).toBeVisible();
  await context.close();
});

test('ACC-19 · only the owner manages users', async ({
  newCompany,
  browser,
  baseURL,
}) => {
  const {page} = await newCompany('SoloDueño');
  await openUsers(page);
  const luis = nextEmail('facturador');
  await invite(page, luis);
  const other = await anotherBrowser(browser, baseURL as string);
  await accept(other.page, await linkFor(luis, 'invitacion'), 'Luis');
  await expect(
    other.page.getByRole('heading', {name: 'Tablero'}),
  ).toBeVisible();

  const response = await other.page.request.get('/api/v1/users');
  expect(response.status(), 'The API refuses a billing user too.').toBe(403);
  await other.context.close();
});
