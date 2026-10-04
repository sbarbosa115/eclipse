<?php

namespace App\Access\UI\Http\Security;

use App\Shared\Domain\Error\NotAllowed;
use App\Shared\UI\Http\Security\SignedInUser;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Warnings on the `security` channel (A09): a refused sign-in (the e-mail typed and the address, never the
 * password) and a refused action (403: who, their role, the request). config/packages/monolog.yaml sends them to the
 * log in production, where the rest is kept only around errors.
 */
#[WithMonologChannel('security')]
final class SecurityLog
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Security $security,
    ) {
    }

    #[AsEventListener(event: LoginFailureEvent::class)]
    public function onSignInRefused(LoginFailureEvent $event): void
    {
        $badge = $event->getPassport()?->hasBadge(UserBadge::class) ? $event->getPassport()->getBadge(UserBadge::class) : null;
        $this->logger->warning('Sign-in refused', [
            'email' => $badge instanceof UserBadge ? $badge->getUserIdentifier() : null,
            'ip' => $event->getRequest()->getClientIp(),
            'reason' => $event->getException()::class,
        ]);
    }

    /** Before the firewall turns an AccessDeniedException into a response (priority 1). */
    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 2)]
    public function onException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();
        $refused = $e instanceof AccessDeniedException
            || $e instanceof NotAllowed
            || ($e instanceof HttpExceptionInterface && 403 === $e->getStatusCode());
        if (!$refused) {
            return;
        }
        $user = $this->security->getUser();
        $request = $event->getRequest();
        $this->logger->warning('Access refused', [
            'user_id' => $user instanceof SignedInUser ? $user->userId()->toRfc4122() : null,
            'company_id' => $user instanceof SignedInUser ? $user->companyId()->toRfc4122() : null,
            'role' => $user instanceof SignedInUser ? $user->role() : null,
            'request' => $request->getMethod().' '.$request->getPathInfo(),
            'ip' => $request->getClientIp(),
        ]);
    }
}
