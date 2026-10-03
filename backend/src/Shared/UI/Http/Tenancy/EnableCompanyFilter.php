<?php

namespace App\Shared\UI\Http\Tenancy;

use App\Shared\UI\Http\Security\SignedInUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Once the firewall knows who is signed in, every query of the request sees only their company's rows. On the
 * controller event, so the (lazy) firewall has authenticated, and before any controller code runs.
 */
#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 1000)]
final class EnableCompanyFilter
{
    /** Shared\Infrastructure\Doctrine\CompanyFilter, as config/packages/doctrine.yaml names it. */
    private const FILTER = 'company';

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        $user = $this->security->getUser();
        $filters = $this->em->getFilters();
        if (!$user instanceof SignedInUser) {
            if ($filters->isEnabled(self::FILTER)) {
                $filters->disable(self::FILTER);
            }

            return;
        }

        $filters->enable(self::FILTER)->setParameter('company', bin2hex($user->companyId()->toBinary()));
    }
}
