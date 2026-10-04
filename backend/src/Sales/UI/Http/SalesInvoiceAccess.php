<?php

namespace App\Sales\UI\Http;

use App\Sales\Domain\Error\SalesInvoiceNotFound;
use App\Sales\Domain\Error\SalesInvoicesAreReadOnly;
use App\Shared\UI\Http\Security\Permission;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Component\Uid\Uuid;

/**
 * What the sales invoice controller checks before it acts: who may write (a local rule until the "access" item's
 * voters land, like CatalogAccess), and that an id in the URL is one.
 */
final class SalesInvoiceAccess
{
    /** Who may write sales invoices: Permission::WRITE_DOCUMENTS (§8). */
    public function mayWrite(SignedInUser $user): void
    {
        if (!Permission::granted($user->role(), Permission::WRITE_DOCUMENTS)) {
            throw new SalesInvoicesAreReadOnly();
        }
    }

    public function invoiceId(string $id): Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : throw new SalesInvoiceNotFound();
    }

    public static function optionalUuid(?string $id): ?Uuid
    {
        return null === $id || '' === $id ? null : Uuid::fromString($id);
    }
}
