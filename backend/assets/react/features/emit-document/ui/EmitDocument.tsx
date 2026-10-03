import {useState} from 'react';
import {useTranslation} from '@/shared/i18n';
import {Alert, Button, Modal} from '@/shared/ui';

export interface EmitDocumentProps {
  /** "Emitir". */
  emitLabel: string;
  /** "Emitir y enviar": shown only when given. */
  emitAndSendLabel?: string;
  confirmTitle: string;
  /** What emitting does to this document (it takes its number, it can no longer be edited…). */
  confirmBody: string;
  /** Added to the body when it is also sent ("se enviará a facturas@cliente.co"). */
  sendNote?: string;
  /** Emits (and sends); rejects with the message to show in the dialog, which then stays open. */
  onEmit: (send: boolean) => Promise<void>;
  /** Asked before the dialog opens: false keeps it closed (the page shows what is missing instead). */
  beforeConfirm?: (send: boolean) => boolean;
  disabled?: boolean;
}

/**
 * Emitir / Emitir y enviar (§4.6) for any document: the buttons and the confirmation, since emission cannot be undone
 * (only voided). The document's own copy comes in through the props.
 */
export function EmitDocument({
  emitLabel,
  emitAndSendLabel,
  confirmTitle,
  confirmBody,
  sendNote,
  onEmit,
  beforeConfirm,
  disabled = false,
}: EmitDocumentProps) {
  const {t} = useTranslation();
  const [asking, setAsking] = useState<null | {send: boolean}>(null);
  const [busy, setBusy] = useState(false);
  const [failure, setFailure] = useState<string | null>(null);

  const open = (send: boolean) => {
    if (beforeConfirm && !beforeConfirm(send)) return;
    setFailure(null);
    setAsking({send});
  };

  const confirm = async () => {
    if (!asking) return;
    setBusy(true);
    setFailure(null);
    try {
      await onEmit(asking.send);
      setAsking(null);
    } catch (error) {
      setFailure(error instanceof Error ? error.message : String(error));
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      {emitAndSendLabel && (
        <Button
          variant="secondary"
          disabled={disabled}
          onClick={() => open(true)}
        >
          {emitAndSendLabel}
        </Button>
      )}
      <Button disabled={disabled} onClick={() => open(false)}>
        {emitLabel}
      </Button>
      {asking && (
        <Modal title={confirmTitle} onClose={() => !busy && setAsking(null)}>
          <Alert kind="error">{failure}</Alert>
          <p>{confirmBody}</p>
          {asking.send && sendNote && <p>{sendNote}</p>}
          <div className="form-actions">
            <Button variant="ghost" onClick={() => setAsking(null)}>
              {t('common.cancel')}
            </Button>
            <Button busy={busy} onClick={confirm}>
              {asking.send && emitAndSendLabel ? emitAndSendLabel : emitLabel}
            </Button>
          </div>
        </Modal>
      )}
    </>
  );
}
