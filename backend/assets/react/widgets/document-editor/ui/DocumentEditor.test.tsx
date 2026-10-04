import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {useState} from 'react';
import {fakeApi} from '@/shared/test/fakeApi';
import {emptyDraft, emptyLine} from '../model/draft';
import type {
  DocumentDraft,
  DocumentKind,
  DraftLine,
  EditorErrors,
} from '../model/types';
import {DocumentEditor} from './DocumentEditor';

const tax = (
  id: string,
  name: string,
  taxClass: 'charge' | 'withholding',
  rate: string,
  calculation = 'percentage',
) => ({
  id,
  name,
  tax_class: taxClass,
  kind: 'iva',
  calculation,
  rate,
  active: true,
  standard: true,
});

const CHARGE = [
  tax('iva19', 'IVA 19 %', 'charge', '19.0000'),
  tax('iva5', 'IVA 5 %', 'charge', '5.0000'),
];
const WITHHOLDING = [
  tax('rete4', 'ReteFuente servicios 4 %', 'withholding', '4.0000'),
];
const METHODS = [
  {id: 'cash', name: 'Efectivo', kind: 'cash', active: true, standard: true},
  {id: 'credit', name: 'Crédito', kind: 'credit', active: true, standard: true},
];

const TERCERO = {
  id: 't1',
  display_name: 'Cliente Uno S.A.S.',
  person_type: 'empresa',
  identification_type: 'nit',
  identification_number: '900123456',
  check_digit: '8',
  roles: ['cliente'],
  active: true,
  branch_code: '0',
};

const PRODUCT = {
  id: 'p1',
  type: 'servicio',
  code: 'SRV-01',
  name: 'Hora de consultoría',
  unit_code: '94',
  sale_price: '119000.0000',
  price_includes_tax: true,
  unit_price_net_of_tax: '100000.0000',
  charge_tax_id: 'iva19',
  withholding_tax_id: null,
  active: true,
  revenue_account_label: null,
  expense_account_label: null,
};

type Routes = Parameters<typeof fakeApi>[0];

function api(extra: Routes = {}) {
  return fakeApi({
    'GET /taxes': (_body, url) => [
      200,
      {
        items:
          url.searchParams.get('class') === 'charge' ? CHARGE : WITHHOLDING,
      },
    ],
    'GET /payment-methods': [200, {items: METHODS}],
    'GET /terceros': [200, {items: [TERCERO], total: 1, page: 1, per_page: 10}],
    'GET /terceros/t1/contacts': [
      200,
      {items: [{id: 'c1', name: 'Ana Pérez', email: 'ana@uno.co'}]},
    ],
    'GET /products': [200, {items: [PRODUCT], total: 1, page: 1, per_page: 10}],
    ...extra,
  });
}

function line(change: Partial<DraftLine>): DraftLine {
  return {...emptyLine(), ...change};
}

function draft(change: Partial<DocumentDraft> = {}): DocumentDraft {
  return {
    ...emptyDraft({
      typeLabel: 'FV · Factura de venta',
      number: null,
      today: '2026-10-03',
    }),
    ...change,
  };
}

/** The editor is controlled: the harness holds its value like a page would. */
function renderEditor({
  kind = 'sales_invoice',
  value = draft(),
  readOnly,
  errors,
  onAttach,
  onRemoveAttachment,
}: {
  kind?: DocumentKind;
  value?: DocumentDraft;
  readOnly?: boolean;
  errors?: EditorErrors;
  onAttach?: (files: File[]) => void;
  onRemoveAttachment?: (id: string) => void;
} = {}) {
  const changes: DocumentDraft[] = [];
  function Harness() {
    const [current, setCurrent] = useState(value);
    return (
      <DocumentEditor
        kind={kind}
        value={current}
        onChange={(next) => {
          changes.push(next);
          setCurrent(next);
        }}
        readOnly={readOnly}
        errors={errors}
        onAttach={onAttach}
        onRemoveAttachment={onRemoveAttachment}
      />
    );
  }
  render(<Harness />);
  return {latest: () => changes[changes.length - 1], changes};
}

