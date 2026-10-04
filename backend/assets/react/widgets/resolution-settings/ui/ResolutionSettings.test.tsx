import {permissionsOf} from '@/shared/test/permissions';
import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {vi} from 'vitest';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {ResolutionSettings} from './ResolutionSettings';

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

const resolution = (over: Record<string, unknown> = {}) => ({
  id: 'r1',
  resolution_number: '18760000001',
  prefix: 'SETP',
  range_from: 1,
  range_to: 1000,
  valid_from: '2026-01-01',
  valid_to: '2026-12-31',
  mode: 'electronic',
  next_number: 1,
  has_issued_numbers: false,
  ...over,
});

const status = (over: Record<string, unknown> = {}) => ({
  status: 'active',
  numbers_left: 1000,
  days_left: 200,
  warning: false,
  warning_numbers: 100,
  warning_days: 30,
  ...over,
});

const settings = (
  over: {
    resolution?: unknown;
    status?: unknown;
    confirmed?: string | null;
  } = {},
) => ({
  resolution: over.resolution === undefined ? resolution() : over.resolution,
  status: over.status ?? status(),
  manual_invoicing_confirmed_at: over.confirmed ?? null,
});

const SERIES = {
  items: [
    {kind: 'quotation', prefix: 'C', next_number: 5},
    {kind: 'cash_receipt', prefix: 'RC', next_number: 1},
  ],
};

const renderTab = () =>
  render(
    <SessionProvider>
      <ResolutionSettings />
    </SessionProvider>,
  );

const routes = (role: string, current = settings()) => ({
  'GET /me': [200, sessionOf(role)] as [number, unknown],
  'GET /company/resolution': [200, current] as [number, unknown],
  'GET /company/numbering': [200, SERIES] as [number, unknown],
});

