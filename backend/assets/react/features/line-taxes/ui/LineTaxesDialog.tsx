import {useState} from 'react';
import {setProductTaxes, TaxSelect, type Tax} from '@/entities/product';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {formatMoney} from '@/shared/lib';
import {FormModal} from '@/shared/ui';

/** A line's two taxes; null is "Sin impuesto". */
export interface LineTaxes {
  charge_tax_id: string | null;
  withholding_tax_id: string | null;
}

/**
 * The per-line tax dialog (§4.6): shows the line's subtotal, changes its impuesto cargo and impuesto retención, and
 * can make them the product's from now on (PUT /products/{id}/taxes). When that save fails the line keeps its taxes
 * and the dialog stays open, so the person never believes the product changed when it did not.
 */
export function LineTaxesDialog({
  lineNumber,
  subtotal,
  chargeTaxId,
  withholdingTaxId,
  chargeTaxes,
  withholdingTaxes,
  productId,
  onApply,
  onClose,
}: {
  lineNumber: number;
  /** The line's subtotal (base after discount), a decimal string. */
  subtotal: string;
  chargeTaxId: string | null;
  withholdingTaxId: string | null;
  chargeTaxes: Tax[];
  withholdingTaxes: Tax[];
  /** The line's product; null on a line by expense account (nothing to update). */
  productId: string | null;
  onApply: (taxes: LineTaxes) => void;
  onClose: () => void;
}) {
  const {t} = useTranslation();
  const [charge, setCharge] = useState(chargeTaxId ?? '');
  const [withholding, setWithholding] = useState(withholdingTaxId ?? '');
  const [toProduct, setToProduct] = useState(false);
  const [busy, setBusy] = useState(false);
  const [failure, setFailure] = useState<string | null>(null);

  const submit = async () => {
    const taxes: LineTaxes = {
      charge_tax_id: charge || null,
      withholding_tax_id: withholding || null,
    };
    if (toProduct && productId) {
      setBusy(true);
      setFailure(null);
      try {
        await setProductTaxes(productId, taxes);
      } catch (error) {
        setBusy(false);
        setFailure(
          error instanceof ApiError && error.status === 403
            ? t('documentEditor.lineTaxes.forbidden')
            : t('documentEditor.lineTaxes.failed'),
        );
        return;
      }
    }
    onApply(taxes);
  };

  return (
    <FormModal
      title={t('documentEditor.lineTaxes.title', {n: lineNumber})}
      onClose={onClose}
      onSubmit={submit}
      busy={busy}
      error={failure}
      submitLabel={t('documentEditor.lineTaxes.apply')}
    >
      <dl className="definitions span-2">
        <div>
          <dt>{t('documentEditor.lineTaxes.subtotal')}</dt>
          <dd className="nowrap">{formatMoney(subtotal)}</dd>
        </div>
      </dl>
      <TaxSelect
        label={t('documentEditor.lineTaxes.charge')}
        taxes={chargeTaxes}
        value={charge}
        emptyLabel={t('documentEditor.lines.noTax')}
        onChange={setCharge}
      />
      <TaxSelect
        label={t('documentEditor.lineTaxes.withholding')}
        taxes={withholdingTaxes}
        value={withholding}
        emptyLabel={t('documentEditor.lines.noTax')}
        onChange={setWithholding}
      />
      {productId && (
        <div className="field span-2">
          <label className="checkbox">
            <input
              type="checkbox"
              checked={toProduct}
              onChange={(e) => setToProduct(e.target.checked)}
            />
            <span>{t('documentEditor.lineTaxes.applyToProduct')}</span>
          </label>
          <span className="admin-field-hint">
            {t('documentEditor.lineTaxes.applyToProductHint')}
          </span>
        </div>
      )}
    </FormModal>
  );
}
