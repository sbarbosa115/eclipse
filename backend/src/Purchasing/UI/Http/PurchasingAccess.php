<?php

namespace App\Purchasing\UI\Http;

use App\Purchasing\Domain\Error\PurchaseInvoiceNotFound;
use App\Purchasing\Domain\Error\PurchasingIsReadOnly;
use App\Purchasing\Domain\Error\SupplierFileNotFound;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Component\Uid\Uuid;

/**
 * Who may write purchase documents and that an id in the URL is one. Local until the "access" item's voters land
 * (follow-up F4): the owner and billing users create, emit and void; the accountant reads (§8, Q23).
 */
final class PurchasingAccess
{
    public function mayWrite(SignedInUser $user): void
    {
        if (!\in_array($user->role(), ['owner', 'billing'], true)) {
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