describe('Configuración › Resolución', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('shows the resolution and that it is in force', async () => {
    fakeApi(routes('owner'));
    renderTab();

    expect(await screen.findByLabelText('Número de resolución')).toHaveValue(
      '18760000001',
    );
    expect(screen.getByLabelText(/^Prefijo/)).toHaveValue('SETP');
    expect(screen.getByLabelText('Hasta')).toHaveValue('1000');
    expect(screen.getByLabelText('Consecutivo actual')).toHaveValue('1');
    expect(
      screen.getByText(
        'Tu resolución está vigente. Te quedan 1000 números y 200 días.',
      ),
    ).toBeInTheDocument();
  });

  it('warns the owner, in a warning of its own, when the resolution is running out', async () => {
    fakeApi(
      routes(
        'owner',
        settings({
          status: status({warning: true, numbers_left: 42, days_left: 12}),
        }),
      ),
    );
    renderTab();

    const warning = await screen.findByText(/se está agotando/);
    expect(warning).toHaveTextContent('te quedan 42 números y 12 días');
    expect(warning.closest('.alert')).toHaveClass('alert-warning');
  });

  it.each([
    ['expired', /venció/],
    ['exhausted', /Se acabaron los números/],
  ])(
    'says in red that a %s resolution blocks invoicing',
    async (state, text) => {
      fakeApi(
        routes(
          'owner',
          settings({status: status({status: state, numbers_left: 0})}),
        ),
      );
      renderTab();

      const banner = await screen.findByText(text);
      expect(banner.closest('.alert')).toHaveClass('alert-error');
    },
  );

  it('invites the owner to set the resolution up when there is none', async () => {
    fakeApi(
      routes(
        'owner',
        settings({
          resolution: null,
          status: status({status: 'missing', numbers_left: 0, days_left: 0}),
        }),
      ),
    );
    renderTab();

    expect(
      await screen.findByText(/Todavía no has configurado tu resolución/),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('button', {name: 'Crear resolución'}),
    ).toBeInTheDocument();
    expect(
      screen.queryByLabelText('Consecutivo actual'),
    ).not.toBeInTheDocument();
  });

  it('creates the resolution with what was typed', async () => {
    const created = settings();
    const api = fakeApi({
      ...routes(
        'owner',
        settings({resolution: null, status: status({status: 'missing'})}),
      ),
      'POST /company/resolution': [201, created],
    });
    renderTab();

    await userEvent.type(
      await screen.findByLabelText('Número de resolución'),
      '18760000001',
    );
    await userEvent.type(screen.getByLabelText(/Prefijo/), 'setp');
    await userEvent.type(screen.getByLabelText('Desde'), '1');
    await userEvent.type(screen.getByLabelText('Hasta'), '1000');
    await userEvent.type(
      screen.getByLabelText('Fecha de inicio'),
      '2026-01-01',
    );
    await userEvent.type(screen.getByLabelText('Fecha de fin'), '2026-12-31');
    await userEvent.click(
      screen.getByRole('button', {name: 'Crear resolución'}),
    );

    expect(
      await screen.findByText('Creamos la resolución.'),
    ).toBeInTheDocument();
    expect(api.calls.find((c) => c.method === 'POST')?.body).toEqual({
      resolution_number: '18760000001',
      prefix: 'setp',
      range_from: 1,
      range_to: 1000,
      valid_from: '2026-01-01',
      valid_to: '2026-12-31',
      mode: 'electronic',
    });
  });

  it('refuses hasta below desde before calling the server', async () => {
    const api = fakeApi(routes('owner'));
    renderTab();

    const to = await screen.findByLabelText('Hasta');
    await userEvent.clear(to);
    await userEvent.type(to, '0');
    await userEvent.click(
      screen.getByRole('button', {name: 'Guardar resolución'}),
    );

    expect(to).toBeInvalid();
    expect(api.calls.some((c) => c.method === 'PUT')).toBe(false);
  });

  it("shows the server's refusal next to the field", async () => {
    fakeApi({
      ...routes('owner'),
      'PUT /company/resolution': [
        422,
        {
          error: 'validation_failed',
          violations: [
            {
              field: 'range_to',
              message:
                'El número final no puede ser menor que el último número usado (10).',
            },
          ],
        },
      ],
    });
    renderTab();

    await userEvent.click(
      await screen.findByRole('button', {name: 'Guardar resolución'}),
    );

    expect(
      await screen.findByText(
        'El número final no puede ser menor que el último número usado (10).',
      ),
    ).toBeInTheDocument();
    expect(screen.getByLabelText('Hasta')).toBeInvalid();
  });

  it('locks desde and the prefix once invoices were numbered', async () => {
    fakeApi(
      routes(
        'owner',
        settings({
          resolution: resolution({has_issued_numbers: true, next_number: 11}),
        }),
      ),
    );
    renderTab();

    expect(await screen.findByLabelText(/^Prefijo/)).toBeDisabled();
    expect(screen.getByLabelText('Desde')).toBeDisabled();
    expect(screen.getByLabelText('Hasta')).toBeEnabled();
    expect(screen.getByText(/Ya hay facturas numeradas/)).toBeInTheDocument();
  });

  it('keeps manual mode unavailable until the owner confirms the DIAN permission', async () => {
    const api = fakeApi({
      ...routes('owner'),
      'POST /company/manual-invoicing-confirmation': [
        200,
        settings({confirmed: '2026-10-03T10:00:00+00:00'}),
      ],
    });
    renderTab();

    const mode = await screen.findByLabelText('Modalidad');
    expect(within(mode).getByRole('option', {name: /Manual/})).toBeDisabled();
    await userEvent.click(
      screen.getByRole('button', {name: 'Confirmar permiso de la DIAN'}),
    );
    await userEvent.click(
      screen.getByRole('button', {
        name: 'Confirmo que la empresa tiene el permiso',
      }),
    );

    expect(
      await screen.findByText(
        'Registramos la confirmación del permiso de la DIAN.',
      ),
    ).toBeInTheDocument();
    expect(
      within(screen.getByLabelText('Modalidad')).getByRole('option', {
        name: /Manual/,
      }),
    ).toBeEnabled();
    expect(
      screen.getByText(
        /Permiso de facturación manual confirmado el 03\/10\/2026/,
      ),
    ).toBeInTheDocument();
    expect(
      api.calls.some(
        (c) => c.path === '/company/manual-invoicing-confirmation',
      ),
    ).toBe(true);
  });

  it('saves the warning thresholds', async () => {
    const api = fakeApi({
      ...routes('owner'),
      'PUT /company/resolution/warnings': [
        200,
        settings({status: status({warning_numbers: 50, warning_days: 10})}),
      ],
    });
    renderTab();

    const numbers = await screen.findByLabelText(
      'Avisar con menos de (números)',
    );
    await userEvent.clear(numbers);
    await userEvent.type(numbers, '50');
    const days = screen.getByLabelText('Avisar con menos de (días)');
    await userEvent.clear(days);
    await userEvent.type(days, '10');
    await userEvent.click(screen.getByRole('button', {name: 'Guardar avisos'}));

    expect(
      await screen.findByText('Guardamos los avisos.'),
    ).toBeInTheDocument();
    expect(api.calls.find((c) => c.method === 'PUT')?.body).toEqual({
      warning_numbers: 50,
      warning_days: 10,
    });
  });

  it('lists the internal series and edits one', async () => {
    const api = fakeApi({
      ...routes('owner'),
      'PUT /company/numbering/quotation': [
        200,
        {kind: 'quotation', prefix: 'COT', next_number: 40},
      ],
    });
    renderTab();

    const row = (await screen.findByText('Cotización')).closest(
      'tr',
    ) as HTMLElement;
    expect(within(row).getByText('C')).toBeInTheDocument();
    await userEvent.click(within(row).getByRole('button', {name: 'Editar'}));
    const dialog = screen.getByRole('dialog', {name: 'Numeración: Cotización'});
    const prefix = within(dialog).getByLabelText(/Prefijo/);
    await userEvent.clear(prefix);
    await userEvent.type(prefix, 'COT');
    const next = within(dialog).getByLabelText('Próximo número');
    await userEvent.clear(next);
    await userEvent.type(next, '40');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Guardar'}),
    );

    expect(
      await screen.findByText('Guardamos la numeración.'),
    ).toBeInTheDocument();
    expect(api.calls.find((c) => c.method === 'PUT')?.body).toEqual({
      prefix: 'COT',
      next_number: 40,
    });
    expect(screen.getByText('COT')).toBeInTheDocument();
  });

  it('refuses a next number below the current one before calling the server', async () => {
    const api = fakeApi(routes('owner'));
    renderTab();

    const row = (await screen.findByText('Cotización')).closest(
      'tr',
    ) as HTMLElement;
    await userEvent.click(within(row).getByRole('button', {name: 'Editar'}));
    const dialog = screen.getByRole('dialog');
    const next = within(dialog).getByLabelText('Próximo número');
    await userEvent.clear(next);
    await userEvent.type(next, '2');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Guardar'}),
    );

    expect(
      within(dialog).getByText(
        'El próximo número no puede ser menor que 5, el actual.',
      ),
    ).toBeInTheDocument();
    expect(api.calls.some((c) => c.method === 'PUT')).toBe(false);
  });

  it('is read-only for the accountant and billing, who still see the warning', async () => {
    fakeApi(
      routes(
        'billing',
        settings({status: status({warning: true, numbers_left: 5})}),
      ),
    );
    renderTab();

    expect(await screen.findByLabelText('Número de resolución')).toBeDisabled();
    expect(screen.getByText(/se está agotando/)).toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Guardar resolución'}),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Guardar avisos'}),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Editar'}),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Confirmar permiso de la DIAN'}),
    ).not.toBeInTheDocument();
  });
});
