import {Fragment} from 'react';
import {useSearchParams} from 'react-router-dom';
import {formatDate} from '@/shared/lib';
import {useTranslation} from '@/shared/i18n';
import {
  Button,
  DataTable,
  EmptyState,
  ErrorState,
  FilterBar,
  IconButton,
  Loading,
  Pager,
  TabIntro,
} from '@/shared/ui';
import {fetchJournal} from '../api/ledgerApi';
import {useLoaded} from '../lib/useLoaded';
import {DateFilter} from './DateFilter';
import {LedgerExports} from './LedgerExports';
import {MoneyCell} from './Money';

/** §4.13 Libro diario: entries by date, each with its lines; filtered by period, account and tercero. */
export function JournalView() {
  const {t} = useTranslation();
  const [params, setParams] = useSearchParams();
  const from = params.get('from') ?? '';
  const to = params.get('to') ?? '';
  const account = params.get('account') ?? '';
  const tercero = params.get('tercero_id') ?? '';
  const terceroName = params.get('tercero') ?? '';
  const page = params.get('page') ?? '1';

  const change = (updates: Record<string, string>) => {
    const next = new URLSearchParams(params);
    for (const [name, value] of Object.entries(updates)) {
      if (value) next.set(name, value);
      else next.delete(name);
    }
    if (!('page' in updates)) next.delete('page');
    setParams(next);
  };

  const {data, failed, retry} = useLoaded(params.toString(), () =>
    fetchJournal({from, to, account, tercero_id: tercero, page}),
  );
  const filtered = from !== '' || to !== '' || account !== '' || tercero !== '';
  const source = (type: string) => {
    const key = `ledger.sources.${type}`;
    const label = t(key);
    return label === key ? type : label;
  };

  return (
    <>
      <TabIntro>{t('ledger.journal.intro')}</TabIntro>
      <FilterBar
        search={account}
        onSearch={(value) => change({account: value.replace(/\D/g, '')})}
        searchPlaceholder={t('ledger.journal.accountPlaceholder')}
      >
        <DateFilter
          label={t('ledger.period.from')}
          value={from}
          onChange={(value) => change({from: value})}
        />
        <DateFilter
          label={t('ledger.period.to')}
          value={to}
          onChange={(value) => change({to: value})}
        />
        {tercero && (
          <span className="ledger-chip badge badge-info">
            {t('ledger.journal.terceroFilter', {name: terceroName})}
            <IconButton
              icon="close"
              label={t('ledger.journal.clearTercero')}
              onClick={() => change({tercero_id: '', tercero: ''})}
            />
          </span>
        )}
      </FilterBar>
      <LedgerExports
        book="journal"
        params={{from, to, account, tercero_id: tercero}}
      />
      {failed ? (
        <ErrorState message={t('common.loadFailed')} onRetry={retry} />
      ) : !data ? (
        <Loading />
      ) : data.items.length === 0 ? (
        <EmptyState
          action={
            filtered && (
              <Button variant="ghost" onClick={() => setParams({})}>
                {t('common.showAll')}
              </Button>
            )
          }
        >
          {filtered
            ? t('ledger.journal.filteredEmpty')
            : t('ledger.journal.empty')}
        </EmptyState>
      ) : (
        <>
          <DataTable
            actions={false}
            columns={[
              t('ledger.journal.date'),
              t('ledger.journal.document'),
              t('ledger.journal.account'),
              t('ledger.journal.tercero'),
              t('ledger.amounts.debit'),
              t('ledger.amounts.credit'),
            ]}
            rows={data.items}
            renderRow={(entry) => (
              <Fragment key={entry.id}>
                <tr className="ledger-entry-head">
                  <td>{formatDate(entry.date)}</td>
                  <td>
                    <span className="nowrap">{entry.source_number}</span>
                    <br />
                    <span className="small muted">
                      {source(entry.source_type)}
                    </span>
                  </td>
                  <td colSpan={4}>
                    {`${t('ledger.journal.number')} ${entry.number} · ${entry.description}`}
                    {entry.reverses_id && (
                      <span className="small muted">
                        {` · ${t('ledger.journal.reverses')}`}
                      </span>
                    )}
                  </td>
                </tr>
                {entry.lines.map((line, index) => (
                  <tr key={`${entry.id}-${index}`}>
                    <td />
                    <td />
                    <td className="ledger-indent-1">{`${line.account_code} ${line.account_name}`}</td>
                    <td>
                      {line.tercero_id && line.tercero_name && (
                        <Button
                          variant="link"
                          aria-label={t('ledger.journal.filterByTercero', {
                            name: line.tercero_name,
                          })}
                          onClick={() =>
                            change({
                              tercero_id: line.tercero_id ?? '',
                              tercero: line.tercero_name ?? '',
                            })
                          }
                        >
                          {line.tercero_name}
                        </Button>
                      )}
                    </td>
                    <MoneyCell amount={line.debit} blankZero />
                    <MoneyCell amount={line.credit} blankZero />
                  </tr>
                ))}
                <tr className="ledger-subtotal">
                  <td colSpan={4} />
                  <MoneyCell amount={entry.total_debit} />
                  <MoneyCell amount={entry.total_credit} />
                </tr>
              </Fragment>
            )}
          />
          <Pager data={data} onPage={(next) => change({page: String(next)})} />
        </>
      )}
    </>
  );
}
