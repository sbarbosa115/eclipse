import {formatDateTime, formatDay} from './dateTime';

describe('dates in Bogotá', () => {
  it('shows an instant in Colombian time, day first', () => {
    expect(formatDateTime('2026-10-03T15:30:00+00:00')).toBe(
      '03/10/2026 10:30',
    );
  });

  it('puts a late-evening instant on the Colombian day', () => {
    expect(formatDay('2026-10-04T02:00:00+00:00')).toBe('03/10/2026');
  });
});
