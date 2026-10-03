import type {
  QuickTerceroPayload,
  Role,
  Tercero,
  TerceroPayload,
} from '../api/terceroApi';

export const ROLES: readonly Role[] = [
  'cliente',
  'proveedor',
  'empleado',
  'otro',
];

export const PERSON_TYPES = ['persona', 'empresa'] as const;

export const IDENTIFICATION_TYPES = [
  'cc',
  'nit',
  'ce',
  'pasaporte',
  'ti',
  'rc',
  'te',
  'die',
  'pep',
  'ppt',
  'nit_extranjero',
  'nuip',
] as const;

export const VAT_REGIMES = ['responsable', 'no_responsable', 'simple'] as const;

export const FISCAL_RESPONSIBILITIES = [
  'O-13',
  'O-15',
  'O-23',
  'O-47',
  'R-99-PN',
] as const;

export interface PhoneForm {
  indicative: string;
  number: string;
  extension: string;
}

export interface ContactForm {
  /** Set for a contact that already exists, so documents keep pointing at it. */
  id: string | null;
  name: string;
  email: string;
  phone: string;
}

/** The form's state: every field a string or a list, so inputs stay controlled. Keys are the API's. */
export interface TerceroForm {
  person_type: string;
  identification_type: string;
  identification_number: string;
  /** Empty means "compute it". */
  check_digit: string;
  branch_code: string;
  first_names: string;
  last_names: string;
  business_name: string;
  trade_name: string;
  city: string;
  address: string;
  phones: PhoneForm[];
  billing_contact_name: string;
  email: string;
  mobile: string;
  postal_code: string;
  vat_regime: string;
  billing_contact_is_payer: boolean;
  fiscal_responsibilities: string[];
  roles: string[];
  receivable_account_id: string;
  payable_account_id: string;
  contacts: ContactForm[];
}

/** Field name → message. Phone and contact fields are `phones[0].number`, `contacts[1].name`. */
export type FormErrors = Record<string, string>;

export function emptyForm(role?: Role): TerceroForm {
  return {
    person_type: 'empresa',
    identification_type: 'nit',
    identification_number: '',
    check_digit: '',
    branch_code: '0',
    first_names: '',
    last_names: '',
    business_name: '',
    trade_name: '',
    city: '',
    address: '',
    phones: [],
    billing_contact_name: '',
    email: '',
    mobile: '',
    postal_code: '',
    vat_regime: '',
    billing_contact_is_payer: false,
    fiscal_responsibilities: ['R-99-PN'],
    roles: role ? [role] : [],
    receivable_account_id: '',
    payable_account_id: '',
    contacts: [],
  };
}

export function formFromTercero(t: Tercero): TerceroForm {
  return {
    person_type: t.person_type,
    identification_type: t.identification_type,
    identification_number: t.identification_number,
    check_digit: t.check_digit ?? '',
    branch_code: t.branch_code,
    first_names: t.first_names ?? '',
    last_names: t.last_names ?? '',
    business_name: t.business_name ?? '',
    trade_name: t.trade_name ?? '',
    city: t.city ?? '',
    address: t.address ?? '',
    phones: t.phones.map((p) => ({
      indicative: p.indicative,
      number: p.number,
      extension: p.extension ?? '',
    })),
    billing_contact_name: t.billing_contact_name ?? '',
    email: t.email ?? '',
    mobile: t.mobile ?? '',
    postal_code: t.postal_code ?? '',
    vat_regime: t.vat_regime ?? '',
    billing_contact_is_payer: t.billing_contact_is_payer,
    fiscal_responsibilities: [...t.fiscal_responsibilities],
    roles: [...t.roles],
    receivable_account_id: t.receivable_account?.id ?? '',
    payable_account_id: t.payable_account?.id ?? '',
    contacts: t.contacts.map((c) => ({
      id: c.id,
      name: c.name,
      email: c.email ?? '',
      phone: c.phone ?? '',
    })),
  };
}

const blank = (value: string): string | null => {
  const text = value.trim();
  return text === '' ? null : text;
};

