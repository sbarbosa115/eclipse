<?php

namespace App\Access\Infrastructure\Persistence;

use App\Access\Domain\Error\UserNotFound;
use App\Access\Domain\Model\Role;
use App\Access\Domain\Model\User;
use App\Access\Domain\Model\UserStatus;
use App\Access\Domain\Repository\UserRepository;
use App\Shared\Infrastructure\Doctrine\CompanyFilter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineUserRepository implements UserRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $companyId, Uuid $id): User
    {
        return $this->em->getRepository(User::class)->findOneBy(['companyId' => $companyId, 'id' => $id]) ?? throw new UserNotFound();
    }

    public function getById(Uuid $id): User
    {
        return $this->acrossCompanies(fn () => $this->em->getRepository(User::class)->find($id)) ?? throw new UserNotFound();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->acrossCompanies(fn () => $this->em->getRepository(User::class)->findOneBy(['email' => User::normalize($email)]));
    }

    public function ofCompany(Uuid $companyId): array
    {
        /* @var list<User> */
        return $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.companyId = :company')
            ->setParameter('company', $companyId, 'uuid')
            // An invitee has no name yet: they sort by e-mail among the others.
            ->addSelect("CASE WHEN u.name = '' THEN u.email ELSE u.name END AS HIDDEN sortName")
            ->orderBy('sortName')
            ->addOrderBy('u.email')
            ->getQuery()
            ->getResult();
    }

    public function countActiveOwners(Uuid $companyId): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.companyId = :company AND u.role = :owner AND u.status = :active')
            ->setParameter('company', $companyId, 'uuid')
            ->setParameter('owner', Role::Owner)
            ->setParameter('active', UserStatus::Active)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function add(User $user): void
    {
        $this->em->persist($user);
    }

    /**
     * E-mails are unique across the whole app, and a link names a user of any company: these two lookups look past
     * the signed-in user's company (the `company` filter), and nothing else does.
     *
     * @template T
     *
     * @param callable(): T $query
     *
     * @return T
     */
    private function acrossCompanies(callable $query): mixed
    {
        $filters = $this->em->getFilters();
        if (!$filters->isEnabled(CompanyFilter::NAME)) {
            return $query();
        }
        $filters->suspend(CompanyFilter::NAME);
        try {
            return $query();
        } finally {
            $filters->restore(CompanyFilter::NAME);
        }
    }
}
