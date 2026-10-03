<?php

namespace App\Sales\UI\Http;

use App\Sales\Domain\Error\SalesInvoiceNotFound;
use App\Sales\Domain\Error\SalesInvoicesAreReadOnly;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Component\Uid\Uuid;

/**
 * What the sales invoice controller checks before it acts: who may write (a local rule until the "access" item's
 * voters land, like CatalogAccess), and that an id in the URL is one.
 */
final class SalesInvoiceAccess
{
    /** The owner and billing users write; the accountant reads (§8, §9 Q23). */
    public function mayWrite(SignedInUser $user): void
    {
        if (!\in_array($user->role(), ['owner', 'billing'], true)) {
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
