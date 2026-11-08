import {canConvert, canSend, canVoid} from './status';

describe('what can be done with a purchase order', () => {
  it('sends, converts and voids only an emitted one', () => {
    expect(
      [canSend, canConvert, canVoid].map((f) => f({status: 'emitted'})),
    ).toEqual([true, true, true]);
  });

  it.each(['draft', 'converted', 'voided'])(
    'does none of them on a %s one',
    (status) => {
      expect(
        [canSend, canConvert, canVoid].map((f) =>
          f({
            status,
            converted_invoice_id: status === 'converted' ? 'i1' : null,
          }),
        ),
      ).toEqual([false, false, false]);
    },
  );
});
