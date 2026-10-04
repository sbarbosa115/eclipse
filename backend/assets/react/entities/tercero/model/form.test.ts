import {
  emptyForm,
  emptyQuickForm,
  formFromTercero,
  toPayload,
  toQuickPayload,
  validateForm,
} from './form';
import type {Tercero} from '../api/terceroApi';

const ANDINA: Tercero = {
  id: 't1',
  display_name: 'Andina S.A.S.',
  person_type: 'empresa',
  identification_type: 'nit',
  identification_number: '800197268',
  check_digit: '4',
  branch_code: '0',
  business_name: 'Andina S.A.S.',
  phones: [{indicative: '57', number: '6011234567', extension: null}],
  billing_contact_is_payer: false,
  fiscal_responsibilities: ['O-15'],
  roles: ['cliente'],
  receivable_account: {id: 'a1', code: '130505', name: 'Nacionales'},
  contacts: [{id: 'c1', name: 'Pedro', email: null, phone: null}],
  active: true,
  created_at: '2026-10-03T10:00:00+00:00',
};

describe('the tercero form', () => {
  it('starts as a company with the default responsibility and the role it was opened for', () => {
    const form = emptyForm('proveedor');

    expect(form.person_type).toBe('empresa');
    expect(form.fiscal_responsibilities).toEqual(['R-99-PN']);
    expect(form.roles).toEqual(['proveedor']);
    expect(form.branch_code).toBe('0');
  });

  it('turns a tercero into a form and back without losing contact ids', () => {
    const payload = toPayload(formFromTercero(ANDINA));

    expect(payload.contacts).toEqual([
      {id: 'c1', name: 'Pedro', email: null, phone: null},
    ]);
    expect(payload.receivable_account_id).toBe('a1');
    expect(payload.business_name).toBe('Andina S.A.S.');
    expect(payload.phones).toEqual([
      {indicative: '57', number: '6011234567', extension: null},
    ]);
  });

  it('sends blanks as null, and no names of the other kind of person', () => {
    const form = {
      ...emptyForm('cliente'),
      person_type: 'persona',
      identification_type: 'cc',
      identification_number: ' 1020304050 ',
      first_names: 'Ana',
      business_name: 'olvidado',
      check_digit: '7',
    };
    const payload = toPayload(form);

    expect(payload.identification_number).toBe('1020304050');
    expect(payload.business_name).toBeNull();
    expect(payload.check_digit).toBeNull();
    expect(payload.city).toBeNull();
    expect(payload.vat_regime).toBeNull();
  });

  it('drops phones with no number', () => {
    const form = {
      ...emptyForm('cliente'),
      phones: [{indicative: '57', number: ' ', extension: ''}],
    };

    expect(toPayload(form).phones).toEqual([]);
  });

  it('asks for the number, the name and a role', () => {
    expect(validateForm(emptyForm())).toEqual({
      identification_number: 'required',
      business_name: 'required',
      roles: 'roles',
    });
  });

  it('asks for the nombres of a person, not a razón social', () => {
    const form = {...emptyForm('cliente'), person_type: 'persona'};

    expect(validateForm(form)['first_names']).toBe('required');
    expect(validateForm(form)['business_name']).toBeUndefined();
  });

  it('checks e-mails, the DV and the contacts', () => {
    const form = {
      ...emptyForm('cliente'),
      identification_number: '1',
      business_name: 'X',
      email: 'no',
      check_digit: '12',
      contacts: [{id: null, name: '', email: 'x', phone: ''}],
      phones: [{indicative: '57', number: '', extension: ''}],
    };

    expect(validateForm(form)).toEqual({
      'email': 'email',
      'check_digit': 'checkDigit',
      'contacts[0].name': 'required',
      'contacts[0].email': 'email',
      'phones[0].number': 'required',
    });
  });

  it('requires the e-mail only when quick-creating', () => {
    const quick = {
      ...emptyQuickForm('cliente'),
      identification_number: '800197268',
      business_name: 'X',
    };

    expect(validateForm(quick)).toEqual({});
    expect(validateForm(quick, true)).toEqual({email: 'required'});
    expect(toQuickPayload({...quick, email: ' a@b.co '}).email).toBe('a@b.co');
  });
});
