<?php

namespace App\Access\UI\Http\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * POST /api/v1/auth/sign-out answers 204: the UI calls it with fetch, which a redirect (the firewall's default) would
 * follow to the HTML shell.
 */
#[AsEventListener(event: LogoutEvent::class)]
final class SignedOut
{
    public function __invoke(LogoutEvent $event): void
    {
        $event->setResponse(new Response(null, Response::HTTP_NO_CONTENT));
    }
}
