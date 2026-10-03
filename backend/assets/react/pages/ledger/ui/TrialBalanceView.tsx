import {Link, useSearchParams} from 'react-router-dom';
import {useTranslation} from '@/shared/i18n';
import {
  actionClass,
  Actions,
  Alert,
  DataTable,
  EmptyState,
  ErrorState,
  FilterBar,
  Loading,
  TabIntro,
} from '@/shared/ui';
import {fetchTrialBalance} from '../api/ledgerApi';
import {startOfYear, today} from '../lib/period';
import {useLoaded} from '../lib/useLoaded';
import {DateFilter} from './DateFilter';
import {MoneyCell} from './Money';

const LEVELS = ['class', 'group', 'account', 'subaccount', 'auxiliary'];

/** §4.13 Balance de prueba: per account, saldo anterior, débitos, créditos and nuevo saldo; Σ débitos = Σ créditos. */
export function TrialBalanceView() {
  const {t} = useTranslation();
  const [params, setParams] = useSearchParams();
  const from = params.get('from') ?? startOfYear();
  const to = params.get('to') ?? today();
  const level = params.get('level') ?? 'account';
  const depth = LEVELS.indexOf(level);
  const change = (name: string, value: string) => {
    const next = new URLSearchParams(params);
    next.set(name, value);
    setParams(next);
  };

  const {data, failed, retry} = useLoaded(`${from}|${to}`, () =>
    fetchTrialBalance(from, to),
  );
  const rows = data?.rows.filter((r) => LEVELS.indexOf(r.level) <= depth) ?? [];

  return (
    <>
      <TabIntro>{t('ledger.trialBalance.intro')}</TabIntro>
      <FilterBar
        filters={[
          {
            name: 'level',
            label: t('ledger.trialBalance.level'),
            value: level,
            onChange: (value) => change('level', value),
            options: LEVELS.map((value) => ({
              value,
              label: t(`ledger.levels.${value}`),
            })),
          },
        ]}
      >
        <DateFilter
          label={t('ledger.period.from')}
          value={from}
          onChange={(value) => change('from', value)}
        />
        <DateFilter
          label={t('ledger.period.to')}
          value={to}
          onChange={(value) => change('to', value)}
        />
      </FilterBar>
      {failed ? (
        <ErrorState message={t('common.loadFailed')} onRetry={retry} />
      ) : !data ? (
        <Loading />
      ) : data.rows.length === 0 ? (
        <EmptyState>{t('ledger.trialBalance.empty')}</EmptyState>
      ) : (
        <>
          <Alert kind={data.balanced ? 'success' : 'error'}>
            {data.balanced
              ? t('ledger.trialBalance.balanced')
              : t('ledger.trialBalance.unbalanced')}
          </Alert>
          <DataTable
            columns={[
              t('ledger.chart.code'),
              t('ledger.chart.name'),
              t('ledger.amounts.opening'),
              t('ledger.amounts.debit'),
              t('ledger.amounts.credit'),
              t('ledger.amounts.closing'),
            ]}
            rows={[...rows, null]}
            renderRow={(row) =>
              row ? (
                <tr
                  key={row.code}
                  className={
                    row.level === 'class' || row.level === 'group'
                      ? 'ledger-section'
                      : undefined
                  }
                >
                  <td className="nowrap">{row.code}</td>
                  <td>{row.name}</td>
                  <MoneyCell amount={row.opening} />
                  <MoneyCell amount={row.debit} />
                  <MoneyCell amount={row.credit} />
                  <MoneyCell amount={row.closing} />
                  <Actions>
                    <Link
                      className={actionClass('open')}
                      to={`/contabilidad/diario?account=${row.code}&from=${from}&to=${to}`}
                    >
                      {t('ledger.trialBalance.drillDown', {code: row.code})}
                    </Link>
                  </Actions>
                </tr>
              ) : (
                <tr key="total" className="ledger-subtotal">
                  <td colSpan={3}>{t('ledger.amounts.total')}</td>
                  <MoneyCell amount={data.total_debit} />
                  <MoneyCell amount={data.total_credit} />
                  <td colSpan={2} />
                </tr>
              )
            }
          />
        </>
      )}
    </>
  );
}
