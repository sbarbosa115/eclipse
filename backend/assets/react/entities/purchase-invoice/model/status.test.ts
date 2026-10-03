import {canVoid, rowStatus} from './status';

describe('a purchase invoice status', () => {
  it('tints rows by status and offers the void only while nothing is paid', () => {
    expect(rowStatus('draft')).toBe('prospect');
    expect(rowStatus('emitted')).toBeNull();
    expect(rowStatus('voided')).toBe('cancelled');
    expect(canVoid({status: 'emitted', paid_amount: '0.00'})).toBe(true);
    expect(canVoid({status: 'partially_paid', paid_amount: '10.00'})).toBe(
      false,
    );
    expect(canVoid({status: 'draft', paid_amount: '0.00'})).toBe(false);
  });
});
