import {useSearchParams} from 'react-router-dom';
import {useTranslation} from '@/shared/i18n';
import {ErrorState, FilterBar, Loading, TabIntro} from '@/shared/ui';
import {fetchIncomeStatement} from '../api/ledgerApi';
import {startOfYear, today} from '../lib/period';
import {useLoaded} from '../lib/useLoaded';
import {DateFilter} from './DateFilter';
import {StatementTable} from './StatementTable';

/** §9 Q25: the basic estado de resultados of a period, from the balance de prueba. */
export function IncomeStatementView() {
  const {t} = useTranslation();
  const [params, setParams] = useSearchParams();
  const from = params.get('from') ?? startOfYear();
  const to = params.get('to') ?? today();
  const change = (name: string, value: string) => {
    const next = new URLSearchParams(params);
    next.set(name, value);
    setParams(next);
  };
  const {data, failed, retry} = useLoaded(`${from}|${to}`, () =>
    fetchIncomeStatement(from, to),
  );

  return (
    <>
      <TabIntro>{t('ledger.incomeStatement.intro')}</TabIntro>
      <FilterBar>
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
      ) : (
        <StatementTable
          sections={data.sections}
          summary={[
            {label: t('ledger.incomeStatement.revenue'), amount: data.revenue},
            {label: t('ledger.incomeStatement.costs'), amount: data.costs},
            {
              label: t('ledger.incomeStatement.grossProfit'),
              amount: data.gross_profit,
            },
            {
              label: t('ledger.incomeStatement.expenses'),
              amount: data.expenses,
            },
            {
              label: t('ledger.incomeStatement.netIncome'),
              amount: data.net_income,
            },
          ]}
        />
      )}
    </>
  );
}
