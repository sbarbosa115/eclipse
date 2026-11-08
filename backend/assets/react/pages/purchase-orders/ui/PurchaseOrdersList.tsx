import {Link, useLocation} from 'react-router-dom';
import {useSession} from '@/entities/session';
import {useTranslation} from '@/shared/i18n';
import {PageHeader} from '@/shared/ui';
import {PurchaseOrderList} from '@/widgets/purchase-order-list';
import {canWritePurchaseOrders} from '../model/access';

/** /ordenes-compra: the list, with "Nueva orden de compra" in the header. */
export function PurchaseOrdersList() {
  const {t} = useTranslation();
  const location = useLocation();
  const {session} = useSession();
  const writer = canWritePurchaseOrders(session);
  return (
    <>
      <PageHeader
        title={t('purchaseOrder.title')}
        subtitle={t('purchaseOrder.subtitle')}
        actions={
          writer && (
            <Link to="nueva" className="btn btn-primary">
              {t('purchaseOrder.new')}
            </Link>
          )
        }
      />
      <PurchaseOrderList
        canWrite={writer}
        notice={(location.state as {notice?: string} | null)?.notice ?? null}
      />
    </>
  );
}
