import {permissionsOf} from '@/shared/test/permissions';
import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {PostingRules} from './PostingRules';

const me = (role: string) =>
  [
    200,
    {
      user_id: 'u',
      email: 'a@b.co',
      name: 'Ana',
      role,
      permissions: permissionsOf(role),
    },
  ] as [number, unknown];

const rule = (
  concept: string,
  code: string,
  name: string,
  prefixes: string[],
) => ({
  concept,
  account_id: `id-${code}`,
  account_code: code,
  account_name: name,
  allowed_prefixes: prefixes,
});

const RULES = [
  rule('ingreso', '413595', 'VENTA DE OTROS PRODUCTOS', ['41']),
  rule('clientes', '13050501', 'CLIENTES NACIONALES', ['13']),
];

const accountOption = (code: string, name: string) => ({
  id: `id-${code}`,
  code,
  name,
  nature: 'credit',
  level: 'subaccount',
  parent_code: code.slice(0, 4),
  standard: true,
  active: true,
  postable: true,
  usable_on_purchases: false,
});

const renderRules = () =>
  render(
    <SessionProvider>
      <PostingRules />
    </SessionProvider>,
  );

describe('Reglas contables', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('shows each concept with its account and the lock date', async () => {
    fakeApi({
      'GET /me': me('accountant'),
      'GET /posting-rules': [200, {items: RULES}],
      'GET /ledger/lock-date': [200, {locked_until: '2026-09-30'}],
    });
    renderRules();

    expect(await screen.findByText('Ingresos por ventas')).toBeInTheDocument();
    expect(
      screen.getByText('413595 VENTA DE OTROS PRODUCTOS'),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Bloqueado hasta el 30/09/2026.'),
    ).toBeInTheDocument();
  });

  it('moves revenue to a services account, offering only 41 accounts', async () => {
    const api = fakeApi({
      'GET /me': me('accountant'),
      'GET /posting-rules': [200, {items: RULES}],
      'GET /ledger/lock-date': [200, {locked_until: null}],
      'GET /accounts/search': [
        200,
        {
          items: [
            accountOption('415595', 'ACTIVIDADES CONEXAS'),
            accountOption('421040', 'NO OPERACIONAL'),
          ],
        },
      ],
      'PUT /posting-rules/ingreso': [
        200,
        rule('ingreso', '415595', 'ACTIVIDADES CONEXAS', ['41']),
      ],
    });
    renderRules();

    await userEvent.click(
      await screen.findByRole('button', {
        name: 'Cambiar la cuenta de Ingresos por ventas',
      }),
    );
    const dialog = screen.getByRole('dialog');
    expect(
      within(dialog).getByText('Solo cuentas que empiezan por 41.'),
    ).toBeInTheDocument();
    await userEvent.type(within(dialog).getByLabelText('Buscar cuenta'), '415');
    const select = within(dialog).getByLabelText('Cuenta');
    expect(
      await within(select).findByRole('option', {
        name: '415595 ACTIVIDADES CONEXAS',
      }),
    ).toBeInTheDocument();
    expect(within(select).queryByRole('option', {name: /421040/})).toBeNull();
    await userEvent.selectOptions(select, 'id-415595');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Guardar'}),
    );

    expect(
      await screen.findByText(
        'Ingresos por ventas ahora va a la cuenta 415595.',
      ),
    ).toBeInTheDocument();
    expect(screen.getByText('415595 ACTIVIDADES CONEXAS')).toBeInTheDocument();
    expect(api.calls.find((c) => c.method === 'PUT')?.body).toEqual({
      account_id: 'id-415595',
    });
  });

  it('locks the books until a date', async () => {
    const api = fakeApi({
      'GET /me': me('owner'),
      'GET /posting-rules': [200, {items: RULES}],
      'GET /ledger/lock-date': [200, {locked_until: null}],
      'PUT /ledger/lock-date': (body) => [200, body],
    });
    renderRules();

    expect(
      await screen.findByText(
        'Los libros están abiertos: no hay fecha de bloqueo.',
      ),
    ).toBeInTheDocument();
    await userEvent.type(screen.getByLabelText('Bloquear hasta'), '30/09/2026');
    await userEvent.click(screen.getByRole('button', {name: 'Guardar fecha'}));

    expect(
      await screen.findByText('Libros bloqueados hasta el 30/09/2026.'),
    ).toBeInTheDocument();
    expect(api.calls.find((c) => c.method === 'PUT')?.body).toEqual({
      locked_until: '2026-09-30',
    });
  });

  it('explains a refused lock date', async () => {
    fakeApi({
      'GET /me': me('owner'),
      'GET /posting-rules': [200, {items: RULES}],
      'GET /ledger/lock-date': [200, {locked_until: null}],
      'PUT /ledger/lock-date': [
        422,
        {error: 'lock_date_in_future', message: 'x'},
      ],
    });
    renderRules();

    await userEvent.type(
      await screen.findByLabelText('Bloquear hasta'),
      '01/01/2999',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Guardar fecha'}));

    expect(
      await screen.findByText(
        'La fecha de bloqueo no puede ser posterior a hoy.',
      ),
    ).toBeInTheDocument();
  });

  it('shows a billing user the rules without changing them', async () => {
    fakeApi({
      'GET /me': me('billing'),
      'GET /posting-rules': [200, {items: RULES}],
      'GET /ledger/lock-date': [200, {locked_until: null}],
    });
    renderRules();

    expect(await screen.findByText('Ingresos por ventas')).toBeInTheDocument();
    expect(screen.queryByRole('button', {name: /Cambiar/})).toBeNull();
    expect(screen.queryByLabelText('Bloquear hasta')).toBeNull();
  });
});
