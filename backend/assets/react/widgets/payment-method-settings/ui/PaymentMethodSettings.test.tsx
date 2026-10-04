import {permissionsOf} from '@/shared/test/permissions';
import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {PaymentMethodSettings} from './PaymentMethodSettings';

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

const method = (over: Record<string, unknown> = {}) => ({
  id: 'm1',
  name: 'Efectivo',
  kind: 'cash',
  account_id: 'a1',
  account_code: '11050501',
  account_name: 'Caja general',
  active: true,
  standard: true,
  in_use: true,
  ...over,
});

const CREDITO = method({
  id: 'm2',
  name: 'Crédito',
  kind: 'credit',
  account_id: null,
  account_code: null,
  account_name: null,
  in_use: false,
});

const rowOf = (name: string): HTMLElement => {
  const row = screen
    .getAllByRole('row')
    .find((r) => within(r).queryAllByRole('cell')[0]?.textContent === name);
  if (!row) throw new Error(`No row for ${name}`);
  return row;
};

const renderTab = () =>
  render(
    <SessionProvider>
      <PaymentMethodSettings />
    </SessionProvider>,
  );

describe('Configuración › Formas de pago', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('lists the methods with their account, crédito pointing at the tercero', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /settings/payment-methods': [200, {items: [method(), CREDITO]}],
    });
    renderTab();

    expect(
      (await screen.findByText('Efectivo')).closest('tr'),
    ).toHaveTextContent('11050501 · Caja general');
    expect(rowOf('Crédito')).toHaveTextContent('La cuenta del tercero');
  });

  it('offers no edit actions to a billing user', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('billing')],
      'GET /settings/payment-methods': [200, {items: [method()]}],
    });
    renderTab();

    await screen.findByText('Efectivo');
    expect(
      screen.queryByRole('button', {name: 'Nueva forma de pago'}),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Editar'}),
    ).not.toBeInTheDocument();
  });

  it('creates a contado method on a chosen account', async () => {
    const api = fakeApi({
      'GET /me': [200, sessionOf('accountant')],
      'GET /settings/payment-methods': [200, {items: []}],
      'GET /accounts/search': [
        200,
        {
          items: [
            {id: 'a9', code: '11100502', name: 'Bancolombia', postable: true},
          ],
        },
      ],
      'POST /payment-methods': [201, method()],
    });
    renderTab();
    await userEvent.click(
      await screen.findByRole('button', {name: 'Nueva forma de pago'}),
    );
    const dialog = screen.getByRole('dialog');

    await userEvent.type(
      within(dialog).getByLabelText('Nombre'),
      'Bancolombia',
    );
    await userEvent.type(within(dialog).getByLabelText('Cuenta'), '11100502');
    await waitFor(() =>
      expect(within(dialog).getByLabelText('Cuenta')).toHaveValue(
        '11100502 · Bancolombia',
      ),
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Guardar'}),
    );

    await waitFor(() =>
      expect(api.calls.find((c) => c.method === 'POST')).toBeDefined(),
    );
    expect(api.calls.find((c) => c.method === 'POST')?.body).toEqual({
      name: 'Bancolombia',
      kind: 'cash',
      account_id: 'a9',
    });
  });

  it('requires an account for contado but none for crédito', async () => {
    const api = fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /settings/payment-methods': [200, {items: []}],
      'POST /payment-methods': [201, CREDITO],
    });
    renderTab();
    await userEvent.click(
      await screen.findByRole('button', {name: 'Nueva forma de pago'}),
    );
    const dialog = screen.getByRole('dialog');
    await userEvent.type(within(dialog).getByLabelText('Nombre'), 'Crédito 90');

    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Guardar'}),
    );
    expect(
      within(dialog).getByText('Elige la cuenta donde entra el dinero.'),
    ).toBeInTheDocument();
    expect(api.calls.filter((c) => c.method === 'POST')).toHaveLength(0);

    await userEvent.selectOptions(
      within(dialog).getByLabelText('Tipo'),
      'credit',
    );
    expect(within(dialog).queryByLabelText('Cuenta')).not.toBeInTheDocument();
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Guardar'}),
    );
    await waitFor(() =>
      expect(api.calls.find((c) => c.method === 'POST')).toBeDefined(),
    );
    expect(api.calls.find((c) => c.method === 'POST')?.body).toEqual({
      name: 'Crédito 90',
      kind: 'credit',
      account_id: null,
    });
  });

  it('offers Delete only for a method no document uses', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /settings/payment-methods': [200, {items: [method(), CREDITO]}],
    });
    renderTab();

    await screen.findByText('Efectivo');
    expect(
      within(rowOf('Efectivo')).queryByRole('button', {name: 'Eliminar'}),
    ).not.toBeInTheDocument();
    expect(
      within(rowOf('Crédito')).getByRole('button', {name: 'Eliminar'}),
    ).toBeInTheDocument();
  });

  it('deactivates a method, and reactivates an inactive one', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /settings/payment-methods': [
        200,
        {
          items: [
            method(),
            method({id: 'm3', name: 'Nequi', active: false, in_use: false}),
          ],
        },
      ],
      'POST /payment-methods/m1/deactivate': [200, method({active: false})],
      'POST /payment-methods/m3/activate': [
        200,
        method({id: 'm3', name: 'Nequi'}),
      ],
    });
    renderTab();

    await userEvent.click(
      await screen.findByRole('button', {name: 'Desactivar'}),
    );
    expect(
      await screen.findByText('«Efectivo» ya no se ofrece en los documentos.'),
    ).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'Activar'}));
    expect(
      await screen.findByText('«Nequi» se ofrece de nuevo en los documentos.'),
    ).toBeInTheDocument();
  });

  it('explains a method that turned out to be in use', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /settings/payment-methods': [200, {items: [CREDITO]}],
      'DELETE /payment-methods/m2': [
        409,
        {error: 'payment_method_in_use', message: 'x'},
      ],
    });
    renderTab();
    await userEvent.click(
      await screen.findByRole('button', {name: 'Eliminar'}),
    );
    await userEvent.click(
      within(screen.getByRole('dialog')).getByRole('button', {
        name: 'Eliminar',
      }),
    );

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Un documento ya usa esta forma de pago: desactívala en lugar de eliminarla.',
    );
  });
});
