import {useState} from 'react';
import {Link, useLocation} from 'react-router-dom';
import {canWritePurchases} from '@/entities/purchase-invoice';
import {useSession} from '@/entities/session';
import {useTranslation} from '@/shared/i18n';
import {Alert, PageHeader} from '@/shared/ui';
import {PurchaseInvoiceList} from '@/widgets/purchase-invoice-list';

export function PurchaseInvoiceListPage() {
  const {t} = useTranslation();
  const location = useLocation();
  const {session} = useSession();
  const writer = canWritePurchases(session?.role);
  const [notice, setNotice] = useState<string | null>(
    (location.state as {notice?: string} | null)?.notice ?? null,
  );
  return (
    <>
      <PageHeader
        title={t('purchaseInvoice.title')}
        subtitle={t('purchaseInvoice.subtitle')}
        actions={
          writer && (
            <Link to="nueva" className="btn btn-primary">
              {t('purchaseInvoice.new')}
            </Link>
          )
        }
      />
      <Alert kind="success" onDismiss={() => setNotice(null)}>
        {notice}
      </Alert>
      <PurchaseInvoiceList canWrite={writer} />
    </>
  );
}
