import {useState} from 'react';
import {Link} from 'react-router-dom';
import {
  FISCAL_RESPONSIBILITIES,
  IdentificationFields,
  RoleCheckboxes,
  VAT_REGIMES,
  type FormErrors,
  type Tercero,
  type TerceroForm,
} from '@/entities/tercero';
import {useTranslation} from '@/shared/i18n';
import {
  ActionButton,
  Alert,
  Button,
  Card,
  Field,
  IconButton,
} from '@/shared/ui';
import {AccountSelect} from './AccountSelect';
import {ConfirmModal} from './ConfirmModal';

const RECEIVABLE_PREFIXES = ['1305'] as const;
const PAYABLE_PREFIXES = ['2205', '2335'] as const;

/** The form's sections (§4.2): roles, basics, billing, fiscal, contacts, accounts, and the Ley 1581 actions. */
export function TerceroFormView({
  form,
  onChange,
  errors,
  failure,
  saved,
  busy,
  readOnly,
  tercero,
  onSubmit,
  onExport,
  onErase,
}: {
  form: TerceroForm;
  onChange: (patch: Partial<TerceroForm>) => void;
  errors: FormErrors;
  failure: string | null;
  saved: boolean;
  busy: boolean;
  readOnly: boolean;
  tercero: Tercero | null;
  onSubmit: () => void;
  onExport: () => Promise<void>;
  onErase: () => Promise<void>;
}) {
  const {t} = useTranslation();
  const [erasing, setErasing] = useState(false);
  const [privacyFailure, setPrivacyFailure] = useState<string | null>(null);

  const text = (field: keyof TerceroForm) => ({
    value: form[field] as string,
    onChange: (e: {target: {value: string}}) =>
      onChange({[field]: e.target.value}),
  });
  const toggle = (list: string[], value: string) =>
    list.includes(value) ? list.filter((v) => v !== value) : [...list, value];

  const setPhone = (index: number, patch: Partial<TerceroForm['phones'][0]>) =>
    onChange({
      phones: form.phones.map((p, i) => (i === index ? {...p, ...patch} : p)),
    });
  const setContact = (
    index: number,
    patch: Partial<TerceroForm['contacts'][0]>,
  ) =>
    onChange({
      contacts: form.contacts.map((c, i) =>
        i === index ? {...c, ...patch} : c,
      ),
    });

  return (
    <form
      className="tercero-form"
      noValidate
      onSubmit={(event) => {
        event.preventDefault();
        if (!readOnly) onSubmit();
      }}
    >
      <Alert kind="error">{failure}</Alert>
      <Alert kind="success">{saved ? t('terceros.form.saved') : null}</Alert>
      <fieldset className="tercero-fieldset" disabled={readOnly}>
        <Card title={t('terceros.form.sections.roles')}>
          <RoleCheckboxes
            value={form.roles}
            onChange={(roles) => onChange({roles})}
            error={errors['roles']}
          />
          <p className="muted small">{t('terceros.form.rolesHint')}</p>
        </Card>

        <Card title={t('terceros.form.sections.basics')}>
          <div className="form-grid">
            <IdentificationFields
              value={form}
              errors={errors}
              onChange={onChange}
            />
            <Field
              label={t('terceros.form.fields.branchCode')}
              error={errors['branch_code']}
            >
              <input
                {...text('branch_code')}
                maxLength={6}
                inputMode="numeric"
              />
            </Field>
            <Field label={t('terceros.form.fields.tradeName')} optional>
              <input {...text('trade_name')} maxLength={200} />
            </Field>
            <Field label={t('terceros.form.fields.city')} optional>
              <input {...text('city')} maxLength={100} />
            </Field>
            <Field label={t('terceros.form.fields.address')} optional>
              <input {...text('address')} maxLength={200} />
            </Field>
          </div>
          <h3 className="admin-field-label">
            {t('terceros.form.fields.phones')}
          </h3>
          {form.phones.map((phone, i) => (
            <div key={i} className="tercero-row">
              <Field label={t('terceros.form.fields.phoneIndicative')}>
                <input
                  value={phone.indicative}
                  maxLength={6}
                  onChange={(e) => setPhone(i, {indicative: e.target.value})}
                />
              </Field>
              <Field
                label={t('terceros.form.fields.phoneNumber')}
                error={errors[`phones[${i}].number`]}
              >
                <input
                  value={phone.number}
                  maxLength={20}
                  onChange={(e) => setPhone(i, {number: e.target.value})}
                />
              </Field>
              <Field label={t('terceros.form.fields.phoneExtension')} optional>
                <input
                  value={phone.extension}
                  maxLength={10}
                  onChange={(e) => setPhone(i, {extension: e.target.value})}
                />
              </Field>
              <IconButton
                icon="close"
                label={t('terceros.form.removePhone', {n: i + 1})}
                onClick={() =>
                  onChange({phones: form.phones.filter((_, j) => j !== i)})
                }
              />
            </div>
          ))}
          <div>
            <ActionButton
              action="setup"
              onClick={() =>
                onChange({
                  phones: [
                    ...form.phones,
                    {indicative: '57', number: '', extension: ''},
                  ],
                })
              }
            >
              {t('terceros.form.addPhone')}
            </ActionButton>
          </div>
        </Card>

        <Card title={t('terceros.form.sections.billing')}>
          <div className="form-grid">
            <Field
              label={t('terceros.form.fields.billingContactName')}
              optional
            >
              <input {...text('billing_contact_name')} maxLength={160} />
            </Field>
            <Field
              label={t('terceros.form.fields.email')}
              error={errors['email']}
              optional
            >
              <input type="email" {...text('email')} maxLength={180} />
            </Field>
            <Field label={t('terceros.form.fields.mobile')} optional>
              <input {...text('mobile')} maxLength={30} />
            </Field>
            <Field label={t('terceros.form.fields.postalCode')} optional>
              <input {...text('postal_code')} maxLength={12} />
            </Field>
            <Field
              label={t('terceros.form.fields.vatRegime')}
              error={errors['vat_regime']}
              optional
            >
              <select {...text('vat_regime')}>
                <option value="">{t('terceros.form.noVatRegime')}</option>
                {VAT_REGIMES.map((regime) => (
                  <option key={regime} value={regime}>
                    {t(`terceros.vatRegimes.${regime}`)}
                  </option>
                ))}
              </select>
            </Field>
            <label className="checkbox">
              <input
                type="checkbox"
                checked={form.billing_contact_is_payer}
                onChange={(e) =>
                  onChange({billing_contact_is_payer: e.target.checked})
                }
              />
              {t('terceros.form.fields.billingContactIsPayer')}
            </label>
          </div>
        </Card>

        <Card title={t('terceros.form.sections.fiscal')}>
          <p className="muted small">{t('terceros.form.fiscalHint')}</p>
          <div className="tercero-checks">
            {FISCAL_RESPONSIBILITIES.map((code) => (
              <label key={code} className="checkbox">
                <input
                  type="checkbox"
                  checked={form.fiscal_responsibilities.includes(code)}
                  onChange={() =>
                    onChange({
                      fiscal_responsibilities: toggle(
                        form.fiscal_responsibilities,
                        code,
                      ),
                    })
                  }
                />
                {t(`terceros.fiscalResponsibilities.${code}`)}
              </label>
            ))}
          </div>
        </Card>

        <Card title={t('terceros.form.sections.contacts')}>
          {form.contacts.length === 0 && (
            <p className="muted small">{t('terceros.form.noContacts')}</p>
          )}
          {form.contacts.map((contact, i) => (
            <div
              key={contact.id ?? `new-${i}`}
              className="tercero-row contact-row"
            >
              <Field
                label={t('terceros.form.fields.contactName')}
                error={errors[`contacts[${i}].name`]}
              >
                <input
                  value={contact.name}
                  maxLength={160}
                  onChange={(e) => setContact(i, {name: e.target.value})}
                />
              </Field>
              <Field
                label={t('terceros.form.fields.contactEmail')}
                error={errors[`contacts[${i}].email`]}
                optional
              >
                <input
                  type="email"
                  value={contact.email}
                  maxLength={180}
                  onChange={(e) => setContact(i, {email: e.target.value})}
                />
              </Field>
              <Field label={t('terceros.form.fields.contactPhone')} optional>
                <input
                  value={contact.phone}
                  maxLength={30}
                  onChange={(e) => setContact(i, {phone: e.target.value})}
                />
              </Field>
              <IconButton
                icon="close"
                label={t('terceros.form.removeContact', {n: i + 1})}
                onClick={() =>
                  onChange({contacts: form.contacts.filter((_, j) => j !== i)})
                }
              />
            </div>
          ))}
          <div>
            <ActionButton
              action="setup"
              onClick={() =>
                onChange({
                  contacts: [
                    ...form.contacts,
                    {id: null, name: '', email: '', phone: ''},
                  ],
                })
              }
            >
              {t('terceros.form.addContact')}
            </ActionButton>
          </div>
        </Card>

        <Card title={t('terceros.form.sections.accounts')}>
          <p className="muted small">{t('terceros.form.accountsHint')}</p>
          <div className="form-grid">
            <AccountSelect
              label={t('terceros.form.fields.receivableAccount')}
              prefixes={RECEIVABLE_PREFIXES}
              value={form.receivable_account_id}
              current={tercero?.receivable_account ?? null}
              onChange={(id) => onChange({receivable_account_id: id})}
              error={errors['receivable_account_id']}
            />
            <AccountSelect
              label={t('terceros.form.fields.payableAccount')}
              prefixes={PAYABLE_PREFIXES}
              value={form.payable_account_id}
              current={tercero?.payable_account ?? null}
              onChange={(id) => onChange({payable_account_id: id})}
              error={errors['payable_account_id']}
            />
          </div>
        </Card>
      </fieldset>

      {tercero && !readOnly && (
        <Card title={t('terceros.form.sections.privacy')}>
          <p className="muted small">{t('terceros.privacy.intro')}</p>
          <Alert kind="error">{privacyFailure}</Alert>
          <div className="tercero-privacy-actions">
            <ActionButton
              action="open"
              size="md"
              onClick={() => {
                setPrivacyFailure(null);
                onExport().catch((error: unknown) =>
                  setPrivacyFailure(
                    error instanceof Error ? error.message : String(error),
                  ),
                );
              }}
            >
              {t('terceros.privacy.export')}
            </ActionButton>
            <ActionButton
              action="danger"
              size="md"
              onClick={() => setErasing(true)}
            >
              {t('terceros.privacy.erase')}
            </ActionButton>
          </div>
        </Card>
      )}

      {!readOnly && (
        <div className="form-actions">
          <Link to="/terceros" className="btn btn-ghost">
            {t('common.cancel')}
          </Link>
          <Button type="submit" busy={busy}>
            {tercero ? t('terceros.form.save') : t('terceros.form.create')}
          </Button>
        </div>
      )}

      {erasing && tercero && (
        <ConfirmModal
          title={t('terceros.confirm.eraseTitle')}
          body={t('terceros.confirm.eraseBody', {name: tercero.display_name})}
          confirmLabel={t('terceros.confirm.eraseConfirm')}
          onClose={() => setErasing(false)}
          onConfirm={async () => {
            await onErase();
            setErasing(false);
          }}
        />
      )}
    </form>
  );
}
