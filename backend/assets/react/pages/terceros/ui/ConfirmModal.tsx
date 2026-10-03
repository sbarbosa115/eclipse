import {useState} from 'react';
import {useTranslation} from '@/shared/i18n';
import {ActionButton, Alert, Button, Modal} from '@/shared/ui';

/** Asks before something that cannot be taken back (or is a big change); shows why it failed and stays open. */
export function ConfirmModal({
  title,
  body,
  confirmLabel,
  onConfirm,
  onClose,
}: {
  title: string;
  body: string;
  confirmLabel: string;
  /** Resolves when done; rejects with the message to show. */
  onConfirm: () => Promise<void>;
  onClose: () => void;
}) {
  const {t} = useTranslation();
  const [busy, setBusy] = useState(false);
  const [failure, setFailure] = useState<string | null>(null);

  const confirm = async () => {
    setBusy(true);
    setFailure(null);
    try {
      await onConfirm();
    } catch (error) {
      setFailure(error instanceof Error ? error.message : String(error));
      setBusy(false);
    }
  };

  return (
    <Modal title={title} onClose={onClose}>
      <Alert kind="error">{failure}</Alert>
      <p>{body}</p>
      <div className="form-actions">
        <Button variant="ghost" onClick={onClose}>
          {t('common.cancel')}
        </Button>
        <ActionButton action="danger" size="md" busy={busy} onClick={confirm}>
          {confirmLabel}
        </ActionButton>
      </div>
    </Modal>
  );
}
