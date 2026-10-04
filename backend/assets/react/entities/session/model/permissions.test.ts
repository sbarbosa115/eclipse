import {can} from './permissions';

describe('can', () => {
  it('follows the permissions the server sent', () => {
    const accountant = {permissions: ['MANAGE_BOOKS', 'WRITE_DOCUMENTS']};

    expect(can(accountant, 'WRITE_DOCUMENTS')).toBe(true);
    expect(can(accountant, 'MANAGE_USERS')).toBe(false);
  });

  it('allows nothing without a session', () => {
    expect(can(null, 'READ_DOCUMENTS')).toBe(false);
  });
});
