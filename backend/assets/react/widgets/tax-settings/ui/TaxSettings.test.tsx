import {permissionsOf} from '@/shared/test/permissions';
import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {TaxSettings} from './TaxSettings';
import {describeRate, describeValidity} from './format';

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

const tax = (over: Record<string, unknown> = {}) => ({
  id: 't1',
  name: 'IVA 19 %',
  tax_class: 'charge',
  kind: 'iva',
  calculation: 'percentage',
  rate: '19.0000',
  sales_account_id: 'a1',
  sales_account_code: '240805',
  sales_account_name: 'IVA generado',
  purchase_account_id: null,
  purchase_account_code: null,
  purchase_account_name: null,
  valid_from: null,
  valid_to: null,
  active: true,
  standard: true,
  in_use: true,
  ...over,
});

const NINGUNO = tax({
  id: 't0',
  name: 'Ninguno',
  kind: 'none',
  rate: '0.0000',
  in_use: false,
  sales_account_id: null,
  sales_account_code: null,
});

const renderTab = () =>
  render(
    <SessionProvider>
      <TaxSettings />
    </SessionProvider>,
  );

describe('Configuración › Impuestos', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('lists the taxes with their rate, validity and account', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('accountant')],
      'GET /settings/taxes': [
        200,
        {
          items: [
            tax(),
            tax({
              id: 't2',
              name: 'ReteFuente compras 2,5 %',
              tax_class: 'withholding',
              kind: 'retefuente',
              rate: '2.5000',
              valid_from: '2026-01-01',
              in_use: false,
            }),
          ],
        },
      ],
    });
    renderTab();

    const row = (await screen.findByText('IVA 19 %')).closest('tr');
    expect(row).toHaveTextContent('Impuesto · IVA');
    expect(row).toHaveTextContent('19 %');
    expect(row).toHaveTextContent('Siempre');
    expect(row).toHaveTextContent('240805');
    const withholding = screen
      .getByText('ReteFuente compras 2,5 %')
      .closest('tr');
    expect(withholding).toHaveTextContent('2,5 %');
    expect(withholding).toHaveTextContent('Desde 01/01/2026');
  });

  it('offers no edit actions to a billing user', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('billing')],
      'GET /settings/taxes': [200, {items: [tax()]}],
    });
    renderTab();

    await screen.findByText('IVA 19 %');
    expect(
      screen.queryByRole('button', {name: 'Nuevo impuesto'}),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Editar'}),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole('columnheader', {name: 'Acciones'}),
    ).not.toBeInTheDocument();
  });

  it('offers Delete only for a tax no document uses, and nothing for Ninguno', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /settings/taxes': [
        200,
        {
          items: [
            NINGUNO,
            tax(),
            tax({id: 't3', name: 'IVA 16 %', in_use: false, standard: false}),
          ],
        },
      ],
    });
    renderTab();

    await screen.findByText('IVA 19 %');
    const rowOf = (name: string) =>
      screen.getByText(name, {selector: 'td'}).closest('tr') as HTMLElement;
    expect(
      within(rowOf('IVA 19 %')).queryByRole('button', {name: 'Eliminar'}),
    ).not.toBeInTheDocument();
    expect(
      within(rowOf('IVA 19 %')).getByRole('button', {name: 'Desactivar'}),
    ).toBeInTheDocument();
    expect(
      within(rowOf('IVA 16 %')).getByRole('button', {name: 'Eliminar'}),
    ).toBeInTheDocument();
    expect(within(rowOf('Ninguno')).queryAllByRole('button')).toHaveLength(0);
  });

  it('creates a tax with its rate, dates and account', async () => {
    const api = fakeApi({
      'GET /me': [200, sessionOf('accountant')],
      'GET /settings/taxes': [200, {items: []}],
      'GET /accounts/search': [
        200,
        {
          items: [
            {id: 'a1', code: '240805', name: 'IVA generado', postable: true},
          ],
        },
      ],
      'POST /taxes': [201, tax()],
    });
    renderTab();
    await userEvent.click(
      await screen.findByRole('button', {name: 'Nuevo impuesto'}),
    );

    const dialog = screen.getByRole('dialog', {name: 'Nuevo impuesto'});
    await userEvent.type(within(dialog).getByLabelText('Nombre'), 'IVA 16 %');
    await userEvent.type(within(dialog).getByLabelText('Tarifa (%)'), '16');
    await userEvent.type(
      within(dialog).getByLabelText(/Vigente desde/),
      '01/01/2027',
    );
    await userEvent.type(
      within(dialog).getByLabelText(/Cuenta en ventas/),
      '240805',
    );
    await waitFor(() =>
      expect(within(dialog).getByLabelText(/Cuenta en ventas/)).toHaveValue(
        '240805 · IVA generado',
      ),
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Guardar'}),
    );

    await waitFor(() =>
      expect(api.calls.find((c) => c.method === 'POST')).toBeDefined(),
    );
    expect(api.calls.find((c) => c.method === 'POST')?.body).toEqual({
      name: 'IVA 16 %',
      tax_class: 'charge',
      kind: 'iva',
      calculation: 'percentage',
      rate: '16',
      sales_account_id: 'a1',
      purchase_account_id: null,
      valid_from: '2027-01-01',
      valid_to: null,
    });
    expect(await screen.findByText('Impuesto creado.')).toBeInTheDocument();
  });

  it('drops the account message once a valid account is typed (M6)', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('accountant')],
      'GET /settings/taxes': [200, {items: []}],
      'GET /accounts/search': [
        200,
        {
          items: [
            {id: 'a1', code: '240805', name: 'IVA generado', postable: true},
          ],
        },
      ],
    });
    renderTab();
    await userEvent.click(
      await screen.findByRole('button', {name: 'Nuevo impuesto'}),
    );
    const dialog = screen.getByRole('dialog', {name: 'Nuevo impuesto'});
    const account = within(dialog).getByLabelText(/Cuenta en ventas/);
    await userEvent.type(account, 'no es una cuenta');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Guardar'}),
    );
    expect(
      await within(dialog).findByText('Elige una cuenta de la lista.'),
    ).toBeInTheDocument();

    await userEvent.clear(account);
    await userEvent.type(account, '240805');

    await waitFor(() => expect(account).toHaveValue('240805 · IVA generado'));
    expect(
      within(dialog).queryByText('Elige una cuenta de la lista.'),
    ).toBeNull();
  });

  it('accepts a decimal comma and sends a decimal point', async () => {
    const api = fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /settings/taxes': [200, {items: []}],
      'POST /taxes': [201, tax()],
    });
    renderTab();
    await userEvent.click(
      await screen.findByRole('button', {name: 'Nuevo impuesto'}),
    );
    const dialog = screen.getByRole('dialog');

    await userEvent.type(within(dialog).getByLabelText('Nombre'), 'ReteICA');
    await userEvent.selectOptions(
      within(dialog).getByLabelText('Clase'),
      'withholding',
    );
    await userEvent.selectOptions(
      within(dialog).getByLabelText('Tipo'),
      'reteica',
    );
    await userEvent.type(within(dialog).getByLabelText('Tarifa (%)'), '0,966');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Guardar'}),
    );

    await waitFor(() =>
      expect(api.calls.find((c) => c.method === 'POST')).toBeDefined(),
    );
    expect(api.calls.find((c) => c.method === 'POST')?.body).toMatchObject({
      tax_class: 'withholding',
      kind: 'reteica',
      rate: '0.966',
    });
  });

  it("explains what is wrong before sending, and shows the server's refusal on its field", async () => {
    const api = fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /settings/taxes': [200, {items: []}],
      'POST /taxes': [
        422,
        {
          error: 'validation_failed',
          violations: [
            {field: 'name', message: 'Ya hay un impuesto con este nombre.'},
          ],
        },
      ],
    });
    renderTab();
    await userEvent.click(
      await screen.findByRole('button', {name: 'Nuevo impuesto'}),
    );
    const dialog = screen.getByRole('dialog');

    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Guardar'}),
    );
    expect(
      within(dialog).getByText('Este campo es obligatorio.'),
    ).toBeInTheDocument();
    expect(
      within(dialog).getByText(
        'Escribe un número, con hasta cuatro decimales.',
      ),
    ).toBeInTheDocument();
    expect(api.calls.filter((c) => c.method === 'POST')).toHaveLength(0);

    await userEvent.type(within(dialog).getByLabelText('Nombre'), 'IVA 19 %');
    await userEvent.type(within(dialog).getByLabelText('Tarifa (%)'), '19');
    await userEvent.type(
      within(dialog).getByLabelText(/Vigente desde/),
      '01/02/2027',
    );
    await userEvent.type(
      within(dialog).getByLabelText(/Vigente hasta/),
      '01/01/2027',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Guardar'}),
    );
    expect(
      within(dialog).getByText(
        'La fecha final no puede ser anterior a la inicial.',
      ),
    ).toBeInTheDocument();

    await userEvent.clear(within(dialog).getByLabelText(/Vigente hasta/));
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Guardar'}),
    );
    expect(
      await within(dialog).findByText('Ya hay un impuesto con este nombre.'),
    ).toBeInTheDocument();
  });

  it('edits a tax: the class and kind are shown, not chosen', async () => {
    const api = fakeApi({
      'GET /me': [200, sessionOf('accountant')],
      'GET /settings/taxes': [200, {items: [tax({in_use: false})]}],
      'PUT /taxes/t1': [200, tax({rate: '16.0000'})],
    });
    renderTab();
    await userEvent.click(await screen.findByRole('button', {name: 'Editar'}));
    const dialog = screen.getByRole('dialog', {name: 'Editar impuesto'});

    expect(within(dialog).queryByLabelText('Clase')).not.toBeInTheDocument();
    expect(within(dialog).getByLabelText('Tarifa (%)')).toHaveValue('19');
    expect(within(dialog).getByLabelText(/Cuenta en ventas/)).toHaveValue(
      '240805 · IVA generado',
    );
    await userEvent.clear(within(dialog).getByLabelText('Tarifa (%)'));
    await userEvent.type(within(dialog).getByLabelText('Tarifa (%)'), '16');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Guardar'}),
    );

    await waitFor(() =>
      expect(api.calls.find((c) => c.method === 'PUT')).toBeDefined(),
    );
    expect(api.calls.find((c) => c.method === 'PUT')?.body).toMatchObject({
      rate: '16',
      sales_account_id: 'a1',
      name: 'IVA 19 %',
    });
  });

  it('deactivates a tax and says so', async () => {
    const api = fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /settings/taxes': [200, {items: [tax()]}],
      'POST /taxes/t1/deactivate': [200, tax({active: false})],
    });
    renderTab();

    await userEvent.click(
      await screen.findByRole('button', {name: 'Desactivar'}),
    );

    expect(
      await screen.findByText('«IVA 19 %» ya no se ofrece en los documentos.'),
    ).toBeInTheDocument();
    expect(api.calls.filter((c) => c.path === '/settings/taxes')).toHaveLength(
      2,
    );
  });

  it('asks before deleting, and explains a tax that turned out to be in use', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /settings/taxes': [200, {items: [tax({in_use: false})]}],
      'DELETE /taxes/t1': [409, {error: 'tax_in_use', message: 'x'}],
    });
    renderTab();
    await userEvent.click(
      await screen.findByRole('button', {name: 'Eliminar'}),
    );

    const dialog = screen.getByRole('dialog', {name: 'Eliminar impuesto'});
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Eliminar'}),
    );

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Un documento ya usa este impuesto: desactívalo en lugar de eliminarlo.',
    );
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });
});

describe('how a tax reads in the table', () => {
  it('writes rates the Colombian way', () => {
    expect(describeRate({calculation: 'percentage', rate: '19.0000'})).toBe(
      '19 %',
    );
    expect(describeRate({calculation: 'percentage', rate: '2.5000'})).toBe(
      '2,5 %',
    );
    expect(describeRate({calculation: 'percentage', rate: '0.9660'})).toBe(
      '0,966 %',
    );
    expect(describeRate({calculation: 'percentage', rate: '0.0000'})).toBe(
      '0 %',
    );
    expect(describeRate({calculation: 'per_unit', rate: '500.0000'})).toBe(
      '$ 500,00',
    );
  });

  it('describes the validity dates', () => {
    expect(describeValidity({valid_from: null, valid_to: null}).key).toBe(
      'always',
    );
    expect(
      describeValidity({valid_from: '2026-01-01', valid_to: null}).key,
    ).toBe('from');
    expect(
      describeValidity({valid_from: null, valid_to: '2026-12-31'}).key,
    ).toBe('until');
    expect(
      describeValidity({valid_from: '2026-01-01', valid_to: '2026-12-31'})
        .params,
    ).toEqual({from: '01/01/2026', to: '31/12/2026'});
  });
});
