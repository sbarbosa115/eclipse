import {canSend, canVoid, statusTone} from './status';

describe('sales invoice status helpers', () => {
  it('voids an emitted invoice only while nothing was collected', () => {
    expect(canVoid({status: 'emitted', paid_amount: '0.00'})).toBe(true);
    expect(canVoid({status: 'paid', paid_amount: '0.00'})).toBe(true);
    expect(canVoid({status: 'partially_paid', paid_amount: '10.00'})).toBe(
      false,
    );
    expect(canVoid({status: 'draft', paid_amount: '0.00'})).toBe(false);
    expect(canVoid({status: 'voided', paid_amount: '0.00'})).toBe(false);
  });

  it('sends only an emitted invoice that was not voided', () => {
    expect(canSend({status: 'emitted'})).toBe(true);
    expect(canSend({status: 'draft'})).toBe(false);
    expect(canSend({status: 'voided'})).toBe(false);
  });

  it('gives every status a tone of the kit', () => {
    expect(statusTone('paid')).toBe('active');
    expect(statusTone('voided')).toBe('cancelled');
    expect(statusTone('unknown')).toBe('neutral');
  });
});
