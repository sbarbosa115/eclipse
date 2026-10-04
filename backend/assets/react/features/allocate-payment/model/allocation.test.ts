import {
  allocationsOf,
  parseAmount,
  summarize,
  type OpenItem,
} from './allocation';

const item = (id: string, balance: string): OpenItem => ({
  id,
  document: `FE-${id}`,
  issueDate: '2026-09-01',
  dueDate: '2026-10-01',
  amount: '1190000.00',
  balance,
});

describe('reading an amount as a Colombian types it', () => {
  it.each([
    ['595000', '595000.00'],
    ['595000.5', '595000.50'],
    ['595000,5', '595000.50'],
    ['595.000', '595000.00'],
    ['1.190.000,00', '1190000.00'],
    ['$ 1.190.000', '1190000.00'],
    [' 0012 ', '12.00'],
    ['0,01', '0.01'],
  ])('%s is %s pesos', (text, value) => {
    expect(parseAmount(text)).toBe(value);
  });

  it.each(['', 'abc', '1,234,5', '12.345.6', '-5', '1.5.5', '10,123'])(
    '%s is not an amount',
    (text) => {
      expect(parseAmount(text)).toBeNull();
    },
  );
});

describe('the running difference', () => {
  const items = [item('1', '595000.00'), item('2', '300000.00')];

  it('is the amount received minus what was allocated, until it is zero', () => {
    const partly = summarize('695000', items, {'1': '595000', '2': ''});
    expect(partly).toMatchObject({
      allocated: '595000.00',
      difference: '100000.00',
      balanced: false,
    });

    const done = summarize('695.000', items, {'1': '595000', '2': '100000'});
    expect(done).toMatchObject({
      allocated: '695000.00',
      difference: '0.00',
      balanced: true,
      errors: {},
    });
  });

  it('goes negative when more is allocated than received', () => {
    expect(summarize('100', items, {'1': '150'})).toMatchObject({
      difference: '-50.00',
      balanced: false,
    });
  });

  it('flags a row above its balance or that is not an amount', () => {
    const summary = summarize('595000.01', items, {
      '1': '595000.01',
      '2': 'diez',
    });
    expect(summary.errors).toEqual({'1': 'exceeds', '2': 'invalid'});
    expect(summary.balanced).toBe(false);
  });

  it('is never balanced with nothing received', () => {
    expect(summarize('', items, {}).balanced).toBe(false);
    expect(summarize('0', items, {'1': '0'}).balanced).toBe(false);
  });
});

it('sends only the rows with an amount, as decimal strings', () => {
  expect(
    allocationsOf([item('1', '10'), item('2', '10'), item('3', '10')], {
      '1': '5,5',
      '2': '',
      '3': '0',
    }),
  ).toEqual([{id: '1', amount: '5.50'}]);
});
