import {translator} from '@/shared/i18n/i18n';
import {
  defaultUnit,
  emptyProductForm,
  emptyQuickProduct,
  formFromProduct,
  toProductRequest,
  toQuickRequest,
  trimDecimals,
  validateProductForm,
  validateQuickProduct,
} from './productForm';
import type {Product} from '../api/productApi';

const t = translator();

const product: Product = {
  id: 'p1',
  type: 'servicio',
  code: 'S-1',
  name: 'Asesoría',
  description: null,
  category_id: null,
  category_name: null,
  unit_code: 'HUR',
  sale_price: '119000.0000',
  price_includes_tax: true,
  unit_price_net_of_tax: '100000.0000',
  charge_tax_id: 'iva',
  withholding_tax_id: null,
  revenue_account_id: 'acc',
  expense_account_id: null,
  active: true,
  revenue_account_label: '413595 · Venta de otros',
  expense_account_label: null,
};

describe('the product form', () => {
  it('asks for código, nombre and a price, and checks the price’s shape', () => {
    const errors = validateProductForm(emptyProductForm(), t);

    expect(errors.code).toBe('Este campo es obligatorio.');
    expect(errors.name).toBe('Este campo es obligatorio.');
    expect(errors.sale_price).toBe('Este campo es obligatorio.');
    expect(
      validateProductForm(
        {...emptyProductForm(), code: 'A', name: 'A', sale_price: '12,5'},
        t,
      ).sale_price,
      'a comma is not a decimal point',
    ).toBe('Escribe el precio en pesos, con máximo cuatro decimales.');
    expect(
      validateProductForm(
        {...emptyProductForm(), code: 'A', name: 'A', sale_price: '49999.9'},
        t,
      ),
    ).toEqual({});
  });

  it('refuses an account typed but not chosen from the list', () => {
    const errors = validateProductForm(
      {
        ...emptyProductForm(),
        code: 'A',
        name: 'A',
        sale_price: '1',
        revenue_account: {id: null, text: '4135'},
      },
      t,
    );

    expect(errors.revenue_account_id).toBe(
      'Elige una cuenta de la lista o borra el campo.',
    );
  });

  it('leaves the taxes out on create, so the company’s defaults apply, and writes none on update', () => {
    const form = {
      ...emptyProductForm(),
      code: ' A ',
      name: 'A',
      sale_price: '1',
    };

    const created = toProductRequest(form, true);
    expect(created).not.toHaveProperty('charge_tax_id');
    expect(created).not.toHaveProperty('withholding_tax_id');
    expect(created.code, 'text is trimmed').toBe('A');

    const updated = toProductRequest(form, false);
    expect(updated.charge_tax_id).toBeNull();
    expect(updated.withholding_tax_id).toBeNull();
    expect(
      toProductRequest({...form, charge_tax_id: 'iva'}, true).charge_tax_id,
    ).toBe('iva');
  });

  it('shows a saved product as the person would type it', () => {
    const form = formFromProduct(product);

    expect(form.sale_price).toBe('119000');
    expect(form.revenue_account).toEqual({
      id: 'acc',
      text: '413595 · Venta de otros',
    });
    expect(form.expense_account).toEqual({id: null, text: ''});
    expect(trimDecimals('42016.8067')).toBe('42016.8067');
    expect(trimDecimals('10000.5000')).toBe('10000.5');
    expect(trimDecimals('10000')).toBe('10000');
  });

  it('starts goods at 94 unidad and services at ZZ servicio', () => {
    expect(defaultUnit('producto')).toBe('94');
    expect(defaultUnit('servicio')).toBe('ZZ');
  });
});

describe('the quick-create form', () => {
  it('needs only what a line needs', () => {
    expect(
      Object.keys(validateQuickProduct(emptyQuickProduct(), t)).sort(),
    ).toEqual(['code', 'name', 'sale_price']);
    expect(
      validateQuickProduct(
        {...emptyQuickProduct('Café'), code: 'C', sale_price: '5000'},
        t,
      ),
    ).toEqual({});
  });

  it('sends the taxes only when they were chosen', () => {
    const base = {...emptyQuickProduct('Café'), code: 'C', sale_price: '5000'};

    expect(toQuickRequest(base)).toEqual({
      type: 'producto',
      code: 'C',
      name: 'Café',
      sale_price: '5000',
      price_includes_tax: false,
    });
    expect(toQuickRequest({...base, charge_tax_id: 'iva'}).charge_tax_id).toBe(
      'iva',
    );
  });
});
