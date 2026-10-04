<?php

namespace App\Access\UI\Http\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The API is signed in with a cookie, so a write must come from the app's own pages: a browser says where a request
 * comes from (Origin, Sec-Fetch-Site), and a write from another site is refused before the firewall runs. The
 * SameSite=Lax cookie already keeps it out of cross-site requests; this is the second lock. Signing out is a write
 * too: the firewall would sign out on any method.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 16)]
final class SameOriginWrites
{
    private const SIGN_OUT = '/api/v1/auth/sign-out';

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        if (self::SIGN_OUT === $request->getPathInfo() && !$request->isMethod('POST')) {
            $event->setResponse(new JsonResponse(['error' => 'method_not_allowed', 'message' => 'Sign out with POST.'], 405, ['Allow' => 'POST']));

            return;
        }
        if ($request->isMethodSafe()) {
            return;
        }

        $origin = $request->headers->get('Origin');
        $site = $request->headers->get('Sec-Fetch-Site');
        $foreign = (null !== $origin && $origin !== $request->getSchemeAndHttpHost())
            || \in_array($site, ['cross-site', 'same-site'], true);
        if ($foreign) {
            $event->setResponse(new JsonResponse(['error' => 'forbidden', 'message' => 'This request must come from the app itself.'], 403));
        }
    }
}
