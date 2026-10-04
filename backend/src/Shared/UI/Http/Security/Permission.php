<?php

namespace App\Shared\UI\Http\Security;

/**
 * What a signed-in person may do, by name (§8): a controller asks `denyAccessUnlessGranted(Permission::MANAGE_USERS)`
 * and Access\UI\Http\Security\RoleMatrixVoter answers from the person's role. The matrix itself is documented and
 * tested in tests/Unit/Access/RoleMatrixTest.php.
 */
final class Permission
{
    /** Invite users, change their roles, deactivate them (Configuración › Usuarios). */
    public const MANAGE_USERS = 'MANAGE_USERS';

    /** The company profile, the invoicing resolution and the numbering series. */
    public const MANAGE_SETTINGS = 'MANAGE_SETTINGS';

    /** The chart of accounts, posting rules, taxes, payment methods and the lock date. */
    public const MANAGE_BOOKS = 'MANAGE_BOOKS';

    /** The ledger and its reports (libro diario, balance de prueba, statements). */
    public const VIEW_BOOKS = 'VIEW_BOOKS';

    /** Terceros, products and every commercial document: create, edit, emit, void. */
    public const WRITE_DOCUMENTS = 'WRITE_DOCUMENTS';

    /** Every list, document and PDF. */
    public const READ_DOCUMENTS = 'READ_DOCUMENTS';

    /**
     * Who may do what (§8, changed 2026-10-04: the accountant also writes every document). The one copy of the
     * matrix: the voter (Access\UI\Http\Security\RoleMatrixVoter), the local checks and the session the UI reads
     * (SessionOutput::permissions) all ask it. tests/Unit/Access/RoleMatrixTest.php is the table in words.
     */
    public const MATRIX = [
        self::MANAGE_USERS => ['owner'],
        self::MANAGE_SETTINGS => ['owner'],
        self::MANAGE_BOOKS => ['owner', 'accountant'],
        self::VIEW_BOOKS => ['owner', 'accountant'],
        self::WRITE_DOCUMENTS => ['owner', 'billing', 'accountant'],
        self::READ_DOCUMENTS => ['owner', 'billing', 'accountant'],
    ];

    public static function granted(string $role, string $permission): bool
    {
        return \in_array($role, self::MATRIX[$permission] ?? [], true);
    }

    /**
     * Everything this role may do, for the UI.
     *
     * @return list<string>
     */
    public static function of(string $role): array
    {
        return array_values(array_filter(self::ALL, static fn (string $p) => self::granted($role, $p)));
    }

    public const ALL = [
        self::MANAGE_USERS,
        self::MANAGE_SETTINGS,
        self::MANAGE_BOOKS,
        self::VIEW_BOOKS,
        self::WRITE_DOCUMENTS,
        self::READ_DOCUMENTS,
    ];

    private function __construct()
    {
    }
}
