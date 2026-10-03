import './resolution.css';
import {useState} from 'react';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {formatDate} from '@/shared/lib';
import {Alert, Button, Card, Field} from '@/shared/ui';
import type {ResolutionPayload, ResolutionSettings} from '../api/resolutionApi';

type Errors = Record<string, string>;

interface FormState {
  resolution_number: string;
  prefix: string;
  range_from: string;
  range_to: string;
  valid_from: string;
  valid_to: string;
  mode: string;
}

const fromSettings = (settings: ResolutionSettings): FormState => {
  const r = settings.resolution;
  return {
    resolution_number: r?.resolution_number ?? '',
    prefix: r?.prefix ?? '',
    range_from: r ? String(r.range_from) : '',
    range_to: r ? String(r.range_to) : '',
    valid_from: r?.valid_from ?? '',
    valid_to: r?.valid_to ?? '',
    mode: r?.mode ?? 'electronic',
  };
};

interface Props {
  settings: ResolutionSettings;
  canEdit: boolean;
  /** Confirming the DIAN permission is the owner's, from the tab. */
  onAskManualConfirmation: () => void;
  onSave: (payload: ResolutionPayload, creating: boolean) => Promise<void>;
}

/** The resolution's form: creates it the first time, edits it afterwards. */
export function ResolutionForm({
  settings,
  canEdit,
  onAskManualConfirmation,
  onSave,
}: Props) {
  const {t} = useTranslation();
  const creating = settings.resolution === null;
  const locked = settings.resolution?.has_issued_numbers ?? false;
  const manualAllowed = settings.manual_invoicing_confirmed_at != null;
  const [form, setForm] = useState<FormState>(() => fromSettings(settings));
  const [errors, setErrors] = useState<Errors>({});
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const text = (name: keyof FormState) => ({
    value: form[name],
    onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
      setForm({...form, [name]: e.target.value}),
  });

  const validate = (): Errors => {
    const found: Errors = {};
    const required = t('company.errors.required');
    if (form.resolution_number.trim() === '')
      found['resolution_number'] = required;
    for (const key of ['range_from', 'range_to'] as const) {
      if (!/^\d+$/.test(form[key].trim()) || Number(form[key]) < 1)
        found[key] = required;
    }
    if (form.valid_from === '') found['valid_from'] = required;
    if (form.valid_to === '') found['valid_to'] = required;
    if (!found['range_to'] && Number(form.range_to) < Number(form.range_from))
      found['range_to'] = t('company.resolution.errors.invalid');
    if (form.valid_from && form.valid_to && form.valid_to < form.valid_from)
      found['valid_to'] = t('company.resolution.errors.invalid');
    return found;
  };

  const submit = async () => {
    const found = validate();
    setErrors(found);
    setFailure(null);
    if (Object.keys(found).length > 0) return;
    setBusy(true);
    try {
      await onSave(
        {
          resolution_number: form.resolution_number.trim(),
          prefix: form.prefix.trim(),
          range_from: Number(form.range_from),
          range_to: Number(form.range_to),
          valid_from: form.valid_from,
          valid_to: form.valid_to,
          mode: form.mode,
        },
        creating,
      );
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        const body = error.body as {
          violations?: {field: string; message: string}[];
        };
        const server: Errors = {};
        for (const v of body.violations ?? []) server[v.field] = v.message;
        setErrors(server);
      } else if (error instanceof ApiError && error.code === 'forbidden') {
        setFailure(t('company.resolution.errors.forbidden'));
      } else if (error instanceof ApiError) {
        const known = ['resolution_exists', 'resolution_not_found'];
        setFailure(
          known.includes(error.code)
            ? t(`company.resolution.errors.${error.code}`)
            : t('common.errors.unexpected'),
        );
      } else {
        setFailure(t('common.errors.unexpected'));
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <Card title={t('company.resolution.sections.resolution')}>
      <Alert kind="error">{failure}</Alert>
      {locked && (
        <p className="muted small">{t('company.resolution.hints.locked')}</p>
      )}
      <form
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          void submit();
        }}
      >
        <fieldset disabled={!canEdit || busy} className="resolution-fieldset">
          <div className="form-grid">
            <Field
              label={t('company.resolution.fields.resolutionNumber')}
              error={errors['resolution_number']}
            >
              <input maxLength={40} {...text('resolution_number')} />
            </Field>
            <Field
              label={t('company.resolution.fields.prefix')}
              error={errors['prefix']}
              optional
            >
              <input maxLength={10} disabled={locked} {...text('prefix')} />
            </Field>
            <Field
              label={t('company.resolution.fields.rangeFrom')}
              error={errors['range_from']}
            >
              <input
                inputMode="numeric"
                disabled={locked}
                {...text('range_from')}
              />
            </Field>
            <Field
              label={t('company.resolution.fields.rangeTo')}
              error={errors['range_to']}
            >
              <input inputMode="numeric" {...text('range_to')} />
            </Field>
            <Field
              label={t('company.resolution.fields.validFrom')}
              error={errors['valid_from']}
            >
              <input type="date" {...text('valid_from')} />
            </Field>
            <Field
              label={t('company.resolution.fields.validTo')}
              error={errors['valid_to']}
            >
              <input type="date" {...text('valid_to')} />
            </Field>
            <Field
              label={t('company.resolution.fields.mode')}
              hint={
                manualAllowed
                  ? t('company.resolution.manual.confirmedAt', {
                      date: formatDate(settings.manual_invoicing_confirmed_at),
                    })
                  : t('company.resolution.hints.manualLocked')
              }
              error={errors['mode']}
              className="span-2"
            >
              <select {...text('mode')}>
                <option value="electronic">
                  {t('company.resolution.modes.electronic')}
                </option>
                <option value="manual" disabled={!manualAllowed}>
                  {t('company.resolution.modes.manual')}
                </option>
              </select>
            </Field>
            {creating ? null : (
              <Field
                label={t('company.resolution.fields.nextNumber')}
                className="span-2"
              >
                <input
                  readOnly
                  value={String(settings.resolution?.next_number ?? '')}
                />
              </Field>
            )}
          </div>
        </fieldset>
        {canEdit && (
          <div className="form-actions">
            {!manualAllowed && (
              <Button variant="ghost" onClick={onAskManualConfirmation}>
                {t('company.resolution.manual.button')}
              </Button>
            )}
            <Button type="submit" busy={busy}>
              {creating
                ? t('company.resolution.create')
                : t('company.resolution.save')}
            </Button>
          </div>
        )}
      </form>
    </Card>
  );
}
