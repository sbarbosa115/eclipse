<?php

namespace App\Ledger\UI\Http\Controller;

use App\Ledger\Domain\Error\CatalogEditNotAllowed;
use App\Shared\UI\Http\Security\SignedInUser;

/** Who may change the taxes and payment methods: the owner and the accountant (§8). */
trait EditsCatalogs
{
    private function requireCatalogEditor(SignedInUser $user): void
    {
        if (!\in_array($user->role(), ['owner', 'accountant'], true)) {
            throw new CatalogEditNotAllowed();
        }
    }
}
