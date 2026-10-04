import {canConvert, canDecide, canSend, canVoid} from './status';

describe('what can be done with a quotation', () => {
  it('lets an open emitted quotation be sent, decided, converted and voided', () => {
    const open = {status: 'emitted', converted_invoice_id: null};
    expect([
      canSend(open),
      canDecide(open),
      canConvert(open),
      canVoid(open),
    ]).toEqual([true, true, true, true]);
  });

  it('lets a lapsed offer be decided, converted and voided, not sent', () => {
    const expired = {status: 'expired'};
    expect([
      canSend(expired),
      canDecide(expired),
      canConvert(expired),
      canVoid(expired),
    ]).toEqual([false, true, true, true]);
  });

  it('converts an accepted quotation once', () => {
    expect(canConvert({status: 'accepted', converted_invoice_id: null})).toBe(
      true,
    );
    expect(canConvert({status: 'accepted', converted_invoice_id: 'i1'})).toBe(
      false,
    );
    expect(canVoid({status: 'accepted'})).toBe(false);
  });

  it('offers nothing on a draft, a rejected or a voided one', () => {
    for (const status of ['draft', 'rejected', 'voided']) {
      expect([
        canSend({status}),
        canDecide({status}),
        canConvert({status}),
      ]).toEqual([false, false, false]);
    }
  });
});
