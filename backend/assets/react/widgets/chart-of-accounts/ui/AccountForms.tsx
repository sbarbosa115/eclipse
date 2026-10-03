import {useState} from 'react';
import {useTranslation} from '@/shared/i18n';
import {Field, FormModal} from '@/shared/ui';
import {addAccount, updateAccount, type Account} from '../api/chartApi';
import {errorMessage} from '../lib/errorMessage';

const isCostOrExpense = (code: string) =>
  ['5', '6', '7'].includes(code.charAt(0));

/** A sub-account or auxiliar under `parent`: its code is the parent's plus two digits. */
export function AddAccountModal({
  parent,
  onClose,
  onSaved,
}: {
  parent: Account;
  onClose: () => void;
  onSaved: (account: Account) => void;
}) {
  const {t} = useTranslation();
  const [code, setCode] = useState(parent.code);
  const [name, setName] = useState('');
  const [purchases, setPurchases] = useState(isCostOrExpense(parent.code));
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = () => {
    setBusy(true);
    setError(null);
    addAccount({
      parent_code: parent.code,
      code: code.trim(),
      name: name.trim(),
      usable_on_purchases: purchases,
    })
      .then(onSaved)
      .catch((e: unknown) => {
        setError(errorMessage(t, e));
        setBusy(false);
      });
  };

  return (
    <FormModal
      title={t('ledger.chart.addTitle', {code: parent.code})}
      onClose={onClose}
      onSubmit={submit}
      busy={busy}
      error={error}
    >
      <Field label={t('ledger.chart.parent')}>
        <input value={`${parent.code} ${parent.name}`} disabled />
      </Field>
      <Field
        label={t('ledger.chart.code')}
        hint={t('ledger.chart.codeHint', {code: parent.code})}
      >
        <input
          value={code}
          inputMode="numeric"
          maxLength={parent.code.length + 2}
          onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
        />
      </Field>
      <Field label={t('ledger.chart.name')} className="span-2">
        <input
          value={name}
          maxLength={200}
          onChange={(e) => setName(e.target.value)}
        />
      </Field>
      <label className="checkbox span-2">
        <input
          type="checkbox"
          checked={purchases}
          onChange={(e) => setPurchases(e.target.checked)}
        />
        {t('ledger.chart.usableOnPurchases')}
      </label>
    </FormModal>
  );
}

/** Rename the company's own account, (de)activate any, mark it usable on purchases. */
export function EditAccountModal({
  account,
  onClose,
  onSaved,
}: {
  account: Account;
  onClose: () => void;
  onSaved: (account: Account) => void;
}) {
  const {t} = useTranslation();
  const [name, setName] = useState(account.name);
  const [active, setActive] = useState(account.active);
  const [purchases, setPurchases] = useState(account.usable_on_purchases);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = () => {
    setBusy(true);
    setError(null);
    updateAccount(account.id, {
      name: name.trim(),
      active,
      usable_on_purchases: purchases,
    })
      .then(onSaved)
      .catch((e: unknown) => {
        setError(errorMessage(t, e));
        setBusy(false);
      });
  };

  return (
    <FormModal
      title={t('ledger.chart.editTitle', {code: account.code})}
      onClose={onClose}
      onSubmit={submit}
      busy={busy}
      error={error}
    >
      <Field
        label={t('ledger.chart.name')}
        className="span-2"
        hint={account.standard ? t('ledger.chart.standardName') : undefined}
      >
        <input
          value={name}
          maxLength={200}
          disabled={account.standard}
          onChange={(e) => setName(e.target.value)}
        />
      </Field>
      <label className="checkbox">
        <input
          type="checkbox"
          checked={active}
          onChange={(e) => setActive(e.target.checked)}
        />
        {t('ledger.chart.activeLabel')}
      </label>
      <label className="checkbox">
        <input
          type="checkbox"
          checked={purchases}
          onChange={(e) => setPurchases(e.target.checked)}
        />
        {t('ledger.chart.usableOnPurchases')}
      </label>
    </FormModal>
  );
}
