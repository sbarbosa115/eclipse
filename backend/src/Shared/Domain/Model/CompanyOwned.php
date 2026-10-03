<?php

namespace App\Shared\Domain\Model;

use Symfony\Component\Uid\Uuid;

/**
 * A record that belongs to one company (principle 7: no query crosses companies). Its table has a company_id column,
 * and the Doctrine "company" filter (Shared\Infrastructure\Doctrine\CompanyFilter) keeps every other company's rows
 * out of the signed-in user's queries. Repositories still load by (company, id): the filter is the second lock.
 */
interface CompanyOwned
{
    public function companyId(): Uuid;
}
