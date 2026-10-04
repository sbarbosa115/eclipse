<?php

namespace App\Access\UI\Http\Security;

use App\Access\Application\Command\RecordSignIn;
use App\Shared\Application\Command\CommandBus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * POST /api/v1/auth/sign-in answers who signed in (SessionOutput), like GET /me.
 */
final class SignInSucceeded implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly SessionOutputs $sessions,
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        if (!$user instanceof SecurityUser) {
            throw new \LogicException('The API firewall signs in SecurityUsers.');
        }
        $this->commands->dispatch(new RecordSignIn($user->companyId(), $user->userId()));

        return new JsonResponse($this->normalizer->normalize($this->sessions->of($user)));
    }
}
