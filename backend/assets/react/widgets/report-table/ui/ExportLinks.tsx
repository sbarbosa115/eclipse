import {useState, type MouseEvent} from 'react';
import {useTranslation} from '@/shared/i18n';
import {Alert, Icon} from '@/shared/ui';
import type {ExportFormat} from '../lib/exportUrl';
import './reportTable.css';

export interface ExportTarget {
  /** What the person is told they download: "Cartera de clientes". */
  name: string;
  csv: string;
  pdf: string;
}

/**
 * The two buttons of every report: its CSV (for Excel) and its PDF. A click first rehearses the export (`check=1`:
 * the server answers 204, or 422 when the report is above the row cap) so a report too big to download is explained
 * here, in words, instead of saving the server's error as a file; then the browser downloads the real file.
 */
export function ExportLinks({target}: {target: ExportTarget}) {
  const {t} = useTranslation();
  const [message, setMessage] = useState<string | null>(null);
  const formats: ExportFormat[] = ['csv', 'pdf'];

  const download = async (
    event: MouseEvent<HTMLAnchorElement>,
    href: string,
  ) => {
    event.preventDefault();
    setMessage(null);
    try {
      const rehearsal = await fetch(`${href}&check=1`, {
        headers: {Accept: 'application/json'},
      });
      if (rehearsal.status === 422) {
        setMessage(t('reports.export.tooLarge', {name: target.name}));
        return;
      }
      if (rehearsal.status === 403) {
        setMessage(t('reports.export.forbidden'));
        return;
      }
      if (!rehearsal.ok) {
        setMessage(t('reports.export.failed'));
        return;
      }
    } catch {
      setMessage(t('common.errors.network'));
      return;
    }
    window.location.assign(href);
  };

  return (
    <>
      <div
        className="report-export"
        role="group"
        aria-label={t('reports.export.label', {name: target.name})}
      >
        {formats.map((format) => (
          <a
            key={format}
            className="btn btn-secondary btn-sm"
            href={target[format]}
            download
            aria-label={t(`reports.export.${format}Of`, {name: target.name})}
            onClick={(event) => void download(event, target[format])}
          >
            <Icon name="download" size={16} />
            {t(`reports.export.${format}`)}
          </a>
        ))}
      </div>
      <Alert kind="error" onDismiss={() => setMessage(null)}>
        {message}
      </Alert>
    </>
  );
}
