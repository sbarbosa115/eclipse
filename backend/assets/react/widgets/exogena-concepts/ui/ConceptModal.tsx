import {useState} from 'react';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {Field, FormModal} from '@/shared/ui';
import {EXOGENA_FORMATS, type ExogenaConcept} from '../api/exogenaConceptsApi';
import {sameRow} from '../lib/table';

type FieldName = 'format' | 'account_prefix' | 'concept_code';
type Errors = Partial<Record<FieldName, string>>;

interface Props {
  /** The row being edited, or null to add one. */
  row: ExogenaConcept | null;
  /** The other rows of the table, to refuse a prefix twice in one format. */
  others: ExogenaConcept[];
  onClose: () => void;
  /** Saves the table with this row; rejects with the server's refusal. */
  onSave: (row: ExogenaConcept) => Promise<void>;
}

/** Adds or edits one row of the concept table: format, account prefix and DIAN concept. */
export function ConceptModal({row, others, onClose, onSave}: Props) {
  const {t} = useTranslation();
  const [format, setFormat] = useState(row?.format ?? '1001');
  const [prefix, setPrefix] = useState(row?.account_prefix ?? '');
  const [concept, setConcept] = useState(row?.concept_code ?? '');
  const [errors, setErrors] = useState<Errors>({});
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    const next = {
      format,
      account_prefix: prefix.trim(),
      concept_code: concept.trim(),
    };
    const found: Errors = {};
    if (!/^\d{1,16}$/.test(next.account_prefix)) {
      found.account_prefix = t('ledger.exogena.form.prefixInvalid');
    } else if (others.some((other) => sameRow(other, next))) {
      found.account_prefix = t('ledger.exogena.form.prefixTaken', {format});
    }
    if (!/^\d{4}$/.test(next.concept_code)) {
      found.concept_code = t('ledger.exogena.form.conceptInvalid');
    }
    setErrors(found);
    setFailure(null);
    if (Object.keys(found).length > 0) return;

    setBusy(true);
    try {
      await onSave(next);
    } catch (error) {
      const violation =
        error instanceof ApiError && error.code === 'validation_failed'
          ? (error.body as {violations?: {field: string; message: string}[]})
              .violations?.[0]
          : undefined;
      const field = violation?.field.split('.').at(-1) as FieldName | undefined;
      if (
        violation &&
        field &&
        ['format', 'account_prefix', 'concept_code'].includes(field)
      ) {
        setErrors({[field]: violation.message});
      } else {
        setFailure(
          error instanceof ApiError
            ? t('common.errors.unexpected')
            : t('common.errors.network'),
        );
      }
      setBusy(false);
    }
  };

  return (
    <FormModal
      title={
        row
          ? t('ledger.exogena.form.editTitle')
          : t('ledger.exogena.form.newTitle')
      }
      onClose={onClose}
      onSubmit={() => void submit()}
      busy={busy}
      error={failure}
    >
      <Field label={t('ledger.exogena.columns.format')} error={errors.format}>
        <select value={format} onChange={(e) => setFormat(e.target.value)}>
          {EXOGENA_FORMATS.map((value) => (
            <option key={value} value={value}>
              {t(`ledger.exogena.formats.${value}`)}
            </option>
          ))}
        </select>
      </Field>
      <Field
        label={t('ledger.exogena.columns.prefix')}
        hint={t('ledger.exogena.form.prefixHint')}
        error={errors.account_prefix}
      >
        <input
          type="text"
          inputMode="numeric"
          maxLength={16}
          value={prefix}
          onChange={(e) => setPrefix(e.target.value)}
        />
      </Field>
      <Field
        label={t('ledger.exogena.columns.concept')}
        hint={t('ledger.exogena.form.conceptHint')}
        error={errors.concept_code}
      >
        <input
          type="text"
          inputMode="numeric"
          maxLength={4}
          value={concept}
          onChange={(e) => setConcept(e.target.value)}
        />
      </Field>
    </FormModal>
  );
}
