<?php

namespace App\Party\Infrastructure\Query;

use App\Party\Application\Query\TerceroDirectory;
use App\Party\Application\Query\TerceroView;
use App\Party\Domain\Error\TerceroNotFound;
use App\Party\Domain\Model\Contact;
use App\Party\Domain\Model\Tercero;
use App\Shared\Domain\Fiscal\FiscalResponsibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineTerceroDirectory implements TerceroDirectory
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $companyId, Uuid $terceroId): TerceroView
    {
        $t = $this->em->getRepository(Tercero::class)->findOneBy(['companyId' => $companyId, 'id' => $terceroId]) ?? throw new TerceroNotFound();
        $roles = array_keys(array_filter(['cliente' => $t->isClient(), 'proveedor' => $t->isSupplier(), 'empleado' => $t->isEmployee(), 'otro' => $t->isOther()]));

        return new TerceroView(
            $t->id()->toRfc4122(),
            $t->displayName(),
            $t->personType()->value,
            $t->identificationType()->value,
            $t->identificationNumber(),
            $t->checkDigit(),
            $t->email(),
            $t->city(),
            $t->address(),
            $roles,
            array_map(static fn (FiscalResponsibility $r) => $r->value, $t->fiscalResponsibilities()),
            $t->receivableAccountId()?->toRfc4122(),
            $t->payableAccountId()?->toRfc4122(),
            $t->isActive(),
        );
    }

    public function contactName(Uuid $companyId, Uuid $terceroId, Uuid $contactId): ?string
    {
        $contact = $this->em->getRepository(Contact::class)->findOneBy(['companyId' => $companyId, 'id' => $contactId]);

        return null !== $contact && $contact->tercero()->id()->equals($terceroId) ? $contact->name() : null;
    }
}
