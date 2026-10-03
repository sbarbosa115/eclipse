import {apiDelete, apiGet, apiPost, apiPut, type Schema} from '@/shared/api';

export type Product = Schema<'ProductOutput'>;
export type ProductCategory = Schema<'CategoryOutput'>;
export type Unit = Schema<'UnitOutput'>;
export type Tax = Schema<'TaxOutput'>;
export type Account = Schema<'AccountOutput'>;

export type ProductType = 'producto' | 'servicio';

export interface ProductPage {
  items: Product[];
  total: number;
  page: number;
  per_page: number;
}

export interface ProductFilters {
  q?: string;
  type?: string;
  /** '1' only the active, '0' only the inactive, '' all. */
  active?: string;
  page?: number;
  per_page?: number;
}

/** The body of POST /products and PUT /products/{id}; a missing tax is the company's default on create. */
export interface ProductRequest {
  type: ProductType;
  code: string;
  name: string;
  description?: string | null;
  category_id?: string | null;
  unit_code?: string | null;
  sale_price: string;
  price_includes_tax: boolean;
  charge_tax_id?: string | null;
  withholding_tax_id?: string | null;
  revenue_account_id?: string | null;
  expense_account_id?: string | null;
}

/** The body of POST /products/quick. */
export interface QuickProductRequest {
  type: ProductType;
  code: string;
  name: string;
  sale_price: string;
  price_includes_tax: boolean;
  charge_tax_id?: string | null;
  withholding_tax_id?: string | null;
}

export function listProducts(filters: ProductFilters): Promise<ProductPage> {
  const query = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== '') query.set(key, String(value));
  }
  const text = query.toString();
  return apiGet<ProductPage>(`/products${text ? `?${text}` : ''}`);
}

export function getProduct(id: string): Promise<Product> {
  return apiGet<Product>(`/products/${id}`);
}

export function createProduct(body: ProductRequest): Promise<Product> {
  return apiPost<Product>('/products', body);
}

export function quickCreateProduct(
  body: QuickProductRequest,
): Promise<Product> {
  return apiPost<Product>('/products/quick', body);
}

export function updateProduct(
  id: string,
  body: ProductRequest,
): Promise<Product> {
  return apiPut<Product>(`/products/${id}`, body);
}

/** "Use these taxes on this product from now on": a null is no tax. */
export function setProductTaxes(
  id: string,
  taxes: {charge_tax_id: string | null; withholding_tax_id: string | null},
): Promise<Product> {
  return apiPut<Product>(`/products/${id}/taxes`, taxes);
}

export function deactivateProduct(id: string): Promise<Product> {
  return apiPost<Product>(`/products/${id}/deactivate`, {});
}

export function reactivateProduct(id: string): Promise<Product> {
  return apiPost<Product>(`/products/${id}/reactivate`, {});
}

export function deleteProduct(id: string): Promise<null> {
  return apiDelete(`/products/${id}`);
}

export async function listUnits(): Promise<Unit[]> {
  return (await apiGet<{items: Unit[]}>('/products/units')).items;
}

export async function listCategories(): Promise<ProductCategory[]> {
  return (await apiGet<{items: ProductCategory[]}>('/product-categories'))
    .items;
}

export function createCategory(name: string): Promise<ProductCategory> {
  return apiPost<ProductCategory>('/product-categories', {name});
}

export function renameCategory(
  id: string,
  name: string,
): Promise<ProductCategory> {
  return apiPut<ProductCategory>(`/product-categories/${id}`, {name});
}

export async function listTaxes(
  taxClass: 'charge' | 'withholding',
): Promise<Tax[]> {
  return (await apiGet<{items: Tax[]}>(`/taxes?class=${taxClass}`)).items;
}

export async function searchAccounts(query: string): Promise<Account[]> {
  const q = encodeURIComponent(query);
  return (await apiGet<{items: Account[]}>(`/accounts/search?q=${q}`)).items;
}
