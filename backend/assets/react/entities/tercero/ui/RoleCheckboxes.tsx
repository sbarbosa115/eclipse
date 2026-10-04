import {useTranslation} from '@/shared/i18n';
import {ROLES} from '../model/form';

/** The four roles of a tercero, any combination. */
export function RoleCheckboxes({
  value,
  onChange,
  error,
  disabled = false,
}: {
  value: readonly string[];
  onChange: (roles: string[]) => void;
  error?: string;
  disabled?: boolean;
}) {
  const {t} = useTranslation();
  const toggle = (role: string) =>
    onChange(
      value.includes(role) ? value.filter((r) => r !== role) : [...value, role],
    );
  return (
    <fieldset className="tercero-roles" disabled={disabled}>
      <legend className="admin-field-label">
        {t('terceros.form.sections.roles')}
      </legend>
      {ROLES.map((role) => (
        <label key={role} className="checkbox">
          <input
            type="checkbox"
            checked={value.includes(role)}
            onChange={() => toggle(role)}
          />
          {t(`terceros.roles.${role}`)}
        </label>
      ))}
      {error && <span className="field-error">{error}</span>}
    </fieldset>
  );
}
