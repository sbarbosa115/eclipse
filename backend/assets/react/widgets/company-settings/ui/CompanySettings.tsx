import './company.css';
import {useCallback, useEffect, useRef, useState} from 'react';
import {can, useSession} from '@/entities/session';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {
  Alert,
  Button,
  Card,
  ErrorState,
  Field,
  Loading,
  TabIntro,
} from '@/shared/ui';
import {
  getCompany,
  listTaxChoices,
  logoUrl,
  removeLogo,
  updateCompany,
  uploadLogo,
  type Company,
  type TaxChoice,
} from '../api/companyApi';
import {
  IDENTIFICATION_TYPES,
  LOGO_MAX_BYTES,
  LOGO_TYPES,
  RESPONSIBILITIES,
  VAT_REGIMES,
  toForm,
  toPayload,
  type CompanyForm,
} from './form';

type Errors = Record<string, string>;
type Notice = {kind: 'success' | 'error'; text: string} | null;

/** Configuración › Empresa: the company's profile (§4.1). The owner edits it; the other roles read it. */
export function CompanySettings() {
  const {t} = useTranslation();
  const {session} = useSession();
  const canEdit = can(session, 'MANAGE_SETTINGS');
  const [company, setCompany] = useState<Company | null>(null);
  const [taxes, setTaxes] = useState<TaxChoice[]>([]);
  const [form, setForm] = useState<CompanyForm | null>(null);
  const [errors, setErrors] = useState<Errors>({});
  const [notice, setNotice] = useState<Notice>(null);
  const [failed, setFailed] = useState(false);
  const [busy, setBusy] = useState(false);
  const [logoBusy, setLogoBusy] = useState(false);
  const picker = useRef<HTMLInputElement>(null);

  const [reads, setReads] = useState(0);
  const reload = useCallback(() => setReads((n) => n + 1), []);
  useEffect(() => {
    let current = true;
    Promise.all([getCompany(), listTaxChoices()]).then(
      ([loaded, choices]) => {
        if (!current) return;
        setCompany(loaded);
        setTaxes(choices);
        setForm(toForm(loaded));
        setFailed(false);
      },
      () => current && setFailed(true),
    );
    return () => {
      current = false;
    };
  }, [reads]);

  if (failed)
    return <ErrorState message={t('common.loadFailed')} onRetry={reload} />;
  if (company === null || form === null) return <Loading />;

  const change = (patch: Partial<CompanyForm>) => setForm({...form, ...patch});
  const text = (name: keyof CompanyForm & string) => ({
    value: form[name] as string,
    onChange: (
      e: React.ChangeEvent<
        HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
      >,
    ) => change({[name]: e.target.value}),
  });

  const taxOptions = (taxClass: string, currentId: string) =>
    taxes.filter(
      (tax) =>
        tax.tax_class === taxClass && (tax.active || tax.id === currentId),
    );

  const save = async () => {
    const found: Errors = {};
    if (form.legal_name.trim() === '')
      found['legal_name'] = t('company.errors.required');
    if (form.identification_number.trim() === '')
      found['identification_number'] = t('company.errors.required');
    setErrors(found);
    setNotice(null);
    if (Object.keys(found).length > 0) return;
    setBusy(true);
    try {
      const saved = await updateCompany(toPayload(form));
      setCompany(saved);
      setForm(toForm(saved));
      setNotice({kind: 'success', text: t('company.notice.saved')});
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        const body = error.body as {
          violations?: {field: string; message: string}[];
        };
        const server: Errors = {};
        for (const v of body.violations ?? []) server[v.field] = v.message;
        setErrors(server);
      } else {
        setNotice({kind: 'error', text: explain(error)});
      }
    } finally {
      setBusy(false);
    }
  };

  const explain = (error: unknown): string => {
    const code = error instanceof ApiError ? error.code : '';
    return ['forbidden', 'logo_unsupported', 'logo_too_large'].includes(code)
      ? t(`company.errors.${code}`)
      : t('common.errors.unexpected');
  };

  const chooseLogo = async (file: File | undefined) => {
    if (!file) return;
    setNotice(null);
    if (!LOGO_TYPES.includes(file.type)) {
      setNotice({kind: 'error', text: t('company.errors.logo_unsupported')});
      return;
    }
    if (file.size > LOGO_MAX_BYTES) {
      setNotice({kind: 'error', text: t('company.errors.logo_too_large')});
      return;
    }
    setLogoBusy(true);
    try {
      setCompany(await uploadLogo(file));
      setNotice({kind: 'success', text: t('company.notice.logoSaved')});
    } catch (error) {
      setNotice({kind: 'error', text: explain(error)});
    } finally {
      setLogoBusy(false);
      if (picker.current) picker.current.value = '';
    }
  };

  const dropLogo = async () => {
    setLogoBusy(true);
    try {
      await removeLogo();
      setCompany({...company, logo_id: null});
      setNotice({kind: 'success', text: t('company.notice.logoRemoved')});
    } catch (error) {
      setNotice({kind: 'error', text: explain(error)});
    } finally {
      setLogoBusy(false);
    }
  };

  const toggleResponsibility = (code: string) =>
    change({
      fiscal_responsibilities: form.fiscal_responsibilities.includes(code)
        ? form.fiscal_responsibilities.filter((c) => c !== code)
        : [...form.fiscal_responsibilities, code],
    });

  const isNit = form.identification_type === 'nit';

  return (
    <>
      <TabIntro>
        {canEdit ? t('company.intro') : t('company.readOnly')}
      </TabIntro>
      <Alert kind={notice?.kind ?? 'info'} onDismiss={() => setNotice(null)}>
        {notice?.text}
      </Alert>
      <form
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          void save();
        }}
      >
        <fieldset disabled={!canEdit || busy} className="company-fieldset">
          <Card title={t('company.sections.identity')}>
            <div className="form-grid">
              <Field
                label={t('company.fields.legalName')}
                error={errors['legal_name']}
                className="span-2"
              >
                <input maxLength={180} {...text('legal_name')} />
              </Field>
              <Field
                label={t('company.fields.tradeName')}
                error={errors['trade_name']}
                className="span-2"
                optional
              >
                <input maxLength={180} {...text('trade_name')} />
              </Field>
              <Field
                label={t('company.fields.identificationType')}
                error={errors['identification_type']}
              >
                <select {...text('identification_type')}>
                  {IDENTIFICATION_TYPES.map((type) => (
                    <option key={type} value={type}>
                      {t(`company.identificationTypes.${type}`)}
                    </option>
                  ))}
                </select>
              </Field>
              <Field
                label={t('company.fields.identificationNumber')}
                error={errors['identification_number']}
              >
                <input maxLength={20} {...text('identification_number')} />
              </Field>
              {isNit && (
                <Field
                  label={t('company.fields.checkDigit')}
                  hint={t('company.hints.checkDigit')}
                  error={errors['check_digit']}
                  optional
                >
                  <input
                    maxLength={1}
                    inputMode="numeric"
                    {...text('check_digit')}
                  />
                </Field>
              )}
            </div>
          </Card>

          <Card title={t('company.sections.contact')}>
            <div className="form-grid">
              <Field
                label={t('company.fields.address')}
                error={errors['address']}
                className="span-2"
                optional
              >
                <input maxLength={200} {...text('address')} />
              </Field>
              <Field
                label={t('company.fields.city')}
                error={errors['city']}
                optional
              >
                <input maxLength={100} {...text('city')} />
              </Field>
              <Field
                label={t('company.fields.phone')}
                error={errors['phone']}
                optional
              >
                <input maxLength={40} inputMode="tel" {...text('phone')} />
              </Field>
              <Field
                label={t('company.fields.email')}
                error={errors['email']}
                className="span-2"
                optional
              >
                <input type="email" maxLength={180} {...text('email')} />
              </Field>
            </div>
          </Card>

          <Card title={t('company.sections.fiscal')}>
            <div className="form-grid">
              <Field
                label={t('company.fields.vatRegime')}
                error={errors['vat_regime']}
                className="span-2"
              >
                <select {...text('vat_regime')}>
                  {VAT_REGIMES.map((regime) => (
                    <option key={regime} value={regime}>
                      {t(`company.vatRegimes.${regime}`)}
                    </option>
                  ))}
                </select>
              </Field>
            </div>
            <fieldset className="company-responsibilities">
              <legend className="admin-field-label">
                {t('company.fields.responsibilities')}
              </legend>
              <p className="muted small">
                {t('company.hints.responsibilities')}
              </p>
              {RESPONSIBILITIES.map((code) => (
                <label key={code} className="checkbox">
                  <input
                    type="checkbox"
                    checked={form.fiscal_responsibilities.includes(code)}
                    onChange={() => toggleResponsibility(code)}
                  />
                  {t(`company.responsibilitiesList.${code}`)}
                </label>
              ))}
              {errors['fiscal_responsibilities[0]'] && (
                <span className="field-error">
                  {errors['fiscal_responsibilities[0]']}
                </span>
              )}
            </fieldset>
          </Card>

          <Card title={t('company.sections.defaultTaxes')}>
            <p className="muted small">{t('company.hints.defaultTaxes')}</p>
            <div className="form-grid">
              <Field
                label={t('company.fields.defaultChargeTax')}
                error={errors['default_charge_tax_id']}
              >
                <select {...text('default_charge_tax_id')}>
                  <option value="">{t('company.noTax')}</option>
                  {taxOptions('charge', form.default_charge_tax_id).map(
                    (tax) => (
                      <option key={tax.id} value={tax.id}>
                        {tax.name}
                      </option>
                    ),
                  )}
                </select>
              </Field>
              <Field
                label={t('company.fields.defaultWithholdingTax')}
                error={errors['default_withholding_tax_id']}
              >
                <select {...text('default_withholding_tax_id')}>
                  <option value="">{t('company.noTax')}</option>
                  {taxOptions(
                    'withholding',
                    form.default_withholding_tax_id,
                  ).map((tax) => (
                    <option key={tax.id} value={tax.id}>
                      {tax.name}
                    </option>
                  ))}
                </select>
              </Field>
            </div>
          </Card>
        </fieldset>
        {canEdit && (
          <div className="form-actions">
            <Button type="submit" busy={busy}>
              {t('company.save')}
            </Button>
          </div>
        )}
      </form>

      <Card title={t('company.sections.logo')}>
        {company.logo_id ? (
          <img
            className="company-logo"
            src={logoUrl(company.logo_id)}
            alt={t('company.logo.alt')}
          />
        ) : (
          <p className="muted">{t('company.logo.none')}</p>
        )}
        <p className="muted small">{t('company.logo.hint')}</p>
        {canEdit && (
          <div className="form-actions company-logo-actions">
            <input
              ref={picker}
              type="file"
              accept="image/png,image/jpeg"
              hidden
              aria-label={t('company.logo.choose')}
              onChange={(e) => void chooseLogo(e.target.files?.[0])}
            />
            <Button
              variant="secondary"
              busy={logoBusy}
              onClick={() => picker.current?.click()}
            >
              {company.logo_id
                ? t('company.logo.replace')
                : t('company.logo.upload')}
            </Button>
            {company.logo_id && (
              <Button
                variant="ghost"
                disabled={logoBusy}
                onClick={() => void dropLogo()}
              >
                {t('company.logo.remove')}
              </Button>
            )}
          </div>
        )}
      </Card>
    </>
  );
}
