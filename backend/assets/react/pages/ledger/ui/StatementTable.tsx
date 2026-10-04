import {Fragment} from 'react';
import {useTranslation} from '@/shared/i18n';
import {DataTable} from '@/shared/ui';
import type {StatementSection} from '../api/ledgerApi';
import {MoneyCell} from './Money';

/** A statement: each section (a PUC class) with its groups and cuentas, then the summary lines. */
export function StatementTable({
  sections,
  summary,
}: {
  sections: StatementSection[];
  summary: ReadonlyArray<{label: string; amount: string}>;
}) {
  const {t} = useTranslation();
  return (
    <DataTable
      actions={false}
      columns={[t('ledger.statement.concept'), t('ledger.statement.amount')]}
      rows={[...sections.map((s) => ({section: s})), {section: null}]}
      renderRow={({section}) =>
        section ? (
          <Fragment key={section.code}>
            <tr className="ledger-section">
              <td>{`${section.code} ${section.name}`}</td>
              <MoneyCell amount={section.total} />
            </tr>
            {section.lines.map((line) => (
              <tr key={line.code}>
                <td
                  className={
                    line.level === 'group'
                      ? 'ledger-indent-1'
                      : 'ledger-indent-2'
                  }
                >
                  {`${line.code} ${line.name}`}
                </td>
                <MoneyCell amount={line.amount} />
              </tr>
            ))}
          </Fragment>
        ) : (
          <Fragment key="summary">
            {summary.map((item) => (
              <tr key={item.label} className="ledger-subtotal">
                <td>{item.label}</td>
                <MoneyCell amount={item.amount} />
              </tr>
            ))}
          </Fragment>
        )
      }
    />
  );
}
