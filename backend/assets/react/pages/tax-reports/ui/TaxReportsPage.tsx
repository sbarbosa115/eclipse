import {useTranslation} from '@/shared/i18n';
import {EmptyState, PageHeader} from '@/shared/ui';

/**
 * Reportes › Impuestos (observations PRD §4.7): retenciones, IVA and exógena. Placeholder of the contract (item 0):
 * the "tax-reports" item builds the page and its retenciones and IVA tabs, the "exogena" item the exógena tab.
 */
export function TaxReportsPage() {
  const {t} = useTranslation();

  return (
    <>
      <PageHeader title={t('taxReports.title')} />
      <EmptyState>{t('taxReports.comingSoon')}</EmptyState>
    </>
  );
}
