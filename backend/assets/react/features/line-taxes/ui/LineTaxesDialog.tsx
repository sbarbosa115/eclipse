import {useState} from 'react';
import {setProductTaxes, TaxSelect, type Tax} from '@/entities/product';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {formatMoney} from '@/shared/lib';
import {FormModal} from '@/shared/ui';

/** The kinds of retención a line takes, at most one of each, in the order they are sent (§4.2, Q-G). */
export const WITHHOLDING_KINDS = ['retefuente', 'reteiva', 'reteica'] as const;
export type WithholdingKind = (typeof WITHHOLDING_KINDS)[number];

/** A line's taxes: its impuesto cargo (null is "Sin impuesto") and its retenciones (tax ids, one per kind). */
export interface LineTaxes {
  charge_tax_id: string | null;
  withholdings: string[];
}

/** The line's retención of each kind, from its ids; an id the dialog does not know is dropped. */
function byKind(
  ids: ReadonlyArray<string>,
  taxes: ReadonlyArray<Tax>,
): Record<WithholdingKind, string> {
  const chosen: Record<WithholdingKind, string> = {
    retefuente: '',
    reteiva: '',
    reteica: '',
  };
  for (const id of ids) {
    const kind = taxes.find((tax) => tax.id === id)?.kind;
    if (kind && kind in chosen && chosen[kind as WithholdingKind] === '') {
      chosen[kind as WithholdingKind] = id;
    }
  }
  return chosen;
}

/**
 * The per-line tax dialog (§4.6, observations §4.2): shows the line's subtotal, changes its impuesto cargo and its
 * retenciones (one select per kind: Retefuente, ReteIVA, ReteICA), and can make them the product's from now on
 * (PUT /products/{id}/taxes; a product keeps one default retención, the Retefuente one, else the first chosen). When
 * that save fails the line keeps its taxes and the dialog stays open, so the person never believes the product
 * changed when it did not.
 */
export function LineTaxesDialog({
  lineNumber,
  subtotal,
  chargeTaxId,
  withholdings,
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
  /** The line's retenciones (tax ids). */
  withholdings: string[];
  chargeTaxes: Tax[];
  /** The retenciones offered, of every kind (each select shows its own), including those the line already has. */
  withholdingTaxes: Tax[];
  /** The line's product; null on a line by expense account (nothing to update). */
  productId: string | null;
  onApply: (taxes: LineTaxes) => void;
  onClose: () => void;
}) {
  const {t} = useTranslation();
  const [charge, setCharge] = useState(chargeTaxId ?? '');
  const [chosen, setChosen] = useState(() =>
    byKind(withholdings, withholdingTaxes),
  );
  const [toProduct, setToProduct] = useState(false);
  const [busy, setBusy] = useState(false);
  const [failure, setFailure] = useState<string | null>(null);

  const submit = async () => {
    const ids = WITHHOLDING_KINDS.map((kind) => chosen[kind]).filter(
      (id) => id !== '',
    );
    const taxes: LineTaxes = {charge_tax_id: charge || null, withholdings: ids};
    if (toProduct && productId) {
      setBusy(true);
      setFailure(null);
      try {
        await setProductTaxes(productId, {
          charge_tax_id: taxes.charge_tax_id,
          withholding_tax_id: ids[0] ?? null,
        });
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
      <p className="span-2 small muted">
        {t('documentEditor.lineTaxes.withholdingsHint')}
      </p>
      {WITHHOLDING_KINDS.map((kind) => (
        <TaxSelect
          key={kind}
          label={t(`documentEditor.lineTaxes.kinds.${kind}`)}
          taxes={withholdingTaxes.filter((tax) => tax.kind === kind)}
          value={chosen[kind]}
          emptyLabel={t('documentEditor.lineTaxes.noWithholding')}
          onChange={(id) => setChosen((c) => ({...c, [kind]: id}))}
        />
      ))}
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
