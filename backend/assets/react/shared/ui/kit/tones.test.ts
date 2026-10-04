import {toneFor} from './kit';

describe('the status tones of documents', () => {
  it.each([
    ['draft', 'warning'],
    ['emitted', 'info'],
    ['partially_paid', 'accent'],
    ['paid', 'success'],
    ['accepted', 'success'],
    ['rejected', 'neutral'],
    ['expired', 'neutral'],
    ['voided', 'neutral'],
  ])('%s reads as %s, the same on every document list', (status, tone) => {
    expect(toneFor(status)).toBe(tone);
  });

  it('shows a status it does not know as neutral', () => {
    expect(toneFor('nonsense')).toBe('neutral');
  });
});
