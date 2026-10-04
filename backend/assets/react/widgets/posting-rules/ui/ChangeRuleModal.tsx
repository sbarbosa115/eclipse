import {useEffect, useState} from 'react';
import {useTranslation} from '@/shared/i18n';
import {Field, FormModal} from '@/shared/ui';
import {
  changeRule,
  searchAccounts,
  type AccountOption,
  type PostingRule,
} from '../api/rulesApi';
import {errorMessage} from '../lib/errorMessage';

/** Picks another account for a concept, among the postable accounts of the concept's part of the PUC. */
export function ChangeRuleModal({
  rule,
  conceptLabel,
  onClose,
  onSaved,
}: {
  rule: PostingRule;
  conceptLabel: string;
  onClose: () => void;
  onSaved: (rule: PostingRule) => void;
}) {
  const {t} = useTranslation();
  const [query, setQuery] = useState('');
  const [options, setOptions] = useState<AccountOption[]>([]);
  const [accountId, setAccountId] = useState(rule.account_id);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    const timer = setTimeout(() => {
      searchAccounts(query.trim() || (rule.allowed_prefixes[0] ?? ''))
        .then(({items}) => {
          if (cancelled) return;
          setOptions(
            items.filter((a) =>
              rule.allowed_prefixes.some((p) => a.code.startsWith(p)),
            ),
          );
        })
        .catch(() => {
          if (!cancelled) setOptions([]);
        });
    }, 250);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [query, rule.allowed_prefixes]);

  const current = {
    id: rule.account_id,
    label: `${rule.account_code} ${rule.account_name}`,
  };
  const choices = [
    current,
    ...options
      .filter((a) => a.id !== rule.account_id)
      .map((a) => ({id: a.id, label: `${a.code} ${a.name}`})),
  ];

  const submit = () => {
    setBusy(true);
    setError(null);
    changeRule(rule.concept, accountId)
      .then(onSaved)
      .catch((e: unknown) => {
        setError(errorMessage(t, e));
        setBusy(false);
      });
  };

  return (
    <FormModal
      title={t('ledger.rules.changeTitle', {concept: conceptLabel})}
      onClose={onClose}
      onSubmit={submit}
      busy={busy}
      error={error}
    >
      <Field
        label={t('ledger.rules.searchAccount')}
        className="span-2"
        hint={t('ledger.rules.allowed', {
          prefixes: rule.allowed_prefixes.join(', '),
        })}
      >
        <input
          type="search"
          value={query}
          placeholder={t('ledger.rules.searchPlaceholder')}
          onChange={(e) => setQuery(e.target.value)}
        />
      </Field>
      <Field label={t('ledger.rules.pickAccount')} className="span-2">
        <select
          value={accountId}
          onChange={(e) => setAccountId(e.target.value)}
        >
          {choices.map((choice) => (
            <option key={choice.id} value={choice.id}>
              {choice.label}
            </option>
          ))}
        </select>
      </Field>
      {query.trim() !== '' && options.length === 0 && (
        <p className="small muted span-2">{t('ledger.rules.noMatches')}</p>
      )}
    </FormModal>
  );
}
