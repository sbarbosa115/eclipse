import {useState} from 'react';
import {can, useSession} from '@/entities/session';
import {useTranslation} from '@/shared/i18n';
import {todayInColombia} from '@/shared/lib';
import {Card, DateInput, Field, TabIntro} from '@/shared/ui';
import {
  exportUrl,
  ExportLinks,
  type ExportTarget,
} from '@/widgets/report-table';
import './reports.css';

/**
 * Exportar (§4.13, Q26): every report as CSV (UTF-8, `;`, decimal points: machine-readable) and PDF, for the dates
 * picked here. The ledger's books are for whoever may view them.
 */
export function ExportPanel() {
  const {t} = useTranslation();
  const {session} = useSession();
  const today = todayInColombia();
  const [from, setFrom] = useState(`${today.slice(0, 4)}-01-01`);
  const [to, setTo] = useState(today);
  const [asOf, setAsOf] = useState(today);
  const books = can(session, 'VIEW_BOOKS');

  const target = (
    key: string,
    path: string,
    params: Record<string, string>,
  ): {key: string; target: ExportTarget} => {
    const name = t(`reports.exportPanel.reports.${key}`);
    return {
      key,
      target: {
        name,
        csv: exportUrl(path, 'csv', params),
        pdf: exportUrl(path, 'pdf', params),
      },
    };
  };
  const detail = {as_of: asOf, detail: '1'};
  const carteraReports = [
    target('carteraClients', '/reports/cartera/clients/export', {as_of: asOf}),
    target('carteraClientsDetail', '/reports/cartera/clients/export', detail),
    target('carteraSuppliers', '/reports/cartera/suppliers/export', {
      as_of: asOf,
    }),
    target(
      'carteraSuppliersDetail',
      '/reports/cartera/suppliers/export',
      detail,
    ),
  ];
  const bookReports = [
    target('journal', '/reports/ledger/journal/export', {from, to}),
    target('trialBalance', '/reports/ledger/trial-balance/export', {from, to}),
    target('incomeStatement', '/reports/ledger/income-statement/export', {
      from,
      to,
    }),
    target('balanceSheet', '/reports/ledger/balance-sheet/export', {
      date: asOf,
    }),
  ];
  const card = ({key, target: item}: {key: string; target: ExportTarget}) => (
    <Card key={key} title={item.name}>
      <p className="muted">{t(`reports.exportPanel.hints.${key}`)}</p>
      <ExportLinks target={item} />
    </Card>
  );

  return (
    <>
      <TabIntro>{t('reports.exportPanel.intro')}</TabIntro>
      <Card>
        <div className="report-export-period">
          <Field label={t('reports.exportPanel.asOf')}>
            <DateInput value={asOf} onChange={(iso) => iso && setAsOf(iso)} />
          </Field>
          {books && (
            <>
              <Field label={t('reports.exportPanel.from')}>
                <DateInput
                  value={from}
                  onChange={(iso) => iso && setFrom(iso)}
                />
              </Field>
              <Field label={t('reports.exportPanel.to')}>
                <DateInput value={to} onChange={(iso) => iso && setTo(iso)} />
              </Field>
            </>
          )}
        </div>
        <p className="muted">{t('reports.exportPanel.format')}</p>
      </Card>
      <div className="report-export-grid">
        {carteraReports.map(card)}
        {books ? bookReports.map(card) : null}
      </div>
      {!books && <p className="muted">{t('reports.exportPanel.noBooks')}</p>}
    </>
  );
}
