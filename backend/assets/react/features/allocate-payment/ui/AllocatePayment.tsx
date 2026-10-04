import {useId} from 'react';
import {useTranslation} from '@/shared/i18n';
import {formatDate, formatMoney} from '@/shared/lib';
import {ActionButton, Actions, DataTable} from '@/shared/ui';
import {parseAmount, summarize, type OpenItem} from '../model/allocation';
import './allocatePayment.css';

export interface AllocatePaymentProps {
  /** The tercero's open receivables or payables, in the order to show them (oldest due first). */
  items: ReadonlyArray<OpenItem>;
  /** What the person typed per item id ('' or missing: nothing). */
  amounts: Readonly<Record<string, string>>;
  onChange: (amounts: Record<string, string>) => void;
  /** The amount received or paid, as typed: the difference is counted against it. */
  total: string;
  /** Messages from the server, by item id. */
  errors?: Readonly<Record<string, string | undefined>>;
  disabled?: boolean;
  /** The i18n namespace the words are read from (document, balance, errors.exceeds…): the cash receipt's by default. */
  labels?: string;
}

/**
 * The open items of one tercero with an amount to apply to each (§4.9, §4.11): a "pay in full" shortcut per row and
 * the running difference against the amount received, until it is zero. Generic: the recibo de caja passes
 * receivables, the recibo de pago payables; neither is named here.
 */
export function AllocatePayment({
  items,
  amounts,
  onChange,
  total,
  errors = {},
  disabled = false,
  labels = 'cashReceipt.allocate',
}: AllocatePaymentProps) {
  const {t} = useTranslation();
  const baseId = useId();
  const summary = summarize(total, items, amounts);
  const set = (id: string, value: string) =>
    onChange({...amounts, [id]: value});
  const difference = summary.difference;
  const statusText = () => {
    if (summary.balanced) return `✓ ${t(`${labels}.balanced`)}`;
    if (difference.startsWith('-')) {
      return t(`${labels}.over`, {
        amount: formatMoney(difference.slice(1)),
      });
    }
    if (difference !== '0.00') {
      return t(`${labels}.missing`, {
        amount: formatMoney(difference),
      });
    }
    return t(`${labels}.pending`);
  };

  return (
    <div className="allocate-payment">
      <DataTable
        columns={[
          t(`${labels}.document`),
          t(`${labels}.issueDate`),
          t(`${labels}.dueDate`),
          t(`${labels}.amount`),
          t(`${labels}.balance`),
          t(`${labels}.toApply`),
        ]}
        rows={items}
        renderRow={(item) => {
          const rowError = summary.errors[item.id];
          const message =
            errors[item.id] ??
            (rowError ? t(`${labels}.errors.${rowError}`) : null);
          const errorId = `${baseId}-${item.id}-error`;
          return (
            <tr key={item.id}>
              <td>{item.document}</td>
              <td>{formatDate(item.issueDate)}</td>
              <td>{formatDate(item.dueDate)}</td>
              <td className="num">{formatMoney(item.amount)}</td>
              <td className="num">{formatMoney(item.balance)}</td>
              <td className="allocate-payment-input">
                <input
                  type="text"
                  inputMode="decimal"
                  value={amounts[item.id] ?? ''}
                  disabled={disabled}
                  aria-label={t(`${labels}.toApplyOf`, {
                    document: item.document,
                  })}
                  aria-invalid={message ? true : undefined}
                  aria-describedby={message ? errorId : undefined}
                  onChange={(event) => set(item.id, event.target.value)}
                />
                {message && (
                  <span id={errorId} className="field-error">
                    {message}
                  </span>
                )}
              </td>
              <Actions>
                <ActionButton
                  action="setup"
                  disabled={disabled}
                  aria-label={t(`${labels}.payInFullOf`, {
                    document: item.document,
                  })}
                  onClick={() => set(item.id, item.balance)}
                >
                  {t(`${labels}.payInFull`)}
                </ActionButton>
              </Actions>
            </tr>
          );
        }}
      />
      <dl className="allocate-payment-summary">
        <div>
          <dt>{t(`${labels}.received`)}</dt>
          <dd>{formatMoney(parseAmount(total) ?? '0')}</dd>
        </div>
        <div>
          <dt>{t(`${labels}.allocated`)}</dt>
          <dd>{formatMoney(summary.allocated)}</dd>
        </div>
        <div>
          <dt>{t(`${labels}.difference`)}</dt>
          <dd>{formatMoney(difference)}</dd>
        </div>
      </dl>
      <p
        role="status"
        className={`allocate-payment-status ${summary.balanced ? 'is-balanced' : 'is-open'}`}
      >
        {statusText()}
      </p>
    </div>
  );
}
