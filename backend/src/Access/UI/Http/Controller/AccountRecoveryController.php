<?php

namespace App\Access\UI\Http\Controller;

use App\Access\Application\Command\AcceptInvitation;
use App\Access\Application\Command\RecordSignIn;
use App\Access\Application\Command\RequestPasswordReset;
use App\Access\Application\Command\ResetPassword;
use App\Access\Application\Query\Invitations;
use App\Access\Application\Query\Users;
use App\Access\Domain\Model\User;
use App\Access\UI\Http\Input\AcceptInvitationInput;
use App\Access\UI\Http\Input\PasswordResetRequestInput;
use App\Access\UI\Http\Input\ResetPasswordInput;
use App\Access\UI\Http\Input\TokenInput;
use App\Access\UI\Http\Output\InvitationOutput;
use App\Access\UI\Http\Output\SessionOutput;
use App\Access\UI\Http\Security\SecurityUser;
use App\Access\UI\Http\Security\SessionOutputs;
use App\Shared\Application\Command\CommandBus;
use App\Shared\UI\Http\ApiException;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\InputMapper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * The public pages an e-mailed link opens: accepting an invitation, and resetting a forgotten password. A link that
 * does not work (unknown, used, replaced, expired, of the other kind) answers 404 `link_invalid`, all alike. Both
 * end signed in.
 */
#[Route('/api/v1/auth')]
final class AccountRecoveryController extends AbstractController
{
    public function __construct(
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
        private readonly Invitations $invitations,
        private readonly Users $users,
        private readonly SessionOutputs $sessions,
        private readonly Security $security,
    ) {
    }

    /**
     * Who is invited, to which company and as what, for the invitation page.
     */
    #[Route('/invitations/lookup', methods: ['POST'])]
    #[ApiResponse(InvitationOutput::class)]
    public function lookup(Request $request): JsonResponse
    {
        $input = $this->inputs->map($this->inputs->json($request), TokenInput::class);
        $view = $this->invitations->lookup($input->token);

        return $this->json(new InvitationOutput($view->email, $view->companyName, $view->role));
    }

    /**
     * The invitee writes their name and a password (≥ 10): their account is active and they are signed in.
     */
    #[Route('/invitations/accept', methods: ['POST'])]
    #[ApiResponse(SessionOutput::class)]
    public function accept(Request $request): JsonResponse
    {
        $input = $this->inputs->map($this->inputs->json($request), AcceptInvitationInput::class);
        $userId = $this->commands->dispatch(new AcceptInvitation($input->token, $input->name, $input->password));
        \assert($userId instanceof Uuid);

        return $this->json($this->signIn($userId));
    }

    /**
     * Sends a reset link (valid one hour) to the e-mail, if it belongs to someone who can sign in. The answer is 202
     * with no body whoever asks, so it tells nobody which e-mails have an account. 429 after 5 requests from one
     * address in 10 minutes; one person gets at most 5 e-mails in 10 minutes, silently.
     */
    #[Route('/password-reset', methods: ['POST'])]
    public function requestReset(
        Request $request,
        #[Autowire(service: 'limiter.password_reset')]
        RateLimiterFactoryInterface $limiter,
    ): Response {
        if (!$limiter->create('ip-'.$request->getClientIp())->consume()->isAccepted()) {
            throw ApiException::tooManyRequests('too_many_requests', 'Too many requests from this address. Try again later.');
        }
        $input = $this->inputs->map($this->inputs->json($request), PasswordResetRequestInput::class);
        if ($limiter->create('email-'.User::normalize($input->email))->consume()->isAccepted()) {
            $this->commands->dispatch(new RequestPasswordReset($input->email));
        }

        return new Response(null, Response::HTTP_ACCEPTED);
    }

    /**
     * 204 while the reset link works, so the page asks for a password only then.
     */
    #[Route('/password-reset/check', methods: ['POST'])]
    public function checkReset(Request $request): Response
    {
        $input = $this->inputs->map($this->inputs->json($request), TokenInput::class);
        $this->invitations->checkPasswordReset($input->token);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Sets the new password (≥ 10) and signs the person in. Every other session of theirs ends.
     */
    #[Route('/password-reset/confirm', methods: ['POST'])]
    #[ApiResponse(SessionOutput::class)]
    public function confirmReset(Request $request): JsonResponse
    {
        $input = $this->inputs->map($this->inputs->json($request), ResetPasswordInput::class);
        $userId = $this->commands->dispatch(new ResetPassword($input->token, $input->password));
        \assert($userId instanceof Uuid);

        return $this->json($this->signIn($userId));
    }

    private function signIn(Uuid $userId): SessionOutput
    {
        $user = SecurityUser::from($this->users->byId($userId));
        $this->security->login($user, 'json_login', 'api');
        $this->commands->dispatch(new RecordSignIn($user->companyId(), $user->userId()));

        return $this->sessions->of($user);
    }
}