/** The amount the totals panel shows next to a label. */
function total(label: string): string {
  const panel = screen.getByRole('region', {name: 'Totales'});
  const term = within(panel).getByText(label, {selector: 'dt'});
  return term.nextElementSibling?.textContent ?? '';
}

const PRD_LINE = line({
  product: {id: 'p1', label: 'SRV-01 · Hora de consultoría'},
  description: 'Consultoría',
  quantity: '2',
  unit_price: '1000000',
  discount: '10',
  charge_tax_id: 'iva19',
  withholding_tax_id: 'rete4',
});

describe('the document form (§4.6)', () => {
  afterEach(() => vi.unstubAllGlobals());

  describe('header', () => {
    it('shows Tipo and Número as given, and the issue date it was given', () => {
      api();
      renderEditor({value: draft({number: null})});

      expect(screen.getByLabelText('Tipo')).toHaveValue(
        'FV · Factura de venta',
      );
      expect(screen.getByLabelText('Tipo')).toHaveAttribute('readonly');
      expect(screen.getByLabelText('Número')).toHaveValue(
        'Se asigna al emitir',
      );
      expect(
        screen.getByLabelText('Fecha de elaboración'),
        'DD/MM/YYYY whatever the browser’s language',
      ).toHaveValue('03/10/2026');
    });

    it('searches terceros only from the third character, then loads the contacts of the one chosen', async () => {
      const calls = api();
      const {latest} = renderEditor();

      const search = screen.getByRole('combobox', {name: 'Cliente'});
      await userEvent.type(search, 'cl');
      expect(screen.getByText('Escribe al menos 3 caracteres.')).toBeVisible();
      expect(
        calls.calls.filter((c) => c.path === '/terceros'),
        'two characters do not search',
      ).toHaveLength(0);

      await userEvent.type(search, 'i');
      await userEvent.click(
        await screen.findByRole('option', {name: /Cliente Uno S\.A\.S\./}),
      );

      const request = calls.calls.find((c) => c.path === '/terceros');
      expect(request?.url.searchParams.get('q')).toBe('cli');
      expect(latest()?.tercero).toEqual({id: 't1', name: 'Cliente Uno S.A.S.'});
      expect(search).toHaveValue('Cliente Uno S.A.S.');

      await userEvent.selectOptions(
        await screen.findByRole('combobox', {name: /^Contacto/}),
        await screen.findByRole('option', {name: 'Ana Pérez'}),
      );
      expect(latest()?.contact_id).toBe('c1');
    });

    it('creates a client from the search with the role the document needs', async () => {
      const calls = api({
        'GET /terceros': [200, {items: [], total: 0, page: 1, per_page: 10}],
        'POST /terceros/quick': [
          201,
          {...TERCERO, id: 't9', display_name: 'Nuevo S.A.S.'},
        ],
        'GET /terceros/t9/contacts': [200, {items: []}],
      });
      const {latest} = renderEditor();

      await userEvent.type(
        screen.getByRole('combobox', {name: 'Cliente'}),
        'Nuevo',
      );
      await userEvent.click(
        await screen.findByRole('option', {name: '+ Crear nuevo'}),
      );

      const modal = screen.getByRole('dialog', {name: /tercero/i});
      expect(
        within(modal).getByRole('checkbox', {name: 'Cliente'}),
        'a sale creates a cliente',
      ).toBeChecked();
      await userEvent.type(
        within(modal).getByLabelText('Número de identificación'),
        '800197268',
      );
      await userEvent.type(
        within(modal).getByLabelText('Razón social'),
        'Nuevo S.A.S.',
      );
      await userEvent.type(
        within(modal).getByLabelText('Correo electrónico'),
        'nuevo@x.co',
      );
      await userEvent.click(
        within(modal).getByRole('button', {name: 'Crear tercero'}),
      );

      await waitFor(() =>
        expect(latest()?.tercero).toEqual({id: 't9', name: 'Nuevo S.A.S.'}),
      );
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      expect(
        calls.calls.find((c) => c.path === '/terceros/quick')?.body,
      ).toMatchObject({roles: ['cliente']});
    });

    it('asks a purchase for a proveedor, and creates one as proveedor', async () => {
      api({
        'GET /terceros': [200, {items: [], total: 0, page: 1, per_page: 10}],
      });
      renderEditor({kind: 'purchase_invoice'});

      await userEvent.type(
        screen.getByRole('combobox', {name: 'Proveedor'}),
        'Acme',
      );
      await userEvent.click(
        await screen.findByRole('option', {name: '+ Crear nuevo'}),
      );

      const modal = screen.getByRole('dialog', {name: /tercero/i});
      expect(
        within(modal).getByRole('checkbox', {name: 'Proveedor'}),
      ).toBeChecked();
      expect(
        within(modal).getByRole('checkbox', {name: 'Cliente'}),
      ).not.toBeChecked();
    });
  });

  describe('lines', () => {
    it('fills a line from the product chosen and totals it', async () => {
      const calls = api();
      const {latest} = renderEditor();

      await userEvent.type(
        screen.getByRole('combobox', {name: 'Producto/Servicio, línea 1'}),
        'cons',
      );
      await userEvent.click(
        await screen.findByRole('option', {
          name: /SRV-01 · Hora de consultoría/,
        }),
      );

      expect(
        calls.calls
          .find((c) => c.path === '/products')
          ?.url.searchParams.get('q'),
      ).toBe('cons');
      const filled = latest()?.lines[0];
      expect(filled?.product?.id).toBe('p1');
      expect(screen.getByLabelText('Descripción, línea 1')).toHaveValue(
        'Hora de consultoría',
      );
      expect(screen.getByLabelText('Cantidad, línea 1')).toHaveValue('1');
      expect(
        screen.getByLabelText('Valor unitario, línea 1'),
        'the price net of IVA, as a Colombian writes it',
      ).toHaveValue('100.000');
      expect(screen.getByLabelText('Impuesto cargo, línea 1')).toHaveValue(
        'iva19',
      );
      expect(
        screen.getByRole('cell', {name: '$ 119.000,00'}),
        'Valor total of an IVA-included price is the list price',
      ).toBeInTheDocument();
      expect(total('Total neto')).toBe('$ 119.000,00');
    });

    it('creates a product from the line search and puts it on the line', async () => {
      api({
        'GET /products': [200, {items: [], total: 0, page: 1, per_page: 10}],
        'POST /products/quick': [
          201,
          {...PRODUCT, id: 'p9', code: 'NEW', name: 'Cuaderno'},
        ],
      });
      const {latest} = renderEditor();

      await userEvent.type(
        screen.getByRole('combobox', {name: 'Producto/Servicio, línea 1'}),
        'Cuaderno',
      );
      await userEvent.click(
        await screen.findByRole('option', {name: '+ Crear nuevo'}),
      );

      const modal = screen.getByRole('dialog');
      expect(
        within(modal).getByLabelText('Nombre'),
        'starts from what was typed',
      ).toHaveValue('Cuaderno');
      await userEvent.type(within(modal).getByLabelText('Código'), 'NEW');
      await userEvent.type(
        within(modal).getByLabelText('Precio de venta (COP)'),
        '119000',
      );
      await userEvent.click(within(modal).getByRole('button', {name: 'Crear'}));

      await waitFor(() => expect(latest()?.lines[0]?.product?.id).toBe('p9'));
      expect(
        screen.getByRole('combobox', {name: 'Producto/Servicio, línea 1'}),
      ).toHaveValue('NEW · Cuaderno');
    });

    it('lets a purchase line go straight to an expense account', async () => {
      const calls = api({
        'GET /accounts/search': [
          200,
          {
            items: [
              {
                id: 'a5135',
                code: '513525',
                name: 'Acueducto y alcantarillado',
                nature: 'debit',
                level: 'auxiliary',
                standard: true,
                active: true,
                postable: true,
                usable_on_purchases: true,
              },
            ],
          },
        ],
      });
      const {latest} = renderEditor({kind: 'purchase_invoice'});

      await userEvent.selectOptions(
        screen.getByLabelText('Tipo de línea 1'),
        'account',
      );
      await userEvent.type(
        screen.getByLabelText('Producto/Servicio o cuenta, línea 1'),
        '513525',
      );

      await waitFor(() =>
        expect(latest()?.lines[0]?.account?.id).toBe('a5135'),
      );
      const search = calls.calls.find((c) => c.path === '/accounts/search');
      expect(
        search?.url.searchParams.get('purchases'),
        'only accounts usable on purchases',
      ).toBe('1');
    });

    it('adds, reorders and removes lines with buttons that say which line', async () => {
      api();
      const {latest} = renderEditor({
        value: draft({
          lines: [line({description: 'uno'}), line({description: 'dos'})],
        }),
      });

      await userEvent.click(
        screen.getByRole('button', {name: 'Bajar línea 1'}),
      );
      expect(latest()?.lines.map((l) => l.description)).toEqual(['dos', 'uno']);

      await userEvent.click(
        screen.getByRole('button', {name: 'Subir línea 2'}),
      );
      expect(latest()?.lines.map((l) => l.description)).toEqual(['uno', 'dos']);

      await userEvent.click(
        screen.getByRole('button', {name: 'Agregar línea'}),
      );
      expect(latest()?.lines).toHaveLength(3);

      await userEvent.click(
        screen.getByRole('button', {name: 'Quitar línea 1'}),
      );
      expect(latest()?.lines.map((l) => l.description)).toEqual(['dos', '']);
    });

    it('works from the keyboard: Enter on the last line adds one, Alt+arrows move a line', async () => {
      api();
      const {latest} = renderEditor({
        value: draft({
          lines: [line({description: 'uno'}), line({description: 'dos'})],
        }),
      });

      await userEvent.click(screen.getByLabelText('% Descuento, línea 2'));
      await userEvent.keyboard('{Enter}');
      expect(latest()?.lines).toHaveLength(3);
      expect(
        screen.getByRole('combobox', {name: 'Producto/Servicio, línea 3'}),
        'the new line is ready to type in',
      ).toHaveFocus();

      await userEvent.click(screen.getByLabelText('Descripción, línea 1'));
      await userEvent.keyboard('{Enter}');
      expect(
        latest()?.lines,
        'Enter on another line does not add one',
      ).toHaveLength(3);
      expect(
        screen.getByLabelText('Descripción, línea 2'),
        'it goes down to the same cell of the next line',
      ).toHaveFocus();

      await userEvent.keyboard('{Alt>}{ArrowUp}{/Alt}');
      expect(latest()?.lines.map((l) => l.description)).toEqual([
        'dos',
        'uno',
        '',
      ]);
      expect(
        screen.getByLabelText('Descripción, línea 1'),
        'the focus moves with the line',
      ).toHaveFocus();

      await userEvent.keyboard('{Alt>}{ArrowDown}{/Alt}');
      expect(latest()?.lines.map((l) => l.description)).toEqual([
        'uno',
        'dos',
        '',
      ]);
      expect(screen.getByLabelText('Descripción, línea 2')).toHaveFocus();
    });

    it('changes a line’s taxes in the tax dialog, which shows its subtotal', async () => {
      api();
      const {latest} = renderEditor({value: draft({lines: [PRD_LINE]})});

      await userEvent.click(
        screen.getByRole('button', {name: 'Impuestos de la línea 1'}),
      );
      const dialog = screen.getByRole('dialog', {
        name: 'Impuestos de la línea 1',
      });
      expect(within(dialog).getByText('$ 1.800.000,00')).toBeInTheDocument();

      await userEvent.selectOptions(
        await within(dialog).findByLabelText('Impuesto cargo'),
        'iva5',
      );
      await userEvent.click(
        within(dialog).getByRole('button', {name: 'Aplicar'}),
      );

      expect(latest()?.lines[0]?.charge_tax_id).toBe('iva5');
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      expect(total('Impuestos')).toBe('$ 90.000,00');
    });
  });

  describe('totals', () => {
    it('previews the PRD example as the server computes it', async () => {
      api();
      renderEditor({value: draft({lines: [PRD_LINE]})});

      await waitFor(() => expect(total('Impuestos')).toBe('$ 342.000,00'));
      expect(total('Total bruto')).toBe('$ 2.000.000,00');
      expect(total('Descuentos')).toBe('$ 200.000,00');
      expect(total('Subtotal')).toBe('$ 1.800.000,00');
      expect(total('Retenciones')).toBe('$ 72.000,00');
      expect(total('Total neto')).toBe('$ 2.070.000,00');
    });

    it('rounds once at document level (DocumentTotalsTest)', async () => {
      api();
      const third = {
        quantity: '1',
        unit_price: '0.3333',
        charge_tax_id: 'iva19',
      };
      renderEditor({
        value: draft({lines: [line(third), line(third), line(third)]}),
      });

      await waitFor(() => expect(total('Impuestos')).toBe('$ 0,19'));
      expect(total('Total bruto')).toBe('$ 1,00');
    });

    it('recomputes as the person types', async () => {
      api();
      renderEditor({value: draft({lines: [line({quantity: '1'})]})});

      await userEvent.type(
        screen.getByLabelText('Valor unitario, línea 1'),
        '1500.5',
      );
      expect(total('Total neto')).toBe('$ 1.500,50');
      await userEvent.clear(screen.getByLabelText('Cantidad, línea 1'));
      await userEvent.type(screen.getByLabelText('Cantidad, línea 1'), '3');
      expect(total('Total neto')).toBe('$ 4.501,50');
    });

    it('reads a unit price and a payment typed the Colombian way', async () => {
      api();
      const {latest} = renderEditor({
        value: draft({lines: [line({quantity: '1'})]}),
      });

      await userEvent.type(
        screen.getByLabelText('Valor unitario, línea 1'),
        '1.190.000,5',
      );
      await userEvent.click(
        screen.getByRole('button', {name: 'Agregar forma de pago'}),
      );
      await userEvent.clear(
        screen.getByLabelText('Valor de la forma de pago 1'),
      );
      await userEvent.type(
        screen.getByLabelText('Valor de la forma de pago 1'),
        '1.000.000,25',
      );

      expect(
        latest()?.lines[0]?.unit_price,
        'the draft holds a decimal with a point, never what was typed',
      ).toBe('1190000.5');
      expect(latest()?.payments[0]?.amount).toBe('1000000.25');
      expect(total('Total neto')).toBe('$ 1.190.000,50');
    });
  });

  describe('taxes in force (F5)', () => {
    /** IVA 5 % is in force only from 2027: the server leaves it out of ?on= before then. */
    function datedApi() {
      return api({
        'GET /taxes': (_body, url) => {
          const items =
            url.searchParams.get('class') === 'charge' ? CHARGE : WITHHOLDING;
          const on = url.searchParams.get('on');
          return [
            200,
            {
              items:
                on !== null && on < '2027-01-01'
                  ? items.filter((tx) => tx.id !== 'iva5')
                  : items,
            },
          ];
        },
      });
    }

    it('offers the taxes in force on the fecha de elaboración and asks again when it changes', async () => {
      const calls = datedApi();
      renderEditor({value: draft({lines: [line({quantity: '1'})]})});

      const select = screen.getByLabelText('Impuesto cargo, línea 1');
      await waitFor(() =>
        expect(
          within(select).getByRole('option', {name: 'IVA 19 %'}),
        ).toBeInTheDocument(),
      );
      expect(
        within(select).queryByRole('option', {name: 'IVA 5 %'}),
        'not in force on 03/10/2026',
      ).not.toBeInTheDocument();
      expect(
        calls.calls.some(
          (c) =>
            c.path === '/taxes' &&
            c.url.searchParams.get('on') === '2026-10-03',
        ),
        'the editor asks with the document’s date',
      ).toBe(true);

      const date = screen.getByLabelText('Fecha de elaboración');
      await userEvent.clear(date);
      await userEvent.type(date, '15/01/2027');

      await waitFor(() =>
        expect(
          within(screen.getByLabelText('Impuesto cargo, línea 1')).getByRole(
            'option',
            {name: 'IVA 5 %'},
          ),
        ).toBeInTheDocument(),
      );
    });

    it('keeps showing the tax a line already has, even when it is not in force that day', async () => {
      datedApi();
      renderEditor({
        value: draft({
          lines: [
            line({quantity: '1', unit_price: '1000', charge_tax_id: 'iva5'}),
          ],
        }),
      });

      await waitFor(() =>
        expect(screen.getByLabelText('Impuesto cargo, línea 1')).toHaveValue(
          'iva5',
        ),
      );
      expect(total('Impuestos')).toBe('$ 50,00');
    });
  });

  describe('formas de pago', () => {
    const priced = draft({
      lines: [
        line({quantity: '1', unit_price: '1000', charge_tax_id: 'iva19'}),
      ],
    });

    it('shows the check mark when Total formas de pago equals Total neto', async () => {
      api();
      renderEditor({value: priced});

      await userEvent.click(
        await screen.findByRole('button', {name: 'Agregar forma de pago'}),
      );
      expect(
        screen.getByLabelText('Valor de la forma de pago 1'),
        'the row offers what is left, as a Colombian writes it',
      ).toHaveValue('1.190');
      expect(
        screen.getByText('Coincide con el total neto'),
      ).toBeInTheDocument();

      await userEvent.clear(
        screen.getByLabelText('Valor de la forma de pago 1'),
      );
      await userEvent.type(
        screen.getByLabelText('Valor de la forma de pago 1'),
        '1000',
      );
      expect(
        screen.queryByText('Coincide con el total neto'),
      ).not.toBeInTheDocument();
      expect(
        screen.getByText('Faltan $ 190,00 para el total neto'),
      ).toBeInTheDocument();

      await userEvent.click(
        screen.getByRole('button', {name: 'Agregar forma de pago'}),
      );
      expect(screen.getByLabelText('Valor de la forma de pago 2')).toHaveValue(
        '190',
      );
      expect(
        screen.getByText('Coincide con el total neto'),
      ).toBeInTheDocument();
    });

    it('gives a crédito row its term and due date', async () => {
      api();
      const {latest} = renderEditor({value: priced});

      await userEvent.click(
        await screen.findByRole('button', {name: 'Agregar forma de pago'}),
      );
      await userEvent.selectOptions(
        screen.getByLabelText('Método de pago 1'),
        await screen.findByRole('option', {name: 'Crédito'}),
      );
      expect(screen.getByLabelText('Plazo de la forma de pago 1')).toHaveValue(
        '30',
      );
      expect(screen.getByText('02/11/2026')).toBeInTheDocument();

      await userEvent.selectOptions(
        screen.getByLabelText('Plazo de la forma de pago 1'),
        'custom',
      );
      const date = screen.getByLabelText(
        'Fecha de vencimiento de la forma de pago 1',
      );
      await userEvent.clear(date);
      await userEvent.type(date, '24/12/2026');
      expect(latest()?.payments[0]?.due_date).toBe('2026-12-24');
    });

    it('has none on a quotation', async () => {
      api();
      renderEditor({kind: 'quotation', value: priced});

      await waitFor(() => expect(total('Total neto')).toBe('$ 1.190,00'));
      expect(screen.queryByText('Formas de pago')).not.toBeInTheDocument();
      expect(
        screen.getByRole('combobox', {name: 'Cliente'}),
      ).toBeInTheDocument();
    });
  });

  describe('footer and states', () => {
    it('edits the observaciones and hands new attachments to the page', async () => {
      api();
      const onAttach = vi.fn();
      const onRemoveAttachment = vi.fn();
      const {latest} = renderEditor({
        value: draft({attachments: [{id: 'f1', name: 'factura.pdf'}]}),
        onAttach,
        onRemoveAttachment,
      });

      await userEvent.type(screen.getByLabelText(/^Observaciones/), 'Gracias');
      expect(latest()?.notes).toBe('Gracias');

      const file = new File(['%PDF'], 'soporte.pdf', {type: 'application/pdf'});
      await userEvent.upload(screen.getByLabelText('Adjuntar archivo'), file);
      expect(onAttach).toHaveBeenCalledWith([file]);

      await userEvent.click(
        screen.getByRole('button', {name: 'Quitar factura.pdf'}),
      );
      expect(onRemoveAttachment).toHaveBeenCalledWith('f1');
    });

    it('shows the errors it is given next to their fields', () => {
      api();
      renderEditor({
        value: draft({lines: [line({quantity: '0'})]}),
        errors: {
          'tercero': 'Elige el tercero.',
          'lines.0.quantity': 'La cantidad debe ser mayor que cero.',
          'payments':
            'Total formas de pago ($ 0,00) debe ser igual al total neto ($ 1,00).',
        },
      });

      expect(
        screen.getByRole('combobox', {name: 'Cliente'}),
      ).toHaveAccessibleDescription('Elige el tercero.');
      expect(
        screen.getByLabelText('Cantidad, línea 1'),
      ).toHaveAccessibleDescription('La cantidad debe ser mayor que cero.');
      expect(screen.getByLabelText('Cantidad, línea 1')).toBeInvalid();
      expect(
        screen.getByText(
          'Total formas de pago ($ 0,00) debe ser igual al total neto ($ 1,00).',
        ),
      ).toBeInTheDocument();
    });

    it('hides a line’s message once its cell is edited, until the next check (M6)', async () => {
      api();
      const user = userEvent.setup();
      renderEditor({
        value: draft({lines: [line({quantity: '0'})]}),
        errors: {
          'tercero': 'Elige el tercero.',
          'lines.0.quantity': 'La cantidad debe ser mayor que cero.',
        },
      });

      await user.type(screen.getByLabelText('Cantidad, línea 1'), '2');

      expect(screen.getByLabelText('Cantidad, línea 1')).not.toBeInvalid();
      expect(
        screen.queryByText('La cantidad debe ser mayor que cero.'),
      ).toBeNull();
      expect(
        screen.getByRole('combobox', {name: 'Cliente'}),
        'a message the person has not answered stays',
      ).toHaveAccessibleDescription('Elige el tercero.');
    });

    it('only shows a document that cannot be edited', async () => {
      api();
      renderEditor({
        readOnly: true,
        value: draft({
          tercero: {id: 't1', name: 'Cliente Uno'},
          lines: [PRD_LINE],
        }),
      });

      await waitFor(() => expect(total('Total neto')).toBe('$ 2.070.000,00'));
      expect(screen.getByLabelText('Cantidad, línea 1')).toBeDisabled();
      expect(screen.getByRole('combobox', {name: 'Cliente'})).toBeDisabled();
      expect(
        screen.queryByRole('button', {name: 'Agregar línea'}),
      ).not.toBeInTheDocument();
      expect(
        screen.queryByRole('button', {name: 'Quitar línea 1'}),
      ).not.toBeInTheDocument();
    });
  });
});
