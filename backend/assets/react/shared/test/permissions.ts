// The server's role matrix (Shared\UI\Http\Security\Permission::MATRIX), for tests that fake a session by role: the
// UI itself never derives permissions from a role, it reads the list the server sends.
const MATRIX: Record<string, string[]> = {
  owner: [
    'MANAGE_USERS',
    'MANAGE_SETTINGS',
    'MANAGE_BOOKS',
    'VIEW_BOOKS',
    'WRITE_DOCUMENTS',
    'READ_DOCUMENTS',
  ],
  billing: ['WRITE_DOCUMENTS', 'READ_DOCUMENTS'],
  accountant: [
    'MANAGE_BOOKS',
    'VIEW_BOOKS',
    'WRITE_DOCUMENTS',
    'READ_DOCUMENTS',
  ],
};

export function permissionsOf(role: string): string[] {
  return MATRIX[role] ?? [];
}
