import {canVoid} from './status';

describe('a purchase invoice status', () => {
  it('offers the void only while nothing is paid', () => {
    expect(canVoid({status: 'emitted', paid_amount: '0.00'})).toBe(true);
    expect(canVoid({status: 'partially_paid', paid_amount: '10.00'})).toBe(
      false,
    );
    expect(canVoid({status: 'draft', paid_amount: '0.00'})).toBe(false);
  });
});
