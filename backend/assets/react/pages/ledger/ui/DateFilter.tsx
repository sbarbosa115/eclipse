import {DateInput} from '@/shared/ui';

/** A labelled date input that sits in the FilterBar next to the dropdowns. */
export function DateFilter({
  label,
  value,
  onChange,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
}) {
  return (
    <label className="filter-select ledger-period">
      <span className="filter-select-label">{label}</span>
      <DateInput value={value} onChange={onChange} />
    </label>
  );
}
