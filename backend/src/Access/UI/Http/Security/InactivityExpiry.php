<?php

namespace App\Access\UI\Http\Security;

use App\Shared\Domain\Clock;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * §4.14: a signed-in session that sees no request for two hours ends. The time of the last request is kept in the
 * session and checked on every request, right after the firewall (priority 8) has read who is signed in. An expired
 * session is emptied; an API call that needs a signed-in user answers 401 `session_expired` (the UI then shows the
 * sign-in page), a public one (signing in again, a reset link) goes on as if nobody were signed in.
 *
 * The session file's own lifetime (framework.session.gc_maxlifetime) is the same two hours, but PHP collects old
 * files only now and then: this check is what makes the rule exact.
 */
#[AsEventListener(event: KernelEvents::REQUEST, method: 'onRequest', priority: 7)]
#[AsEventListener(event: LoginSuccessEvent::class, method: 'onLogin')]
final class InactivityExpiry
{
    public const MAX_IDLE_SECONDS = 7200;
    private const KEY = '_access_last_activity';
    private const PUBLIC_PATH = '/api/v1/auth/';

    public function __construct(
        private readonly TokenStorageInterface $tokens,
        private readonly Clock $clock,
    ) {
    }

    /** Signing in (with the form, or by accepting an invitation or a reset link) starts the two hours. */
    public function onLogin(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->hasSession()) {
            $request->getSession()->set(self::KEY, $this->clock->now()->getTimestamp());
        }
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), '/api/') || !$request->hasSession()) {
            return;
        }
        if (!$this->tokens->getToken()?->getUser() instanceof SecurityUser) {
            return;
        }

        $session = $request->getSession();
        $now = $this->clock->now()->getTimestamp();
        $last = $session->get(self::KEY);
        if (\is_int($last) && $now - $last >= self::MAX_IDLE_SECONDS) {
            $this->tokens->setToken(null);
            $session->invalidate();
            if (!str_starts_with($request->getPathInfo(), self::PUBLIC_PATH)) {
                $event->setResponse(new JsonResponse(['error' => 'session_expired', 'message' => 'The session ended after two hours without activity. Sign in again.'], 401));
            }

            return;
        }

        $session->set(self::KEY, $now);
    }
}
