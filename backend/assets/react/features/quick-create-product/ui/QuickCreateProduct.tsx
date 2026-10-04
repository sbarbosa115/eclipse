import {useState} from 'react';
import {
  emptyQuickProduct,
  fieldErrors,
  IncludesTaxCheckbox,
  quickCreateProduct,
  TaxSelect,
  toQuickRequest,
  TypeSelect,
  useTaxOptions,
  validateQuickProduct,
  type Product,
  type ProductFormErrors,
  type QuickProductData,
} from '@/entities/product';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {Field, FormModal, MoneyInput} from '@/shared/ui';

/**
 * The modal a document line opens to create a product or service without leaving the document (§4.3): type, código,
 * nombre, price and its taxes (the company's defaults when left alone). `onCreated` receives the new product, ready to
 * become the line.
 */
export function QuickCreateProduct({
  onCreated,
  onClose,
  initialName = '',
}: {
  onCreated: (product: Product) => void;
  onClose: () => void;
  /** What the person had typed in the line's search. */
  initialName?: string;
}) {
  const {t} = useTranslation();
  const taxes = useTaxOptions();
  const [data, setData] = useState<QuickProductData>(
    emptyQuickProduct(initialName),
  );
  const [errors, setErrors] = useState<ProductFormErrors>({});
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const set = <K extends keyof QuickProductData>(
    field: K,
    value: QuickProductData[K],
  ) => setData((d) => ({...d, [field]: value}));

  const submit = async () => {
    const found = validateQuickProduct(data, t);
    setErrors(found);
    if (Object.keys(found).length > 0) return;
    setBusy(true);
    setFailure(null);
    try {
      onCreated(await quickCreateProduct(toQuickRequest(data)));
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

  return (
    <FormModal
      title={t('catalog.quick.title')}
      onClose={onClose}
      onSubmit={submit}
      busy={busy}
      error={failure}
      submitLabel={t('catalog.quick.submit')}
    >
      <TypeSelect value={data.type} onChange={(type) => set('type', type)} />
      <Field label={t('catalog.form.code')} error={errors.code}>
        <input
          value={data.code}
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
          onChange={(e) => set('name', e.target.value)}
        />
      </Field>
      <Field
        label={t('catalog.form.salePrice')}
        hint={t('catalog.form.salePriceHint')}
        error={errors.sale_price}
      >
        <MoneyInput
          places={4}
          value={data.sale_price}
          onChange={(price) => set('sale_price', price)}
        />
      </Field>
      <IncludesTaxCheckbox
        checked={data.price_includes_tax}
        onChange={(checked) => set('price_includes_tax', checked)}
      />
      <TaxSelect
        label={t('catalog.form.chargeTax')}
        taxes={taxes.chargeTaxes}
        value={data.charge_tax_id}
        emptyLabel={t('catalog.form.companyDefault')}
        error={errors.charge_tax_id}
        onChange={(id) => set('charge_tax_id', id)}
      />
      <TaxSelect
        label={t('catalog.form.withholdingTax')}
        taxes={taxes.withholdingTaxes}
        value={data.withholding_tax_id}
        emptyLabel={t('catalog.form.companyDefault')}
        error={errors.withholding_tax_id}
        onChange={(id) => set('withholding_tax_id', id)}
      />
      <p className="muted small span-2">{t('catalog.quick.taxesHint')}</p>
    </FormModal>
  );
}
