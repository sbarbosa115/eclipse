<?php

namespace App\Ledger\UI\Http\Security;

use App\Shared\UI\Http\ApiException;
use App\Shared\UI\Http\Security\SignedInUser;

/**
 * Who may do what with the books (§8): the owner and the accountant edit the chart, the posting rules and the fecha
 * de bloqueo, and read the libro diario and the reports; the billing user reads the chart and the settings only.
 * Kept local to the ledger's endpoints: the "access" item builds the app's general role matrix.
 */
final class BooksAccess
{
    private const KEEPERS = ['owner', 'accountant'];

    public static function mayKeepTheBooks(SignedInUser $user): bool
    {
        return \in_array($user->role(), self::KEEPERS, true);
    }

    /** @throws ApiException 403 */
    public static function assertBookkeeper(SignedInUser $user): void
    {
        if (!self::mayKeepTheBooks($user)) {
            throw new ApiException(403, 'forbidden', 'Only the owner and the accountant keep the books.');
        }
    }
}
