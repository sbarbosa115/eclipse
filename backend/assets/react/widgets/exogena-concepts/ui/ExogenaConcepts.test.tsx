import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {permissionsOf} from '@/shared/test/permissions';
import {ExogenaConcepts} from './ExogenaConcepts';

const sessionOf = (role: string) => ({
  user_id: 'u1',
  email: 'ana@acme.co',
  name: 'Ana',
  role,
  permissions: permissionsOf(role),
  company_id: 'c1',
  company_name: 'Acme',
  company_nit: '900123456',
  company_check_digit: '8',
});

const TABLE = [
  {format: '1001', account_prefix: '5110', concept_code: '5001'},
  {format: '1001', account_prefix: '5135', concept_code: '5003'},
  {format: '1007', account_prefix: '41', concept_code: '4001'},
];

const rowOf = (prefix: string): HTMLElement => {
  const row = screen
    .getAllByRole('row')
    .find((r) => within(r).queryAllByRole('cell')[1]?.textContent === prefix);
  if (!row) throw new Error(`No row for ${prefix}`);
  return row;
};

const renderTab = () =>
  render(
    <SessionProvider>
      <ExogenaConcepts />
    </SessionProvider>,
  );

describe('Configuración › Exógena', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('lists the concept of each account prefix by format', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('accountant')],
      'GET /ledger/exogena-concepts': [200, {items: TABLE}],
    });
    renderTab();

    expect(await screen.findByText('5135')).toBeInTheDocument();
    expect(rowOf('5135')).toHaveTextContent('1001 · Pagos');
    expect(rowOf('5135')).toHaveTextContent('5003');
    expect(rowOf('41')).toHaveTextContent('1007 · Ingresos');
  });

  it('adds a row by sending the whole table', async () => {
    const api = fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /ledger/exogena-concepts': [200, {items: TABLE}],
      'PUT /ledger/exogena-concepts': (body) => [200, body],
    });
    renderTab();

    await userEvent.click(
      await screen.findByRole('button', {name: 'Nueva fila'}),
    );
    const dialog = screen.getByRole('dialog');
    await userEvent.type(
      within(dialog).getByLabelText('Prefijo de cuenta'),
      '5115',
    );
    await userEvent.type(
      within(dialog).getByLabelText('Concepto DIAN'),
      '5002',
    );
    await userEvent.click(within(dialog).getByRole('button', {name: 'Guardar'}));

    await waitFor(() =>
      expect(screen.getByText('Tabla de conceptos guardada.')).toBeVisible(),
    );
    const put = api.calls.find((c) => c.method === 'PUT');
    expect(put?.body).toEqual({
      items: [
        TABLE[0],
        {format: '1001', account_prefix: '5115', concept_code: '5002'},
        TABLE[1],
        TABLE[2],
      ],
    });
    expect(rowOf('5115')).toHaveTextContent('5002');
  });

  it('checks the fields before sending', async () => {
    const api = fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /ledger/exogena-concepts': [200, {items: TABLE}],
    });
    renderTab();

    await userEvent.click(
      await screen.findByRole('button', {name: 'Nueva fila'}),
    );
    const dialog = screen.getByRole('dialog');
    await userEvent.type(
      within(dialog).getByLabelText('Prefijo de cuenta'),
      '5110',
    );
    await userEvent.type(within(dialog).getByLabelText('Concepto DIAN'), '50');
    await userEvent.click(within(dialog).getByRole('button', {name: 'Guardar'}));

    expect(
      within(dialog).getByText('Ese prefijo ya está en la tabla del formato 1001.'),
    ).toBeVisible();
    expect(
      within(dialog).getByText('Escribe los cuatro dígitos del concepto.'),
    ).toBeVisible();
    expect(api.calls.some((c) => c.method === 'PUT')).toBe(false);
  });

  it('changes a concept and removes a row after confirming', async () => {
    const api = fakeApi({
      'GET /me': [200, sessionOf('accountant')],
      'GET /ledger/exogena-concepts': [200, {items: TABLE}],
      'PUT /ledger/exogena-concepts': (body) => [200, body],
    });
    renderTab();

    await screen.findByText('5110');
    await userEvent.click(
      within(rowOf('5110')).getByRole('button', {name: 'Editar'}),
    );
    const concept = within(screen.getByRole('dialog')).getByLabelText(
      'Concepto DIAN',
    );
    await userEvent.clear(concept);
    await userEvent.type(concept, '5002');
    await userEvent.click(screen.getByRole('button', {name: 'Guardar'}));
    await waitFor(() => expect(rowOf('5110')).toHaveTextContent('5002'));

    await userEvent.click(
      within(rowOf('41')).getByRole('button', {name: 'Quitar'}),
    );
    await userEvent.click(
      within(screen.getByRole('dialog')).getByRole('button', {name: 'Quitar'}),
    );
    await waitFor(() =>
      expect(
        screen.getByText('Se quitó la fila 1007 · 41.'),
      ).toBeVisible(),
    );
    const last = api.calls.filter((c) => c.method === 'PUT').at(-1);
    expect(last?.body).toEqual({
      items: [
        {format: '1001', account_prefix: '5110', concept_code: '5002'},
        {format: '1001', account_prefix: '5135', concept_code: '5003'},
      ],
    });
  });

  it('shows the server refusal in the form', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /ledger/exogena-concepts': [200, {items: []}],
      'PUT /ledger/exogena-concepts': [
        422,
        {
          error: 'validation_failed',
          violations: [
            {
              field: 'items[0].account_prefix',
              message: 'Escribe el comienzo de un código de cuenta (solo dígitos).',
            },
          ],
        },
      ],
    });
    renderTab();

    expect(
      await screen.findByText(
        'La tabla no tiene filas: ninguna cuenta tendrá concepto en la exógena.',
      ),
    ).toBeVisible();
    await userEvent.click(screen.getByRole('button', {name: 'Nueva fila'}));
    const dialog = screen.getByRole('dialog');
    await userEvent.type(within(dialog).getByLabelText('Prefijo de cuenta'), '9');
    await userEvent.type(within(dialog).getByLabelText('Concepto DIAN'), '5001');
    await userEvent.click(within(dialog).getByRole('button', {name: 'Guardar'}));

    expect(
      await within(dialog).findByText(
        'Escribe el comienzo de un código de cuenta (solo dígitos).',
      ),
    ).toBeVisible();
  });

  it('is read-only and says why for who cannot keep the books', async () => {
    fakeApi({'GET /me': [200, sessionOf('billing')]});
    renderTab();

    expect(
      await screen.findByText(
        'La tabla de conceptos de la exógena es del administrador y del contador.',
      ),
    ).toBeVisible();
    expect(screen.queryByRole('button', {name: 'Nueva fila'})).toBeNull();
  });
});
