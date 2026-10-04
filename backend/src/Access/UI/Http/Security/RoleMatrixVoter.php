<?php

namespace App\Access\UI\Http\Security;

use App\Shared\UI\Http\Security\Permission;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * The role matrix of §8: which role may do what, by named permission (Shared\UI\Http\Security\Permission). The
 * owner may do everything; the billing user writes commercial documents; the accountant keeps the books and, since
 * 2026-10-04 (the user's decision), also writes every document. Users and the company settings stay the owner's. tests/Unit/Access/RoleMatrixTest.php is the table in words.
 *
 * A record's company is not this voter's business: repositories load by (company, id) and another company's id is
 * a 404 before anyone asks.
 *
 * @extends Voter<string, mixed>
 */
final class RoleMatrixVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return isset(Permission::MATRIX[$attribute]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof SignedInUser && Permission::granted($user->role(), $attribute);
    }
}
