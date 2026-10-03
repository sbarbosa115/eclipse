import {useEffect, useRef, useState} from 'react';
import {apiGet, type Schema} from '@/shared/api';

type Account = Schema<'AccountOutput'>;

/**
 * What the picker holds: the account chosen (null while none matches) and the text in the box, so a form can tell
 * "left empty" (text '') from "typed something that is not an account" (text set, id null).
 */
export interface AccountChoice {
  id: string | null;
  text: string;
}

/** "240805 · IVA generado": how an account reads in the box and in the list. */
export function accountLabel(account: {code: string; name: string}): string {
  return `${account.code} · ${account.name}`;
}

interface Props {
  'value': AccountChoice;
  'onChange': (choice: AccountChoice) => void;
  /** Only the accounts a purchase line may use. */
  'purchases'?: boolean;
  /** Passed down by <Field>, which labels the box. */
  'id'?: string;
  'aria-describedby'?: string;
  'aria-invalid'?: boolean;
}

/**
 * A box that searches the company's postable accounts by code or name as you type and offers them in a list. Choosing
 * one (or typing its code or its full label) sets the account. Built on the native list so the keyboard and screen
 * readers work as they do everywhere.
 */
export function AccountPicker({
  value,
  onChange,
  purchases = false,
  ...aria
}: Props) {
  const [options, setOptions] = useState<Account[]>([]);
  const listId = `${aria.id ?? 'account'}-options`;
  const asked = useRef(0);
  const latest = useRef({onChange});
  useEffect(() => {
    latest.current = {onChange};
  });

  const text = value.text;
  useEffect(() => {
    const term = text.trim();
    if (term === '') return;
    const attempt = ++asked.current;
    const timer = setTimeout(() => {
      const query = new URLSearchParams({q: term});
      if (purchases) query.set('purchases', '1');
      apiGet<{items: Account[]}>(`/accounts/search?${query}`)
        .then((answer) => {
          if (attempt !== asked.current) return;
          setOptions(answer.items);
          const exact = answer.items.find(
            (a) => accountLabel(a) === text || a.code === term,
          );
          if (exact) {
            latest.current.onChange({
              id: exact.id,
              text: accountLabel(exact),
            });
          }
        })
        .catch(() => {
          if (attempt === asked.current) setOptions([]);
        });
    }, 250);
    return () => clearTimeout(timer);
  }, [text, purchases]);

  return (
    <>
      <input
        {...aria}
        value={value.text}
        list={listId}
        autoComplete="off"
        inputMode="search"
        onChange={(event) => {
          const typed = event.target.value;
          // Picking from the list types the full label: recognise it without waiting for the search.
          const picked = options.find((a) => accountLabel(a) === typed);
          onChange(
            picked
              ? {id: picked.id, text: typed}
              : {id: typed === value.text ? value.id : null, text: typed},
          );
        }}
      />
      <datalist id={listId}>
        {options.map((account) => (
          <option key={account.id} value={accountLabel(account)} />
        ))}
      </datalist>
    </>
  );
}
