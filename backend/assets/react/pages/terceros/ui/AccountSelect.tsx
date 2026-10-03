import {useEffect, useState} from 'react';
import {apiGet, type Schema} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {Field} from '@/shared/ui';
import type {AccountRef} from '@/entities/tercero';

type Account = Schema<'AccountOutput'>;

/**
 * Picks one of the company's postable accounts under the given code prefixes (1305 for what a tercero owes, 2205 and
 * 2335 for what the company owes it), or none: the posting rules' account then applies.
 */
export function AccountSelect({
  label,
  prefixes,
  value,
  current,
  onChange,
  error,
}: {
  label: string;
  prefixes: readonly string[];
  value: string;
  /** The account the tercero has now, so it shows even before (or without) the options loading. */
  current: AccountRef | null;
  onChange: (id: string) => void;
  error?: string;
}) {
  const {t} = useTranslation();
  const [options, setOptions] = useState<Account[]>([]);

  useEffect(() => {
    let cancelled = false;
    Promise.all(
      prefixes.map((prefix) =>
        apiGet<{items: Account[]}>(
          `/accounts/search?q=${encodeURIComponent(prefix)}`,
        ),
      ),
    )
      .then((answers) => {
        if (cancelled) return;
        setOptions(
          answers
            .flatMap((a) => a.items)
            .filter((a) => prefixes.some((p) => a.code.startsWith(p))),
        );
      })
      .catch(() => undefined);
    return () => {
      cancelled = true;
    };
  }, [prefixes]);

  const known = options.some((o) => o.id === value);
  return (
    <Field label={label} error={error} optional>
      <select value={value} onChange={(e) => onChange(e.target.value)}>
        <option value="">{t('terceros.form.defaultAccount')}</option>
        {!known && current && value === current.id && (
          <option
            value={current.id}
          >{`${current.code} · ${current.name}`}</option>
        )}
        {options.map((account) => (
          <option key={account.id} value={account.id}>
            {`${account.code} · ${account.name}`}
          </option>
        ))}
      </select>
    </Field>
  );
}
