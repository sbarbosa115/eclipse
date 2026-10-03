import {useId} from 'react';
import {useTranslation} from '@/shared/i18n';
import {formatMoney} from '@/shared/lib';
import type {TotalsPreview} from '../model/totals';

/** Total bruto down to Total neto (§4.6), as a preview of what the server computes. */
export function TotalsPanel({totals}: {totals: TotalsPreview}) {
  const {t} = useTranslation();
  const titleId = useId();
  const rows: [string, string][] = [
    ['gross', totals.gross],
    ['discounts', totals.discounts],
    ['subtotal', totals.subtotal],
    ['taxes', totals.taxes],
    ['withholdings', totals.withholdings],
  ];
  return (
    <section className="card doc-totals" aria-labelledby={titleId}>
      <h2 id={titleId} className="card-title">
        {t('documentEditor.totals.title')}
      </h2>
      <dl className="definitions">
        {rows.map(([key, amount]) => (
          <div key={key}>
            <dt>{t(`documentEditor.totals.${key}`)}</dt>
            <dd className="doc-amount">{formatMoney(amount)}</dd>
          </div>
        ))}
        <div className="doc-totals-net">
          <dt>{t('documentEditor.totals.net')}</dt>
          <dd className="doc-amount">{formatMoney(totals.net)}</dd>
        </div>
      </dl>
      <p className="small muted">{t('documentEditor.totals.preview')}</p>
    </section>
  );
}
