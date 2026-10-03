<?php

namespace App\Access\UI\Http\Controller;

use App\Access\Application\Command\ChangeUserRole;
use App\Access\Application\Command\InviteUser;
use App\Access\Application\Command\ResendInvitation;
use App\Access\Application\Command\SetUserActive;
use App\Access\Application\Query\UserDirectory;
use App\Access\Domain\Error\UserNotFound;
use App\Access\Domain\Model\Role;
use App\Access\UI\Http\Input\ChangeRoleInput;
use App\Access\UI\Http\Input\InviteUserInput;
use App\Access\UI\Http\Output\UserOutput;
use App\Shared\Application\Command\CommandBus;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\InputMapper;
use App\Shared\UI\Http\Security\Permission;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Configuración › Usuarios (§4.14): the owner lists the company's users, invites people, changes roles and
 * deactivates. The last active owner is never demoted or deactivated (409 `last_owner`). Every change is audited.
 */
#[Route('/api/v1/users')]
#[IsGranted(Permission::MANAGE_USERS)]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
        private readonly UserDirectory $directory,
    ) {
    }

    /**
     * Every user of the company, invitees and deactivated ones included, by name.
     */
    #[Route('', methods: ['GET'])]
    #[ApiResponse(UserOutput::class, key: 'items', list: true)]
    public function list(#[CurrentUser] SignedInUser $user): JsonResponse
    {
        $viewer = $user->userId()->toRfc4122();

        return $this->json(['items' => array_map(static fn ($u) => UserOutput::of($u, $viewer), $this->directory->ofCompany($user->companyId()))]);
    }

    /**
     * Invites someone by e-mail as a billing user or an accountant: they get a link that works once, for seven days.
     * An e-mail registered anywhere in Mustang is refused (422 on `email`).
     */
    #[Route('/invitations', methods: ['POST'])]
    #[ApiResponse(UserOutput::class, status: 201)]
    public function invite(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $input = $this->inputs->map($this->inputs->json($request), InviteUserInput::class);
        $id = $this->commands->dispatch(new InviteUser($user->companyId(), $user->userId(), $input->email, Role::from($input->role)));
        \assert($id instanceof Uuid);

        return $this->json($this->output($user, $id), 201);
    }

    /**
     * Sends the invitation again with a new link; the earlier link stops working. 409 `not_an_invitation` once they
     * accepted.
     */
    #[Route('/{id}/invitation', methods: ['POST'])]
    #[ApiResponse(UserOutput::class)]
    public function resend(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $userId = self::id($id);
        $this->commands->dispatch(new ResendInvitation($user->companyId(), $user->userId(), $userId));

        return $this->json($this->output($user, $userId));
    }

    /**
     * Changes a user's role (owner, billing, accountant). Their session ends on its next request, so they sign in
     * again with the new role.
     */
    #[Route('/{id}/role', methods: ['PUT'])]
    #[ApiResponse(UserOutput::class)]
    public function changeRole(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $userId = self::id($id);
        $input = $this->inputs->map($this->inputs->json($request), ChangeRoleInput::class);
        $this->commands->dispatch(new ChangeUserRole($user->companyId(), $user->userId(), $userId, Role::from($input->role)));

        return $this->json($this->output($user, $userId));
    }

    /**
     * The user may not sign in any more, and their open session ends on its next request. Their name stays on what
     * they made. 409 `cannot_deactivate_yourself`, `last_owner`.
     */
    #[Route('/{id}/deactivate', methods: ['POST'])]
    #[ApiResponse(UserOutput::class)]
    public function deactivate(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->setActive($id, false, $user);
    }

    /**
     * Back as they were: active, or invited if they never accepted (then resend the invitation).
     */
    #[Route('/{id}/reactivate', methods: ['POST'])]
    #[ApiResponse(UserOutput::class)]
    public function reactivate(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->setActive($id, true, $user);
    }

    private function setActive(string $id, bool $active, SignedInUser $user): JsonResponse
    {
        $userId = self::id($id);
        $this->commands->dispatch(new SetUserActive($user->companyId(), $user->userId(), $userId, $active));

        return $this->json($this->output($user, $userId));
    }

    private function output(SignedInUser $viewer, Uuid $userId): UserOutput
    {
        return UserOutput::of($this->directory->get($viewer->companyId(), $userId), $viewer->userId()->toRfc4122());
    }

    /** An id that is not a UUID is as unknown as one nobody owns. */
    private static function id(string $id): Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : throw new UserNotFound();
    }
}
