<?php

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Domain\Model\CompanyOwned;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Adds "company_id = <the signed-in user's company>" to every query on a CompanyOwned entity. Switched on per request
 * by Shared\UI\Http\Tenancy\EnableCompanyFilter; off in console commands and before sign-in.
 */
final class CompanyFilter extends SQLFilter
{
    public const NAME = 'company';

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->getReflectionClass()->implementsInterface(CompanyOwned::class)) {
            return '';
        }

        // Ids are BINARY(16); the parameter is the company id's hex (Shared\UI\Http\Tenancy\EnableCompanyFilter).
        return \sprintf('%s.company_id = UNHEX(%s)', $targetTableAlias, $this->getParameter('company'));
    }
}
