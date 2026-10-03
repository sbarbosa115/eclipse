import {useState} from 'react';
import {
  describeErrors,
  emptyQuickForm,
  IdentificationFields,
  quickCreateTercero,
  ROLES,
  terceroErrorMessage,
  toQuickPayload,
  validateForm,
  violationsOf,
  type FormErrors,
  type QuickForm,
  type Role,
  type TerceroSummary,
} from '@/entities/tercero';
import {useTranslation} from '@/shared/i18n';
import {Field, FormModal} from '@/shared/ui';

/**
 * Creates a tercero from inside a document form (§4.2 Quick-create): tipo, documento, número, DV, nombres o razón
 * social, correo and roles. `defaultRole` is the role the document needs (cliente on a sale, proveedor on a
 * purchase); `onCreated` gets the new tercero to select it in the document.
 */
export function QuickCreateTerceroModal({
  defaultRole,
  onCreated,
  onClose,
}: {
  defaultRole: Role;
  onCreated: (tercero: TerceroSummary) => void;
  onClose: () => void;
}) {
  const {t} = useTranslation();
  const [form, setForm] = useState<QuickForm>(() =>
    emptyQuickForm(defaultRole),
  );
  const [errors, setErrors] = useState<FormErrors>({});
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const patch = (change: Partial<QuickForm>) =>
    setForm((current) => ({...current, ...change}));

  const toggleRole = (role: string) =>
    patch({
      roles: form.roles.includes(role)
        ? form.roles.filter((r) => r !== role)
        : [...form.roles, role],
    });

  const submit = async () => {
    const found = validateForm(form, true);
    setErrors(describeErrors(found, t));
    setFailure(null);
    if (Object.keys(found).length > 0) return;
    setBusy(true);
    try {
      onCreated(await quickCreateTercero(toQuickPayload(form)));
    } catch (error) {
      const fields = violationsOf(error);
      setErrors(fields);
      setFailure(
        Object.keys(fields).length > 0 ? null : terceroErrorMessage(error, t),
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <FormModal
      title={t('terceros.quick.title')}
      submitLabel={t('terceros.quick.create')}
      busy={busy}
      error={failure}
      onClose={onClose}
      onSubmit={submit}
    >
      <p className="span-2 muted small">{t('terceros.quick.intro')}</p>
      <IdentificationFields value={form} errors={errors} onChange={patch} />
      <Field
        label={t('terceros.form.fields.email')}
        error={errors['email']}
        className="span-2"
      >
        <input
          type="email"
          value={form.email}
          maxLength={180}
          onChange={(e) => patch({email: e.target.value})}
        />
      </Field>
      <fieldset className="span-2 tercero-roles">
        <legend className="admin-field-label">
          {t('terceros.form.sections.roles')}
        </legend>
        {ROLES.map((role) => (
          <label key={role} className="checkbox">
            <input
              type="checkbox"
              checked={form.roles.includes(role)}
              onChange={() => toggleRole(role)}
            />
            {t(`terceros.roles.${role}`)}
          </label>
        ))}
        {errors['roles'] && (
          <span className="field-error">{errors['roles']}</span>
        )}
      </fieldset>
    </FormModal>
  );
}
