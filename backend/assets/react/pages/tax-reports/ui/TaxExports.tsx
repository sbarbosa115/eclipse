import {ExportLinks, exportUrl} from '@/widgets/report-table';

/** A report's CSV and PDF with the filters on screen: the export endpoint is the report's path + /export. */
export function TaxExports({
  name,
  path,
  params,
}: {
  name: string;
  path: string;
  params: Record<string, string | undefined>;
}) {
  return (
    <div className="tax-exports">
      <ExportLinks
        target={{
          name,
          csv: exportUrl(`${path}/export`, 'csv', params),
          pdf: exportUrl(`${path}/export`, 'pdf', params),
        }}
      />
    </div>
  );
}
