import {addDays, todayInColombia} from './today';

describe('todayInColombia', () => {
  it('is still the 3rd in Bogotá at 9 p.m., when UTC is already on the 4th', () => {
    // 2026-10-04T02:00Z is 2026-10-03 21:00 in Bogotá (UTC−5).
    expect(todayInColombia(new Date('2026-10-04T02:00:00Z'))).toBe(
      '2026-10-03',
    );
  });

  it('is the same day at noon', () => {
    expect(todayInColombia(new Date('2026-10-03T17:00:00Z'))).toBe(
      '2026-10-03',
    );
  });

  it('adds days to an ISO date without any time zone in the way', () => {
    expect(addDays('2026-10-03', 30)).toBe('2026-11-02');
    expect(addDays('2026-12-31', 1)).toBe('2027-01-01');
  });
});
