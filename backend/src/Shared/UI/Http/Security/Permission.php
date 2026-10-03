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
