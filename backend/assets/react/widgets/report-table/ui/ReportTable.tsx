import type {ReactNode} from 'react';
import {useTranslation} from '@/shared/i18n';
import {DataTable, EmptyState} from '@/shared/ui';
import {ExportLinks, type ExportTarget} from './ExportLinks';
import './reportTable.css';

export interface ReportColumn<T> {
  header: string;
  cell: (row: T) => ReactNode;
  /** Money and counts: right-aligned, tabular figures. */
  numeric?: boolean;
  /** What the totals row shows under this column (nothing when absent). */
  total?: ReactNode;
}

/**
 * A report as a table: its columns, a totals row, and the CSV / PDF buttons above it. Pages give the columns and the
 * rows, never a <table> of their own, so every report looks and exports the same way.
 */
export function ReportTable<T>({
  columns,
  rows,
  rowKey,
  exports,
  totalLabel,
  rowActions,
  empty,
  busy = false,
}: {
  columns: ReadonlyArray<ReportColumn<T>>;
  rows: ReadonlyArray<T>;
  rowKey: (row: T) => string;
  /** The files of this report; the buttons are left out when absent. */
  exports?: ExportTarget;
  /** The first cell of the totals row. The row is left out when no column has a total. */
  totalLabel?: string;
  /** The row's `<Actions>` (links such as "Ver documentos"): the Acciones column. */
  rowActions?: (row: T) => ReactNode;
  /** What an empty report says. */
  empty?: ReactNode;
  busy?: boolean;
}) {
  const {t} = useTranslation();
  const hasTotals = columns.some((c) => c.total !== undefined);
  const cellClass = (c: ReportColumn<T>) =>
    c.numeric ? 'report-num' : undefined;

  return (
    <div className="report-table">
      {exports && <ExportLinks target={exports} />}
      {rows.length === 0 ? (
        <EmptyState>{empty ?? t('reports.table.empty')}</EmptyState>
      ) : (
        <DataTable<T | null>
          columns={columns.map((c) => c.header)}
          actions={rowActions !== undefined}
          busy={busy}
          rows={hasTotals ? [...rows, null] : rows}
          renderRow={(row, index) =>
            row === null ? (
              <tr key="total" className="report-total">
                {columns.map((c, i) => (
                  <td key={c.header} className={cellClass(c)}>
                    {i === 0 && c.total === undefined ? totalLabel : c.total}
                  </td>
                ))}
                {rowActions !== undefined && <td />}
              </tr>
            ) : (
              <tr key={rowKey(row)} data-row={index}>
                {columns.map((c) => (
                  <td key={c.header} className={cellClass(c)}>
                    {c.cell(row)}
                  </td>
                ))}
                {rowActions !== undefined && rowActions(row)}
              </tr>
            )
          }
        />
      )}
    </div>
  );
}
