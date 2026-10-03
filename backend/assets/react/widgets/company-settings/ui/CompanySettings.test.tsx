import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {vi} from 'vitest';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {CompanySettings} from './CompanySettings';

const sessionOf = (role: string) => ({
  user_id: 'u1',
  email: 'ana@acme.co',
  name: 'Ana',
  role,
  company_id: 'c1',
  company_name: 'Acme',
  company_nit: '900123456',
  company_check_digit: '8',
});

const company = (over: Record<string, unknown> = {}) => ({
  id: 'c1',
  legal_name: 'Acme S.A.S.',
  trade_name: null,
  identification_type: 'nit',
  identification_number: '900123456',
  check_digit: '8',
  address: null,
  city: null,
  phone: null,
  email: null,
  logo_id: null,
  vat_regime: 'responsable',
  fiscal_responsibilities: ['R-99-PN'],
  default_charge_tax_id: null,
  default_withholding_tax_id: null,
  ...over,
});

const tax = (id: string, name: string, taxClass: string, active = true) => ({
  id,
  name,
  tax_class: taxClass,
  kind: 'iva',
  calculation: 'percentage',
  rate: '19.0000',
  active,
  standard: true,
});

const TAXES = {
  items: [
    tax('t1', 'IVA 19 %', 'charge'),
    tax('t2', 'IVA viejo', 'charge', false),
    tax('t3', 'ReteFuente servicios 4 %', 'withholding'),
  ],
};

const renderTab = () =>
  render(
    <SessionProvider>
      <CompanySettings />
    </SessionProvider>,
  );

