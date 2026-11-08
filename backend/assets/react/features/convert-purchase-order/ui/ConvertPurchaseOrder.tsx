import {useState} from 'react';
import {
  convertPurchaseOrder,
  purchaseOrderErrorMessage,
  violationsOf,
  type PurchaseOrder,
  type Violation,
} from '@/entities/purchase-order';
import {useTranslation} from '@/shared/i18n';
import {Button, Modal} from '@/shared/ui';

export interface ConvertPurchaseOrderProps {
  orderId: string;
  disabled?: boolean;
  /** The draft invoice exists (and the order is converted): the page opens it. */
  onConverted: (order: PurchaseOrder, invoiceId: string) => void;
  /**
   * The invoice refused something that changed since the order was made (an inactive supplier, product or tax):
   * nothing was converted, and the page shows what, by field path.
   */
  onRefused: (violations: Violation[]) => void;
  /** Any other refusal (already converted, voided…), in words. */
  onFailed: (message: string) => void;
}

/**
 * "Convertir en factura" (observations PRD §4.8): asks first, then makes the draft purchase invoice from the order. An
 * order converts once, so the person is told what happens before it does.
 */
export function ConvertPurchaseOrder({
  orderId,
  disabled = false,
  onConverted,
  onRefused,
  onFailed,
}: ConvertPurchaseOrderProps) {
  const {t} = useTranslation();
  const [asking, setAsking] = useState(false);
  const [busy, setBusy] = useState(false);

  const convert = async () => {
    setBusy(true);
    try {
      const converted = await convertPurchaseOrder(orderId);
      setAsking(false);
      if (converted.converted_invoice_id) {
        onConverted(converted, converted.converted_invoice_id);
      }
    } catch (error) {
      setAsking(false);
      const violations = violationsOf(error);
      if (violations.length > 0) onRefused(violations);
      else onFailed(purchaseOrderErrorMessage(error, t));
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <Button
        variant="secondary"
        disabled={disabled}
        onClick={() => setAsking(true)}
      >
        {t('purchaseOrder.actions.convert')}
      </Button>
      {asking && (
        <Modal
          title={t('purchaseOrder.convert.title')}
          onClose={() => !busy && setAsking(false)}
        >
          <p>{t('purchaseOrder.convert.body')}</p>
          <div className="form-actions">
            <Button variant="ghost" onClick={() => setAsking(false)}>
              {t('common.cancel')}
            </Button>
            <Button busy={busy} onClick={convert}>
              {t('purchaseOrder.convert.confirm')}
            </Button>
          </div>
        </Modal>
      )}
    </>
  );
}
