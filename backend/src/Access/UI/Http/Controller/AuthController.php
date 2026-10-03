<?php

namespace App\Access\UI\Http\Controller;

use App\Access\Application\Command\SignUp;
use App\Access\UI\Http\Input\SignUpInput;
use App\Access\UI\Http\Output\SessionOutput;
use App\Access\UI\Http\Security\SecurityUser;
use App\Access\UI\Http\Security\SessionOutputs;
use App\Access\UI\Http\Security\UserProvider;
use App\Shared\Application\Command\CommandBus;
use App\Shared\UI\Http\ApiException;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\InputMapper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
        private readonly SessionOutputs $sessions,
    ) {
    }

    /**
     * Creates a company and its owner, and signs the owner in.
     */
    #[Route('/auth/sign-up', methods: ['POST'])]
    #[ApiResponse(SessionOutput::class, status: 201)]
    public function signUp(
        Request $request,
        Security $security,
        UserProvider $users,
        #[Autowire(service: 'limiter.sign_up')]
        RateLimiterFactoryInterface $signUpLimiter,
    ): JsonResponse {
        if (!$signUpLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            throw ApiException::tooManyRequests('too_many_requests', 'Too many sign-ups from this address. Try again later.');
        }
        $input = $this->inputs->map($this->inputs->json($request), SignUpInput::class);
        $this->commands->dispatch(new SignUp($input->companyName, $input->nit, $input->ownerName, $input->email, $input->password));

        /** @var SecurityUser $user */
        $user = $users->loadUserByIdentifier($input->email);
        $security->login($user, 'json_login', 'api');

        return $this->json($this->sessions->of($user), 201);
    }

    /**
     * Who is signed in.
     */
    #[Route('/me', methods: ['GET'])]
    #[ApiResponse(SessionOutput::class)]
    public function me(#[CurrentUser] SecurityUser $user): JsonResponse
    {
        return $this->json($this->sessions->of($user));
    }
}
