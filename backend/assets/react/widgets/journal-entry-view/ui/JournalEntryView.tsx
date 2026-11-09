import {Fragment, useCallback, useEffect, useState} from 'react';
import {useTranslation} from '@/shared/i18n';
import {formatDate, formatMoney} from '@/shared/lib';
import {
  Badge,
  Card,
  DataTable,
  EmptyState,
  ErrorState,
  Loading,
} from '@/shared/ui';
import {
  fetchDocumentEntries,
  type DocumentEntry,
  type EntrySourceType,
} from '../api/journalEntryApi';
import './journalEntryView.css';

type Load =
  | {status: 'loading'}
  | {status: 'failed'}
  | {status: 'ready'; entries: DocumentEntry[]};

/**
 * The Contabilización tab of an emitted document (observations PRD §4.5): each entry the document left in the
 * books, read-only, with its lines (Cuenta, Nombre, Tercero, Débito, Crédito) and totals. A voided document also
 * shows the reversal.
 */
export function JournalEntryView({
  sourceType,
  sourceId,
}: {
  sourceType: EntrySourceType;
  sourceId: string;
}) {
  const {t} = useTranslation();
  const [load, setLoad] = useState<Load>({status: 'loading'});
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    let cancelled = false;
    fetchDocumentEntries(sourceType, sourceId)
      .then(
        ({items}) => !cancelled && setLoad({status: 'ready', entries: items}),
      )
      .catch(() => !cancelled && setLoad({status: 'failed'}));
    return () => {
      cancelled = true;
    };
  }, [sourceType, sourceId, attempt]);

  const retry = useCallback(() => {
    setLoad({status: 'loading'});
    setAttempt((n) => n + 1);
  }, []);

  if (load.status === 'loading') return <Loading />;
  if (load.status === 'failed') {
    return (
      <ErrorState message={t('journalEntry.loadFailed')} onRetry={retry} />
    );
  }
  if (load.entries.length === 0) {
    return <EmptyState>{t('journalEntry.empty')}</EmptyState>;
  }

  const numbers = new Map(load.entries.map((e) => [e.id, e.number]));
  return (
    <div className="journal-entry-view">
      <p className="muted">{t('journalEntry.intro')}</p>
      {load.entries.map((entry) => {
        const reversed = entry.reverses_id
          ? numbers.get(entry.reverses_id)
          : undefined;
        return (
          <Card
            key={entry.id}
            title={
              <>
                {t('journalEntry.entry', {number: String(entry.number)})}
                {entry.reverses_id && (
                  <>
                    {' '}
                    <Badge value="voided">
                      {reversed === undefined
                        ? t('journalEntry.reversalOther')
                        : t('journalEntry.reversal', {
                            number: String(reversed),
                          })}
                    </Badge>
                  </>
                )}
              </>
            }
          >
            <p className="journal-entry-meta">
              {formatDate(entry.date)} · {entry.description}
            </p>
            <DataTable
              actions={false}
              columns={[
                t('journalEntry.columns.account'),
                t('journalEntry.columns.name'),
                t('journalEntry.columns.tercero'),
                t('journalEntry.columns.debit'),
                t('journalEntry.columns.credit'),
              ]}
              rows={[...entry.lines, null]}
              renderRow={(line, index) => (
                <Fragment key={`${entry.id}-${index}`}>
                  {line ? (
                    <tr>
                      <td className="nowrap">{line.account_code}</td>
                      <td>{line.account_name}</td>
                      <td>{line.tercero_name}</td>
                      <td className="num">
                        {Number(line.debit) === 0
                          ? ''
                          : formatMoney(line.debit)}
                      </td>
                      <td className="num">
                        {Number(line.credit) === 0
                          ? ''
                          : formatMoney(line.credit)}
                      </td>
                    </tr>
                  ) : (
                    <tr className="journal-entry-total">
                      <td colSpan={3}>{t('journalEntry.totals')}</td>
                      <td className="num">{formatMoney(entry.total_debit)}</td>
                      <td className="num">{formatMoney(entry.total_credit)}</td>
                    </tr>
                  )}
                </Fragment>
              )}
            />
          </Card>
        );
      })}
    </div>
  );
}
