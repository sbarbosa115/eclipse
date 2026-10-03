<?php

namespace App\Catalog\UI\Http;

use App\Catalog\Domain\Error\CatalogIsReadOnly;
use App\Catalog\Domain\Error\CategoryNotFound;
use App\Catalog\Domain\Error\ProductNotFound;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Component\Uid\Uuid;

/** What both catalog controllers check before they act: who may write, and that an id in the URL is one. */
final class CatalogAccess
{
    /** The owner and billing users write the catalog; the accountant reads it (§8). */
    public function mayWrite(SignedInUser $user): void
    {
        if (!\in_array($user->role(), ['owner', 'billing'], true)) {
            throw new CatalogIsReadOnly();
        }
    }

    public function productId(string $id): Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : throw new ProductNotFound();
    }

    public function categoryId(string $id): Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : throw new CategoryNotFound();
    }

    public static function optionalUuid(?string $id): ?Uuid
    {
        return null === $id ? null : Uuid::fromString($id);
    }
}
