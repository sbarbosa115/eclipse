<?php

namespace App\Access\UI\Http\Security;

use App\Shared\UI\Http\Security\Permission;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * The role matrix of §8: which role may do what, by named permission (Shared\UI\Http\Security\Permission). The
 * owner may do everything; the billing user writes commercial documents; the accountant keeps the books and reads
 * the documents (§9 Q23). tests/Unit/Access/RoleMatrixTest.php is the table in words.
 *
 * A record's company is not this voter's business: repositories load by (company, id) and another company's id is
 * a 404 before anyone asks.
 *
 * @extends Voter<string, mixed>
 */
final class RoleMatrixVoter extends Voter
{
    private const MATRIX = [
        Permission::MANAGE_USERS => ['owner'],
        Permission::MANAGE_SETTINGS => ['owner'],
        Permission::MANAGE_BOOKS => ['owner', 'accountant'],
        Permission::VIEW_BOOKS => ['owner', 'accountant'],
        Permission::WRITE_DOCUMENTS => ['owner', 'billing'],
        Permission::READ_DOCUMENTS => ['owner', 'billing', 'accountant'],
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return isset(self::MATRIX[$attribute]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof SignedInUser && \in_array($user->role(), self::MATRIX[$attribute], true);
    }
}