/** Persona: nombres y apellidos. Empresa: razón social. */
export const isCompany = (form: {person_type: string}) =>
  form.person_type === 'empresa';

export function toPayload(form: TerceroForm): TerceroPayload {
  const company = isCompany(form);
  return {
    person_type: form.person_type,
    identification_type: form.identification_type,
    identification_number: form.identification_number.trim(),
    check_digit:
      form.identification_type === 'nit' ? blank(form.check_digit) : null,
    branch_code: form.branch_code.trim() || '0',
    first_names: company ? null : blank(form.first_names),
    last_names: company ? null : blank(form.last_names),
    business_name: company ? blank(form.business_name) : null,
    trade_name: blank(form.trade_name),
    city: blank(form.city),
    address: blank(form.address),
    phones: form.phones
      .filter((p) => p.number.trim() !== '')
      .map((p) => ({
        indicative: p.indicative.trim() || '57',
        number: p.number.trim(),
        extension: blank(p.extension),
      })),
    billing_contact_name: blank(form.billing_contact_name),
    email: blank(form.email),
    mobile: blank(form.mobile),
    postal_code: blank(form.postal_code),
    vat_regime: blank(form.vat_regime),
    billing_contact_is_payer: form.billing_contact_is_payer,
    fiscal_responsibilities: form.fiscal_responsibilities,
    roles: form.roles,
    receivable_account_id: blank(form.receivable_account_id),
    payable_account_id: blank(form.payable_account_id),
    contacts: form.contacts.map((c) => ({
      id: c.id,
      name: c.name.trim(),
      email: blank(c.email),
      phone: blank(c.phone),
    })),
  };
}

export interface QuickForm {
  person_type: string;
  identification_type: string;
  identification_number: string;
  check_digit: string;
  first_names: string;
  last_names: string;
  business_name: string;
  email: string;
  roles: string[];
}

export function emptyQuickForm(role?: Role): QuickForm {
  return {
    person_type: 'empresa',
    identification_type: 'nit',
    identification_number: '',
    check_digit: '',
    first_names: '',
    last_names: '',
    business_name: '',
    email: '',
    roles: role ? [role] : [],
  };
}

export function toQuickPayload(form: QuickForm): QuickTerceroPayload {
  const company = isCompany(form);
  return {
    person_type: form.person_type,
    identification_type: form.identification_type,
    identification_number: form.identification_number.trim(),
    check_digit:
      form.identification_type === 'nit' ? blank(form.check_digit) : null,
    first_names: company ? null : blank(form.first_names),
    last_names: company ? null : blank(form.last_names),
    business_name: company ? blank(form.business_name) : null,
    email: form.email.trim(),
    roles: form.roles,
  };
}

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * The checks the server repeats, run first so a form says what is wrong before it sends. Messages are i18n keys of the
 * `terceros.validation` group, translated by the caller.
 */
export function validateForm(
  form: TerceroForm | QuickForm,
  requireEmail = false,
): FormErrors {
  const errors: FormErrors = {};
  if (form.identification_number.trim() === '') {
    errors['identification_number'] = 'required';
  }
  if (isCompany(form)) {
    if (form.business_name.trim() === '') errors['business_name'] = 'required';
  } else if (form.first_names.trim() === '') {
    errors['first_names'] = 'required';
  }
  if (form.check_digit !== '' && !/^\d$/.test(form.check_digit)) {
    errors['check_digit'] = 'checkDigit';
  }
  if (form.email.trim() === '') {
    if (requireEmail) errors['email'] = 'required';
  } else if (!EMAIL.test(form.email.trim())) {
    errors['email'] = 'email';
  }
  if (form.roles.length === 0) errors['roles'] = 'roles';
  if ('contacts' in form) {
    form.contacts.forEach((c, i) => {
      if (c.name.trim() === '') errors[`contacts[${i}].name`] = 'required';
      if (c.email.trim() !== '' && !EMAIL.test(c.email.trim())) {
        errors[`contacts[${i}].email`] = 'email';
      }
    });
    form.phones.forEach((p, i) => {
      if (p.number.trim() === '') errors[`phones[${i}].number`] = 'required';
    });
  }
  return errors;
}