describe('Configuración › Empresa', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('shows the profile and lets the owner save it', async () => {
    const api = fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /company': [200, company()],
      'GET /taxes': [200, TAXES],
      'PUT /company': (body) => [
        200,
        company({...(body as object), legal_name: 'Acme Ltda.'}),
      ],
    });
    renderTab();

    const name = await screen.findByLabelText('Razón social');
    expect(name).toHaveValue('Acme S.A.S.');
    expect(screen.getByLabelText(/^DV/)).toHaveValue('8');
    await userEvent.clear(name);
    await userEvent.type(name, 'Acme Ltda.');
    await userEvent.type(screen.getByLabelText(/Ciudad/), 'Bogotá');
    await userEvent.selectOptions(
      screen.getByLabelText('Impuesto cargo'),
      'IVA 19 %',
    );
    await userEvent.click(screen.getByLabelText(/O-13/));
    await userEvent.click(
      screen.getByRole('button', {name: 'Guardar cambios'}),
    );

    expect(
      await screen.findByText('Guardamos los datos de la empresa.'),
    ).toBeInTheDocument();
    const put = api.calls.find((c) => c.method === 'PUT');
    expect(put?.body).toMatchObject({
      legal_name: 'Acme Ltda.',
      city: 'Bogotá',
      trade_name: null,
      default_charge_tax_id: 't1',
      default_withholding_tax_id: null,
      fiscal_responsibilities: ['R-99-PN', 'O-13'],
      check_digit: '8',
    });
  });

  it('offers only active taxes of the right class, plus the current default even if deactivated', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /company': [200, company({default_charge_tax_id: 't2'})],
      'GET /taxes': [200, TAXES],
    });
    renderTab();

    const charge = await screen.findByLabelText('Impuesto cargo');
    const options = within(charge)
      .getAllByRole('option')
      .map((o) => o.textContent);
    expect(options).toEqual([
      'Sin impuesto por defecto',
      'IVA 19 %',
      'IVA viejo',
    ]);
    expect(charge).toHaveValue('t2');
    const withholding = screen.getByLabelText('Impuesto de retención');
    expect(
      within(withholding)
        .getAllByRole('option')
        .map((o) => o.textContent),
    ).toEqual(['Sin impuesto por defecto', 'ReteFuente servicios 4 %']);
  });

  it('asks for the razón social and the number before calling the server', async () => {
    const api = fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /company': [200, company()],
      'GET /taxes': [200, TAXES],
    });
    renderTab();

    await userEvent.clear(await screen.findByLabelText('Razón social'));
    await userEvent.clear(screen.getByLabelText('Número de identificación'));
    await userEvent.click(
      screen.getByRole('button', {name: 'Guardar cambios'}),
    );

    expect(screen.getAllByText('Este dato es obligatorio.')).toHaveLength(2);
    expect(api.calls.some((c) => c.method === 'PUT')).toBe(false);
  });

  it("shows the server's refusal next to its field", async () => {
    fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /company': [200, company()],
      'GET /taxes': [200, TAXES],
      'PUT /company': [
        422,
        {
          error: 'validation_failed',
          violations: [
            {
              field: 'identification_number',
              message: 'Ya hay una empresa registrada con este NIT.',
            },
          ],
        },
      ],
    });
    renderTab();

    await screen.findByLabelText('Razón social');
    await userEvent.click(
      screen.getByRole('button', {name: 'Guardar cambios'}),
    );

    expect(
      await screen.findByText('Ya hay una empresa registrada con este NIT.'),
    ).toBeInTheDocument();
    expect(screen.getByLabelText('Número de identificación')).toBeInvalid();
  });

  it('hides the check digit for a document that has none', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /company': [200, company()],
      'GET /taxes': [200, TAXES],
    });
    renderTab();

    await userEvent.selectOptions(
      await screen.findByLabelText('Tipo de documento'),
      'cc',
    );

    expect(screen.queryByLabelText(/^DV/)).not.toBeInTheDocument();
  });

  it('is read-only for the accountant and billing', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('accountant')],
      'GET /company': [200, company()],
      'GET /taxes': [200, TAXES],
    });
    renderTab();

    expect(await screen.findByLabelText('Razón social')).toBeDisabled();
    expect(
      screen.queryByRole('button', {name: 'Guardar cambios'}),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Subir logo'}),
    ).not.toBeInTheDocument();
    expect(
      screen.getByText(
        'Solo el propietario puede cambiar los datos de la empresa.',
      ),
    ).toBeInTheDocument();
  });

  describe('logo', () => {
    const pick = async (file: File) => {
      await userEvent.click(
        await screen.findByRole('button', {name: 'Subir logo'}),
      );
      await userEvent.upload(screen.getByLabelText('Elegir archivo'), file, {
        applyAccept: false,
      });
    };

    it('refuses a file that is not a PNG or JPEG without sending it', async () => {
      const api = fakeApi({
        'GET /me': [200, sessionOf('owner')],
        'GET /company': [200, company()],
        'GET /taxes': [200, TAXES],
      });
      renderTab();

      await pick(new File(['<svg/>'], 'logo.svg', {type: 'image/svg+xml'}));

      expect(
        await screen.findByText('El logo debe ser una imagen PNG o JPG.'),
      ).toBeInTheDocument();
      expect(api.calls.some((c) => c.method === 'POST')).toBe(false);
    });

    it('refuses a file over 2 MB without sending it', async () => {
      const api = fakeApi({
        'GET /me': [200, sessionOf('owner')],
        'GET /company': [200, company()],
        'GET /taxes': [200, TAXES],
      });
      renderTab();

      await pick(
        new File([new Uint8Array(2 * 1024 * 1024 + 1)], 'big.png', {
          type: 'image/png',
        }),
      );

      expect(
        await screen.findByText('El logo pesa más de 2 MB.'),
      ).toBeInTheDocument();
      expect(api.calls.some((c) => c.method === 'POST')).toBe(false);
    });

    it('uploads a PNG as multipart and shows it', async () => {
      const posted: FormData[] = [];
      const base = fakeApi({
        'GET /me': [200, sessionOf('owner')],
        'GET /company': [200, company()],
        'GET /taxes': [200, TAXES],
      });
      vi.stubGlobal(
        'fetch',
        vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
          if (
            String(input).endsWith('/company/logo') &&
            init?.method === 'POST'
          ) {
            posted.push(init.body as FormData);
            return new Response(JSON.stringify(company({logo_id: 'l1'})), {
              status: 200,
            });
          }
          return base.fetch(input, init);
        }),
      );
      renderTab();

      await pick(new File(['png'], 'logo.png', {type: 'image/png'}));

      expect(await screen.findByAltText('Logo de la empresa')).toHaveAttribute(
        'src',
        '/api/v1/company/logo?v=l1',
      );
      expect((posted[0]?.get('file') as File).name).toBe('logo.png');
      expect(screen.getByRole('button', {name: 'Quitar logo'})).toBeEnabled();
    });

    it('removes the logo', async () => {
      const calls: string[] = [];
      const base = fakeApi({
        'GET /me': [200, sessionOf('owner')],
        'GET /company': [200, company({logo_id: 'l1'})],
        'GET /taxes': [200, TAXES],
      });
      vi.stubGlobal(
        'fetch',
        vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
          if (init?.method === 'DELETE') {
            calls.push(String(input));
            return new Response(null, {status: 204});
          }
          return base.fetch(input, init);
        }),
      );
      renderTab();

      await userEvent.click(
        await screen.findByRole('button', {name: 'Quitar logo'}),
      );

      await waitFor(() =>
        expect(
          screen.queryByAltText('Logo de la empresa'),
        ).not.toBeInTheDocument(),
      );
      expect(calls).toEqual(['/api/v1/company/logo']);
    });
  });
});
