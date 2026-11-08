import {useTranslation} from '@/shared/i18n';
import {EmptyState, PageHeader} from '@/shared/ui';

/**
 * Órdenes de compra (observations PRD §4.8). Placeholder of the contract (item 0): the "purchase-orders" item builds
 * the list, the editor and the detail under /ordenes-compra/*.
 */
export function PurchaseOrdersPage() {
  const {t} = useTranslation();

  return (
    <>
      <PageHeader title={t('purchaseOrder.title')} />
      <EmptyState>{t('purchaseOrder.comingSoon')}</EmptyState>
    </>
  );
}
