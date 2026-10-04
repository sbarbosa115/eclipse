import {useState} from 'react';
import {
  convertQuotation,
  quotationErrorMessage,
  violationsOf,
  type Quotation,
  type Violation,
} from '@/entities/quotation';
import {useTranslation} from '@/shared/i18n';
import {Button, Modal} from '@/shared/ui';

export interface ConvertQuotationProps {
  quotationId: string;
  disabled?: boolean;
  /** The draft invoice exists (and the quotation is accepted): the page opens it. */
  onConverted: (quotation: Quotation, invoiceId: string) => void;
  /**
   * The invoice refused something that changed since the quotation was made (an inactive client, product or tax):
   * nothing was converted, and the page shows what, by field path.
   */
  onRefused: (violations: Violation[]) => void;
  /** Any other refusal (already converted, expired…), in words. */
  onFailed: (message: string) => void;
}

/**
 * "Convertir a factura" (§4.7): asks first, then makes the draft sales invoice from the quotation. A quotation converts
 * once, so the person is told what happens before it does.
 */
export function ConvertQuotation({
  quotationId,
  disabled = false,
  onConverted,
  onRefused,
  onFailed,
}: ConvertQuotationProps) {
  const {t} = useTranslation();
  const [asking, setAsking] = useState(false);
  const [busy, setBusy] = useState(false);

  const convert = async () => {
    setBusy(true);
    try {
      const converted = await convertQuotation(quotationId);
      setAsking(false);
      if (converted.converted_invoice_id) {
        onConverted(converted, converted.converted_invoice_id);
      }
    } catch (error) {
      setAsking(false);
      const violations = violationsOf(error);
      if (violations.length > 0) onRefused(violations);
      else onFailed(quotationErrorMessage(error, t));
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
        {t('quotation.actions.convert')}
      </Button>
      {asking && (
        <Modal
          title={t('quotation.convert.title')}
          onClose={() => !busy && setAsking(false)}
        >
          <p>{t('quotation.convert.body')}</p>
          <div className="form-actions">
            <Button variant="ghost" onClick={() => setAsking(false)}>
              {t('common.cancel')}
            </Button>
            <Button busy={busy} onClick={convert}>
              {t('quotation.convert.confirm')}
            </Button>
          </div>
        </Modal>
      )}
    </>
  );
}
