import {useId} from 'react';
import {useTranslation} from '@/shared/i18n';
import {formatDate, formatMoney} from '@/shared/lib';
import {Actions, Button, DataTable, Icon, IconButton} from '@/shared/ui';
import type {PaymentMethod} from '../api/documentEditorApi';
import {paymentBalance} from '../model/draft';
import type {CreditTerm, DraftPayment, EditorErrors} from '../model/types';
import {CellError, invalidProps} from './CellError';

const TERMS: CreditTerm[] = ['today', '15', '30', '60', 'custom'];

/**
 * Formas de pago (§4.6): método, valor and, on a crédito row, its term and due date. Total formas de pago is compared
 * with Total neto: a check mark when they match, what is missing or over when they do not.
 */
export function PaymentsSection({
  payments,
  methods,
  net,
  errors,
  readOnly,
  onAdd,
  onUpdate,
  onRemove,
}: {
  payments: DraftPayment[];
  methods: PaymentMethod[];
  net: string;
  errors: EditorErrors;
  readOnly: boolean;
  onAdd: () => void;
  onUpdate: (index: number, change: Partial<DraftPayment>) => void;
  onRemove: (index: number) => void;
}) {
  const {t} = useTranslation();
  const baseId = useId();
  const balance = paymentBalance(payments, net);
  const kindOf = (id: string | null) =>
    methods.find((method) => method.id === id)?.kind;

  return (
    <section className="doc-payments" aria-labelledby={`${baseId}-title`}>
      <h2 id={`${baseId}-title`} className="doc-section-title">
        {t('documentEditor.payments.title')}
      </h2>
      {payments.length === 0 ? (
        <p className="small muted">{t('documentEditor.payments.empty')}</p>
      ) : (
        <DataTable
          columns={[
            t('documentEditor.payments.method'),
            t('documentEditor.payments.amount'),
            t('documentEditor.payments.due'),
          ]}
          rows={payments}
          actions={!readOnly}
          renderRow={(payment, index) => {
            const n = index + 1;
            const at = `payments.${index}`;
            const ids = {
              method: `${baseId}-${payment.key}-method`,
              amount: `${baseId}-${payment.key}-amount`,
              due: `${baseId}-${payment.key}-due`,
            };
            const credit = kindOf(payment.payment_method_id) === 'credit';
            return (
              <tr key={payment.key}>
                <td>
                  <select
                    aria-label={t('documentEditor.payments.methodOf', {n})}
                    value={payment.payment_method_id ?? ''}
                    onChange={(e) =>
                      onUpdate(index, {
                        payment_method_id: e.target.value || null,
                      })
                    }
                    {...invalidProps(
                      ids.method,
                      errors[`${at}.payment_method_id`],
                    )}
                  >
                    <option value="">
                      {t('documentEditor.payments.chooseMethod')}
                    </option>
                    {methods.map((method) => (
                      <option key={method.id} value={method.id}>
                        {method.name}
                      </option>
                    ))}
                  </select>
                  <CellError
                    id={ids.method}
                    message={errors[`${at}.payment_method_id`]}
                  />
                </td>
                <td>
                  <input
                    className="doc-line-number"
                    inputMode="decimal"
                    aria-label={t('documentEditor.payments.amountOf', {n})}
                    value={payment.amount}
                    onChange={(e) => onUpdate(index, {amount: e.target.value})}
                    {...invalidProps(ids.amount, errors[`${at}.amount`])}
                  />
                  <CellError id={ids.amount} message={errors[`${at}.amount`]} />
                </td>
                <td>
                  {credit ? (
                    <div className="doc-due">
                      <select
                        aria-label={t('documentEditor.payments.termOf', {n})}
                        value={payment.term}
                        onChange={(e) =>
                          onUpdate(index, {
                            term: e.target.value as CreditTerm,
                          })
                        }
                      >
                        {TERMS.map((term) => (
                          <option key={term} value={term}>
                            {t(`documentEditor.payments.terms.${term}`)}
                          </option>
                        ))}
                      </select>
                      {payment.term === 'custom' ? (
                        <input
                          type="date"
                          aria-label={t('documentEditor.payments.dueDateOf', {
                            n,
                          })}
                          value={payment.due_date ?? ''}
                          onChange={(e) =>
                            onUpdate(index, {due_date: e.target.value || null})
                          }
                          {...invalidProps(ids.due, errors[`${at}.due_date`])}
                        />
                      ) : (
                        <span className="nowrap">
                          {formatDate(payment.due_date)}
                        </span>
                      )}
                      <CellError
                        id={ids.due}
                        message={errors[`${at}.due_date`]}
                      />
                    </div>
                  ) : (
                    <span className="muted">
                      {t('documentEditor.payments.notApplicable')}
                    </span>
                  )}
                </td>
                {!readOnly && (
                  <Actions>
                    <IconButton
                      icon="trash"
                      label={t('documentEditor.payments.remove', {n})}
                      onClick={() => onRemove(index)}
                    />
                  </Actions>
                )}
              </tr>
            );
          }}
        />
      )}
      <div className="doc-payments-footer">
        {!readOnly && (
          <Button variant="secondary" size="sm" onClick={onAdd}>
            {t('documentEditor.payments.add')}
          </Button>
        )}
        {payments.length > 0 && (
          <p className="doc-payments-total">
            <span>{t('documentEditor.payments.total')}</span>{' '}
            <strong className="doc-amount">{formatMoney(balance.total)}</strong>{' '}
            {balance.matches ? (
              <span className="doc-match is-ok">
                <Icon name="check" size={16} />
                {t('documentEditor.payments.matches')}
              </span>
            ) : (
              <span className="doc-match is-off">
                {t(
                  balance.difference.startsWith('-')
                    ? 'documentEditor.payments.missing'
                    : 'documentEditor.payments.over',
                  {amount: formatMoney(balance.difference.replace(/^-/, ''))},
                )}
              </span>
            )}
          </p>
        )}
      </div>
      {errors['payments'] && (
        <p className="field-error" role="alert">
          {errors['payments']}
        </p>
      )}
    </section>
  );
}
