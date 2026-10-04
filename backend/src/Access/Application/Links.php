<?php

namespace App\Access\Application;

use App\Access\Domain\Error\LinkInvalid;
use App\Access\Domain\Model\AccessToken;
use App\Access\Domain\Model\TokenPurpose;
use App\Access\Domain\Model\User;
use App\Access\Domain\Repository\AccessTokenRepository;

/**
 * The one-use links e-mailed to people: an invitation lasts seven days, a password reset one hour. A new link
 * replaces the person's earlier unused one of the same kind. Only the token's hash is stored; the token itself
 * travels in the e-mail, after the `#` of the URL so it never reaches a server log.
 */
final class Links
{
    public const INVITATION_LIFETIME = 'P7D';
    public const PASSWORD_RESET_LIFETIME = 'PT1H';

    public function __construct(private readonly AccessTokenRepository $tokens)
    {
    }

    /**
     * @return array{string, \DateTimeImmutable} the token for the e-mail, and when it expires
     */
    public function issue(User $user, TokenPurpose $purpose, \DateTimeImmutable $now): array
    {
        $this->revoke($user, $purpose, $now);
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $link = AccessToken::issue($user->id(), $purpose, $token, $now, new \DateInterval(TokenPurpose::Invitation === $purpose ? self::INVITATION_LIFETIME : self::PASSWORD_RESET_LIFETIME));
        $this->tokens->add($link);

        return [$token, $link->expiresAt()];
    }

    /** Every unused link of this kind stops working. */
    public function revoke(User $user, TokenPurpose $purpose, \DateTimeImmutable $now): void
    {
        foreach ($this->tokens->unusedOf($user->id(), $purpose) as $earlier) {
            $earlier->use($now);
        }
    }

    /**
     * The link this token opens, if it still works for this purpose. It is not spent here: the caller spends it
     * (`use()`) once what it was for has succeeded.
     *
     * @throws LinkInvalid
     */
    public function find(#[\SensitiveParameter] string $token, TokenPurpose $purpose, \DateTimeImmutable $now): AccessToken
    {
        $link = '' === $token ? null : $this->tokens->findByHash(AccessToken::hash($token));
        if (null === $link || $link->purpose() !== $purpose || !$link->isUsable($now)) {
            throw new LinkInvalid();
        }

        return $link;
    }
}
