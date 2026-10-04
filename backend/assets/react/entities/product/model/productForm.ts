import type {Translate} from '@/shared/i18n';
import type {
  Product,
  ProductRequest,
  ProductType,
  QuickProductRequest,
} from '../api/productApi';

/**
 * An account chosen in a picker: its id once one is picked from the list, and the text the person sees. The same
 * shape as `features/pick-account`'s choice, whose picker the product form uses (an entity cannot import a feature).
 */
export interface AccountChoice {
  id: string | null;
  text: string;
}

/** What the full form edits. Selects keep '' for "none"/"the default". */
export interface ProductFormData {
  type: ProductType;
  code: string;
  name: string;
  description: string;
  category_id: string;
  unit_code: string;
  sale_price: string;
  price_includes_tax: boolean;
  charge_tax_id: string;
  withholding_tax_id: string;
  revenue_account: AccountChoice;
  expense_account: AccountChoice;
}

export type ProductFormErrors = Partial<Record<string, string>>;

const NO_ACCOUNT: AccountChoice = {id: null, text: ''};
/** Pesos with up to four decimals, like the API's unit prices. */
export const PRICE = /^\d{1,14}(\.\d{1,4})?$/;

/** The unit a type starts with: 94 unidad for goods, ZZ "servicio" for services (DIAN). */
export function defaultUnit(type: ProductType): string {
  return type === 'servicio' ? 'ZZ' : '94';
}

/** "10000.0000" → "10000", "42016.8067" stays: a price as a person writes it. */
export function trimDecimals(amount: string): string {
  return amount.includes('.')
    ? amount.replace(/0+$/, '').replace(/\.$/, '')
    : amount;
}

export function emptyProductForm(): ProductFormData {
  return {
    type: 'producto',
    code: '',
    name: '',
    description: '',
    category_id: '',
    unit_code: '94',
    sale_price: '',
    price_includes_tax: false,
    charge_tax_id: '',
    withholding_tax_id: '',
    revenue_account: NO_ACCOUNT,
    expense_account: NO_ACCOUNT,
  };
}

export function formFromProduct(product: Product): ProductFormData {
  return {
    type: product.type === 'servicio' ? 'servicio' : 'producto',
    code: product.code,
    name: product.name,
    description: product.description ?? '',
    category_id: product.category_id ?? '',
    unit_code: product.unit_code,
    // The list price as it reads: "10000" rather than "10000.0000".
    sale_price: trimDecimals(product.sale_price),
    price_includes_tax: product.price_includes_tax,
    charge_tax_id: product.charge_tax_id ?? '',
    withholding_tax_id: product.withholding_tax_id ?? '',
    revenue_account: {
      id: product.revenue_account_id ?? null,
      text: product.revenue_account_label ?? '',
    },
    expense_account: {
      id: product.expense_account_id ?? null,
      text: product.expense_account_label ?? '',
    },
  };
}

/** What is wrong before the form is sent; the server checks the rest (a taken código, an inactive tax). */
export function validateProductForm(
  data: ProductFormData,
  t: Translate,
): ProductFormErrors {
  const errors: ProductFormErrors = {};
  if (data.code.trim() === '') errors.code = t('catalog.validation.required');
  if (data.name.trim() === '') errors.name = t('catalog.validation.required');
  const price = data.sale_price.trim();
  if (price === '') errors.sale_price = t('catalog.validation.required');
  else if (!PRICE.test(price))
    errors.sale_price = t('catalog.validation.price');
  for (const field of ['revenue_account', 'expense_account'] as const) {
    const choice = data[field];
    if (choice.text.trim() !== '' && choice.id === null) {
      errors[`${field}_id`] = t('catalog.form.accountMissing');
    }
  }
  return errors;
}

const orNull = (value: string): string | null => (value === '' ? null : value);

/**
 * The request for the form. On create a tax left empty is omitted, so the company's default applies; on update an
 * empty tax is written as none.
 */
export function toProductRequest(
  data: ProductFormData,
  creating: boolean,
): ProductRequest {
  const request: ProductRequest = {
    type: data.type,
    code: data.code.trim(),
    name: data.name.trim(),
    description: orNull(data.description.trim()),
    category_id: orNull(data.category_id),
    unit_code: data.unit_code,
    sale_price: data.sale_price.trim(),
    price_includes_tax: data.price_includes_tax,
    revenue_account_id: data.revenue_account.id,
    expense_account_id: data.expense_account.id,
  };
  if (creating) {
    if (data.charge_tax_id !== '') request.charge_tax_id = data.charge_tax_id;
    if (data.withholding_tax_id !== '') {
      request.withholding_tax_id = data.withholding_tax_id;
    }
  } else {
    request.charge_tax_id = orNull(data.charge_tax_id);
    request.withholding_tax_id = orNull(data.withholding_tax_id);
  }
  return request;
}

/** What the quick-create modal edits. */
export interface QuickProductData {
  type: ProductType;
  code: string;
  name: string;
  sale_price: string;
  price_includes_tax: boolean;
  charge_tax_id: string;
  withholding_tax_id: string;
}

export function emptyQuickProduct(name = ''): QuickProductData {
  return {
    type: 'producto',
    code: '',
    name,
    sale_price: '',
    price_includes_tax: false,
    charge_tax_id: '',
    withholding_tax_id: '',
  };
}

export function validateQuickProduct(
  data: QuickProductData,
  t: Translate,
): ProductFormErrors {
  const errors: ProductFormErrors = {};
  if (data.code.trim() === '') errors.code = t('catalog.validation.required');
  if (data.name.trim() === '') errors.name = t('catalog.validation.required');
  const price = data.sale_price.trim();
  if (price === '') errors.sale_price = t('catalog.validation.required');
  else if (!PRICE.test(price))
    errors.sale_price = t('catalog.validation.price');
  return errors;
}

/** Taxes left empty are the company's defaults. */
export function toQuickRequest(data: QuickProductData): QuickProductRequest {
  const request: QuickProductRequest = {
    type: data.type,
    code: data.code.trim(),
    name: data.name.trim(),
    sale_price: data.sale_price.trim(),
    price_includes_tax: data.price_includes_tax,
  };
  if (data.charge_tax_id !== '') request.charge_tax_id = data.charge_tax_id;
  if (data.withholding_tax_id !== '') {
    request.withholding_tax_id = data.withholding_tax_id;
  }
  return request;
}
