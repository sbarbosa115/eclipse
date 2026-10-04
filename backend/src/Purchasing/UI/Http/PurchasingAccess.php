<?php

namespace App\Purchasing\UI\Http;

use App\Purchasing\Domain\Error\PurchaseInvoiceNotFound;
use App\Purchasing\Domain\Error\PurchasingIsReadOnly;
use App\Purchasing\Domain\Error\SupplierFileNotFound;
use App\Shared\UI\Http\Security\Permission;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Component\Uid\Uuid;

/**
 * Who may write purchase documents and that an id in the URL is one. Local until the "access" item's voters land
 * (follow-up F4): who may create, emit and void is Permission::WRITE_DOCUMENTS (§8).
 */
final class PurchasingAccess
{
    /** Who may write purchases: Permission::WRITE_DOCUMENTS (§8). */
    public function mayWrite(SignedInUser $user): void
    {
        if (!Permission::granted($user->role(), Permission::WRITE_DOCUMENTS)) {
            throw new PurchasingIsReadOnly();
        }
    }

    public function invoiceId(string $id): Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : throw new PurchaseInvoiceNotFound();
    }

    public function attachmentId(string $id): Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : throw new SupplierFileNotFound();
    }
}
