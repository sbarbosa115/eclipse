<?php

namespace App\Tests\Functional\Shared;

use App\Party\Domain\Model\Tercero;
use App\Shared\Domain\Fiscal\IdentificationType;
use App\Shared\Domain\Fiscal\PersonType;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\SignsUp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The second lock of tenant isolation: with the "company" filter on, no query returns another company's rows, even
 * one that forgets to say which company it wants. Each context's endpoints prove the first lock (another company's
 * id answers 404) in their own tests.
 */
final class CompanyFilterTest extends ApiTestCase
{
    use SignsUp;

    public function testQueriesSeeOnlyTheSignedInCompanysRows(): void
    {
        $a = Uuid::fromString($this->signUp('ana@a.co', '900123456', 'A')['company_id']);
        $this->signOut();
        $b = Uuid::fromString($this->signUp('beto@b.co', '800197268', 'B')['company_id']);

        $this->save(
            new Tercero($a, PersonType::Company, IdentificationType::Nit, '111', null, 'Cliente de A', new \DateTimeImmutable()),
            new Tercero($b, PersonType::Company, IdentificationType::Nit, '222', null, 'Cliente de B', new \DateTimeImmutable()),
        );

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getFilters()->enable('company')->setParameter('company', bin2hex($a->toBinary()));
        $names = array_map(static fn (Tercero $t) => $t->displayName(), $em->getRepository(Tercero::class)->findAll());

        self::assertSame(['Cliente de A'], $names, 'A query with no company condition still sees only company A.');
    }

    public function testEveryCompanyOwnedRequestRunsWithTheFilterOn(): void
    {
        $this->signUp();
        $this->getJson('/api/v1/me');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertTrue($em->getFilters()->isEnabled('company'), 'A signed-in request turns the company filter on before its controller runs.');
    }
}
