import {useState} from 'react';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {
  ActionButton,
  Actions,
  Card,
  DataTable,
  Field,
  FormModal,
  Row,
} from '@/shared/ui';
import type {NumberingSeries} from '../api/resolutionApi';

interface Props {
  series: NumberingSeries[];
  canEdit: boolean;
  onSave: (kind: string, prefix: string, nextNumber: number) => Promise<void>;
}

/** Numeración interna: the prefix and next number of the document series (§4.1). */
export function NumberingCard({series, canEdit, onSave}: Props) {
  const {t} = useTranslation();
  const [editing, setEditing] = useState<NumberingSeries | null>(null);

  return (
    <Card title={t('company.resolution.sections.numbering')}>
      <p className="muted small">{t('company.numbering.intro')}</p>
      <DataTable
        actions={canEdit}
        columns={[
          t('company.numbering.columns.document'),
          t('company.numbering.columns.prefix'),
          t('company.numbering.columns.next'),
        ]}
        rows={series}
        renderRow={(s) => (
          <Row key={s.kind}>
            <td>{t(`company.numbering.kinds.${s.kind}`)}</td>
            <td>{s.prefix}</td>
            <td>{s.next_number}</td>
            {canEdit && (
              <Actions>
                <ActionButton action="edit" onClick={() => setEditing(s)}>
                  {t('company.numbering.edit')}
                </ActionButton>
              </Actions>
            )}
          </Row>
        )}
      />
      {editing && (
        <NumberingForm
          series={editing}
          onClose={() => setEditing(null)}
          onSave={async (prefix, next) => {
            await onSave(editing.kind, prefix, next);
            setEditing(null);
          }}
        />
      )}
    </Card>
  );
}

function NumberingForm({
  series,
  onClose,
  onSave,
}: {
  series: NumberingSeries;
  onClose: () => void;
  onSave: (prefix: string, nextNumber: number) => Promise<void>;
}) {
  const {t} = useTranslation();
  const [prefix, setPrefix] = useState(series.prefix);
  const [next, setNext] = useState(String(series.next_number));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    const found: Record<string, string> = {};
    const value = Number(next);
    if (!/^\d+$/.test(next.trim()) || value < series.next_number)
      found['next_number'] = t('company.numbering.minimum', {
        number: String(series.next_number),
      });
    setErrors(found);
    setFailure(null);
    if (Object.keys(found).length > 0) return;
    setBusy(true);
    try {
      await onSave(prefix.trim(), value);
    } catch (error) {
      setBusy(false);
      if (error instanceof ApiError && error.status === 422) {
        const body = error.body as {
          violations?: {field: string; message: string}[];
        };
        const server: Record<string, string> = {};
        for (const v of body.violations ?? []) server[v.field] = v.message;
        setErrors(server);
      } else {
        setFailure(t('common.errors.unexpected'));
      }
    }
  };

  return (
    <FormModal
      title={t('company.numbering.editTitle', {
        kind: t(`company.numbering.kinds.${series.kind}`),
      })}
      onClose={onClose}
      onSubmit={() => void submit()}
      busy={busy}
      error={failure}
    >
      <Field
        label={t('company.resolution.fields.prefix')}
        error={errors['prefix']}
        optional
      >
        <input
          value={prefix}
          maxLength={10}
          onChange={(e) => setPrefix(e.target.value)}
        />
      </Field>
      <Field
        label={t('company.numbering.columns.next')}
        error={errors['next_number']}
      >
        <input
          inputMode="numeric"
          value={next}
          onChange={(e) => setNext(e.target.value)}
        />
      </Field>
    </FormModal>
  );
}
