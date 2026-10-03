<?php

namespace App\Access\UI\Http\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * A wrong e-mail and a wrong password answer alike (401 invalid_credentials), so the form tells nobody which e-mails
 * have an account. Too many attempts: 429.
 */
final class SignInFailed implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse(['error' => 'too_many_requests', 'message' => 'Too many sign-in attempts. Try again in a minute.'], 429);
        }

        return new JsonResponse(['error' => 'invalid_credentials', 'message' => 'Wrong e-mail or password.'], 401);
    }
}
