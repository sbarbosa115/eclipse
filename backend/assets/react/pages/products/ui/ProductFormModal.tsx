import {useState} from 'react';
import './products.css';
import {
  AccountPicker,
  createProduct,
  defaultUnit,
  emptyProductForm,
  fieldErrors,
  formFromProduct,
  IncludesTaxCheckbox,
  TaxSelect,
  toProductRequest,
  TypeSelect,
  updateProduct,
  validateProductForm,
  type Product,
  type ProductFormData,
  type ProductFormErrors,
  type ProductOptions,
} from '@/entities/product';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {Button, Field, FormModal, Modal} from '@/shared/ui';

/**
 * The full form of a product or service (§4.3): creating, editing, or — for the accountant, who only reads — viewing
 * with every field disabled.
 */
export function ProductFormModal({
  product,
  options,
  readOnly,
  onSaved,
  onClose,
}: {
  /** null: a new one. */
  product: Product | null;
  options: ProductOptions;
  readOnly: boolean;
  onSaved: (product: Product, created: boolean) => void;
  onClose: () => void;
}) {
  const {t} = useTranslation();
  const creating = product === null;
  const [data, setData] = useState<ProductFormData>(
    product ? formFromProduct(product) : emptyProductForm(),
  );
  const [errors, setErrors] = useState<ProductFormErrors>({});
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const set = <K extends keyof ProductFormData>(
    field: K,
    value: ProductFormData[K],
  ) => setData((d) => ({...d, [field]: value}));

  const changeType = (type: ProductFormData['type']) =>
    setData((d) => ({
      ...d,
      type,
      // A unit nobody chose follows the type: 94 unidad for goods, ZZ servicio for services.
      unit_code:
        d.unit_code === defaultUnit(d.type) ? defaultUnit(type) : d.unit_code,
    }));

  const submit = async () => {
    const found = validateProductForm(data, t);
    setErrors(found);
    if (Object.keys(found).length > 0) return;
    setBusy(true);
    setFailure(null);
    try {
      const request = toProductRequest(data, creating);
      const saved = creating
        ? await createProduct(request)
        : await updateProduct(product.id, request);
      onSaved(saved, creating);
    } catch (error) {
      setBusy(false);
      const server = fieldErrors(error);
      if (Object.keys(server).length > 0) {
        setErrors(server);
        return;
      }
      setFailure(
        error instanceof ApiError && error.status === 403
          ? t('catalog.errors.forbidden')
          : t('common.errors.unexpected'),
      );
    }
  };

  const fields = (
    <fieldset className="product-fields span-2" disabled={readOnly || busy}>
      <div className="form-grid">
        <TypeSelect value={data.type} onChange={changeType} />
        <Field
          label={t('catalog.form.code')}
          hint={t('catalog.form.codeHint')}
          error={errors.code}
        >
          <input
            value={data.code}
            maxLength={40}
            onChange={(e) => set('code', e.target.value)}
          />
        </Field>
        <Field
          label={t('catalog.form.name')}
          error={errors.name}
          className="span-2"
        >
          <input
            value={data.name}
            maxLength={200}
            onChange={(e) => set('name', e.target.value)}
          />
        </Field>
        <Field
          label={t('catalog.form.category')}
          error={errors.category_id}
          optional
        >
          <select
            value={data.category_id}
            onChange={(e) => set('category_id', e.target.value)}
          >
            <option value="">{t('catalog.form.noCategory')}</option>
            {options.categories.map((category) => (
              <option key={category.id} value={category.id}>
                {category.name}
              </option>
            ))}
          </select>
        </Field>
        <Field label={t('catalog.form.unit')} error={errors.unit_code}>
          <select
            value={data.unit_code}
            onChange={(e) => set('unit_code', e.target.value)}
          >
            {options.units.map((unit) => (
              <option key={unit.code} value={unit.code}>
                {`${unit.code} · ${unit.name}`}
              </option>
            ))}
          </select>
        </Field>
        <Field
          label={t('catalog.form.description')}
          error={errors.description}
          className="span-2"
          optional
        >
          <textarea
            rows={3}
            value={data.description}
            maxLength={2000}
            onChange={(e) => set('description', e.target.value)}
          />
        </Field>
        <Field
          label={t('catalog.form.salePrice')}
          hint={t('catalog.form.salePriceHint')}
          error={errors.sale_price}
        >
          <input
            inputMode="decimal"
            value={data.sale_price}
            onChange={(e) => set('sale_price', e.target.value)}
          />
        </Field>
        <IncludesTaxCheckbox
          checked={data.price_includes_tax}
          onChange={(checked) => set('price_includes_tax', checked)}
        />
        <TaxSelect
          label={t('catalog.form.chargeTax')}
          taxes={options.chargeTaxes}
          value={data.charge_tax_id}
          emptyLabel={
            creating
              ? t('catalog.form.companyDefault')
              : t('catalog.form.noTaxOption')
          }
          error={errors.charge_tax_id}
          onChange={(id) => set('charge_tax_id', id)}
        />
        <TaxSelect
          label={t('catalog.form.withholdingTax')}
          taxes={options.withholdingTaxes}
          value={data.withholding_tax_id}
          emptyLabel={
            creating
              ? t('catalog.form.companyDefault')
              : t('catalog.form.noTaxOption')
          }
          error={errors.withholding_tax_id}
          onChange={(id) => set('withholding_tax_id', id)}
        />
        <AccountPicker
          label={t('catalog.form.revenueAccount')}
          hint={t('catalog.form.revenueAccountHint')}
          value={data.revenue_account}
          error={errors.revenue_account_id}
          onChange={(choice) => set('revenue_account', choice)}
        />
        <AccountPicker
          label={t('catalog.form.expenseAccount')}
          hint={t('catalog.form.expenseAccountHint')}
          value={data.expense_account}
          error={errors.expense_account_id}
          onChange={(choice) => set('expense_account', choice)}
        />
      </div>
    </fieldset>
  );

  if (readOnly) {
    return (
      <Modal title={t('catalog.form.viewTitle')} onClose={onClose}>
        {fields}
        <div className="form-actions">
          <Button variant="ghost" onClick={onClose}>
            {t('common.close')}
          </Button>
        </div>
      </Modal>
    );
  }
  return (
    <FormModal
      title={t(
        creating ? 'catalog.form.createTitle' : 'catalog.form.editTitle',
      )}
      onClose={onClose}
      onSubmit={submit}
      busy={busy}
      error={failure}
    >
      {fields}
    </FormModal>
  );
}
