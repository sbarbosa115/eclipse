import {useEffect, useId, useState} from 'react';
import {useTranslation} from '@/shared/i18n';
import {Field} from '@/shared/ui';
import {searchAccounts, type Account} from '../api/productApi';
import type {AccountChoice} from '../model/productForm';

/** "413595 · Venta de otros": how an account reads in a picker. */
const labelOf = (account: Account) => `${account.code} · ${account.name}`;

/**
 * Picks a postable account of the chart by typing its code or name: the matches are offered as the person types, and
 * only one chosen from them counts (the id is set when the text is exactly one of them).
 */
export function AccountPicker({
  label,
  hint,
  value,
  error,
  onChange,
}: {
  label: string;
  hint?: string;
  value: AccountChoice;
  error?: string | null;
  onChange: (choice: AccountChoice) => void;
}) {
  const {t} = useTranslation();
  const list = useId();
  const [matches, setMatches] = useState<Account[]>([]);

  useEffect(() => {
    const term = value.label.trim();
    let cancelled = false;
    const timer = setTimeout(() => {
      searchAccounts(term)
        .then((found) => {
          if (!cancelled) setMatches(found);
        })
        .catch(() => {
          if (!cancelled) setMatches([]);
        });
    }, 250);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [value.label]);

  const type = (text: string) => {
    const chosen = matches.find((account) => labelOf(account) === text);
    onChange({id: chosen?.id ?? null, label: text});
  };

  return (
    <>
      <Field label={label} hint={hint} error={error} optional>
        <input
          list={list}
          autoComplete="off"
          placeholder={t('catalog.form.accountPlaceholder')}
          value={value.label}
          onChange={(event) => type(event.target.value)}
        />
      </Field>
      <datalist id={list}>
        {matches.map((account) => (
          <option key={account.id} value={labelOf(account)} />
        ))}
      </datalist>
    </>
  );
}
