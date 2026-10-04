<?php

namespace App\Catalog\UI\Http;

use App\Catalog\Domain\Error\CatalogIsReadOnly;
use App\Catalog\Domain\Error\CategoryNotFound;
use App\Catalog\Domain\Error\ProductNotFound;
use App\Shared\UI\Http\Security\Permission;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Component\Uid\Uuid;

/** What both catalog controllers check before they act: who may write, and that an id in the URL is one. */
final class CatalogAccess
{
    /** Who may write the catalog: Permission::WRITE_DOCUMENTS (§8). */
    public function mayWrite(SignedInUser $user): void
    {
        if (!Permission::granted($user->role(), Permission::WRITE_DOCUMENTS)) {
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

    /** An optional id: null or "" (an empty select) is none. */
    public static function optionalUuid(?string $id): ?Uuid
    {
        return null === $id || '' === $id ? null : Uuid::fromString($id);
    }
}
